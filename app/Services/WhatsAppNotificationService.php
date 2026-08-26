<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Models\GeneratedDocument;
use App\Models\NotificationLog;
use App\Models\ServiceRequest;
use App\Models\VillageProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class WhatsAppNotificationService
{
    public function __construct(private readonly WindowsDetachedProcessLauncher $windowsLauncher) {}

    public function notifySubmitted(ServiceRequest $serviceRequest): void
    {
        if (! config('whatsapp.enabled') || blank($serviceRequest->phone)) {
            return;
        }

        $message = "Terima kasih 🙏 telah mengajukan layanan melalui *{$this->villageName()}*.\n\n"
            ."Berikut detail pengajuan Anda:\n"
            ."📄 Kode Pengajuan: *{$serviceRequest->request_code}*\n"
            ."🆔 NIK: *{$serviceRequest->nik}*\n"
            ."📋 Jenis Layanan: *{$serviceRequest->serviceType?->name}*\n\n"
            ."Anda dapat memantau status pengajuan kapan saja melalui halaman *Cek Status* di website kami, dengan memasukkan Kode Pengajuan dan NIK di atas:\n"
            ."🔗 {$this->statusCheckUrl()}\n\n"
            ."Kami akan mengirimkan kabar melalui WhatsApp ini setiap kali status pengajuan Anda berubah. Mohon ditunggu ya 😊";

        $this->queueMessage($serviceRequest, 'whatsapp', $message);
    }

    public function notifyStatusChanged(ServiceRequest $serviceRequest, string $status, ?string $note = null): void
    {
        if (! config('whatsapp.enabled') || blank($serviceRequest->phone)) {
            return;
        }

        $body = match ($status) {
            'verified' => "Pengajuan layanan Anda dengan kode *{$serviceRequest->request_code}* sedang *diverifikasi* oleh petugas {$this->villageName()}. ✅",
            'processing' => "Pengajuan layanan Anda dengan kode *{$serviceRequest->request_code}* sedang *diproses* oleh petugas {$this->villageName()}. ⚙️",
            'completed' => "Pengajuan layanan Anda dengan kode *{$serviceRequest->request_code}* telah *selesai diproses*. 🎉 Dokumen hasil layanan akan segera kami kirimkan menyusul.",
            'rejected' => "Mohon maaf 🙏, pengajuan layanan Anda dengan kode *{$serviceRequest->request_code}* belum dapat kami proses lebih lanjut (*ditolak*).",
            'cancelled' => "Pengajuan layanan Anda dengan kode *{$serviceRequest->request_code}* telah *dibatalkan*.",
            default => "Status pengajuan layanan Anda dengan kode *{$serviceRequest->request_code}* telah diperbarui menjadi *{$serviceRequest->publicStatusLabel()}*.",
        };

        $message = "🔔 *Pemberitahuan Otomatis*\n\n".$body;
        if ($note) {
            $message .= "\n📝 Catatan: {$note}";
        }
        $message .= "\n\n🆔 NIK: *{$serviceRequest->nik}*\n"
            ."🔗 Cek detail pengajuan: {$this->statusCheckUrl()}\n\n"
            .'Terima kasih atas kesabarannya 🙏';

        $this->queueMessage($serviceRequest, 'whatsapp', $message);
    }

    private function queueMessage(ServiceRequest $serviceRequest, string $channel, string $message): void
    {
        $log = NotificationLog::create([
            'service_request_id' => $serviceRequest->id,
            'channel' => $channel,
            'recipient' => $serviceRequest->phone,
            'message' => $message,
            'status' => 'pending',
        ]);

        SendWhatsAppNotification::dispatch($log->id)
            ->onConnection((string) config('whatsapp.queue_connection', 'sync'));
    }

    private function villageName(): string
    {
        return VillageProfile::where('is_active', true)->value('village_name') ?? config('app.name', 'Layanan Desa');
    }

    private function statusCheckUrl(): string
    {
        return route('status.form');
    }

    private function assertWithinRateLimit(string $recipient): void
    {
        $globalKey = 'whatsapp-send:global';
        $recipientKey = 'whatsapp-send:'.$recipient;
        $globalLimit = max(1, (int) config('whatsapp.rate_limit_per_minute', 20));
        $recipientLimit = max(1, (int) config('whatsapp.rate_limit_per_recipient', 5));

        if (RateLimiter::tooManyAttempts($globalKey, $globalLimit) || RateLimiter::tooManyAttempts($recipientKey, $recipientLimit)) {
            throw new RuntimeException('Batas pengiriman WhatsApp tercapai, coba lagi beberapa saat.');
        }

        RateLimiter::hit($globalKey, 60);
        RateLimiter::hit($recipientKey, 600);
    }

    public function sendNow(NotificationLog $log): void
    {
        try {
            $this->assertWithinRateLimit($log->recipient);

            Http::timeout(5)
                ->withToken((string) config('whatsapp.bridge_token'))
                ->post(config('whatsapp.bridge_url').'/send-message', [
                    'phone' => $log->recipient,
                    'message' => $log->message,
                ])
                ->throw();

            $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::channel('security')->warning('whatsapp.send_failed', [
                'notification_log_id' => $log->id,
                'phone' => $log->recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function send(string $phone, string $message): void
    {
        $log = NotificationLog::create([
            'channel' => 'whatsapp',
            'recipient' => $phone,
            'message' => $message,
            'status' => 'pending',
        ]);

        SendWhatsAppNotification::dispatch($log->id)
            ->onConnection((string) config('whatsapp.queue_connection', 'sync'));
    }

    public function sendDocument(ServiceRequest $serviceRequest, GeneratedDocument $document): void
    {
        if ($document->service_request_id !== $serviceRequest->id || ! $document->is_active || $document->status !== 'valid') {
            throw new RuntimeException('Dokumen final aktif tidak valid.');
        }

        if (blank($serviceRequest->phone)) {
            throw new RuntimeException('Nomor WhatsApp pemohon belum tersedia.');
        }

        $disk = Storage::disk('private');
        if (! $disk->exists($document->file_path)) {
            throw new RuntimeException('File dokumen final tidak ditemukan.');
        }

        $maxBytes = max(1, (int) config('whatsapp.document_max_size_kb', 5120)) * 1024;
        $size = $disk->size($document->file_path);
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('Ukuran dokumen tidak dapat dikirim lewat WhatsApp.');
        }

        $contents = $disk->get($document->file_path);
        $extension = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION) ?: (string) $document->file_type);
        $extension = preg_match('/^[a-z0-9]{1,10}$/', $extension) ? $extension : 'bin';
        $filename = $serviceRequest->request_code.'.'.$extension;
        $mimeType = $document->mime_type ?: $disk->mimeType($document->file_path) ?: 'application/octet-stream';
        $caption = "📎 Berikut dokumen hasil layanan Anda untuk pengajuan *{$serviceRequest->request_code}* ({$serviceRequest->serviceType?->name}).\n\n"
            ."Jika ada pertanyaan lebih lanjut, silakan hubungi kantor {$this->villageName()}. Terima kasih telah menggunakan layanan kami 🙏";

        $log = NotificationLog::create([
            'service_request_id' => $serviceRequest->id,
            'channel' => 'whatsapp_document',
            'recipient' => $serviceRequest->phone,
            'message' => $caption,
            'status' => 'pending',
        ]);

        try {
            $this->assertWithinRateLimit($serviceRequest->phone);

            Http::connectTimeout(2)
                ->timeout(15)
                ->withToken((string) config('whatsapp.bridge_token'))
                ->post(config('whatsapp.bridge_url').'/send-document', [
                    'phone' => $serviceRequest->phone,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'document' => base64_encode($contents),
                    'caption' => $caption,
                ])
                ->throw();

            $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            Log::channel('security')->warning('whatsapp.document_send_failed', [
                'notification_log_id' => $log->id,
                'service_request_id' => $serviceRequest->id,
                'generated_document_id' => $document->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function startBridge(): array
    {
        $storage = dirname((string) config('whatsapp.status_file'));
        if (! is_dir($storage)) {
            mkdir($storage, 0755, true);
        }
        if (! is_dir(dirname((string) config('whatsapp.bridge_log_file')))) {
            mkdir(dirname((string) config('whatsapp.bridge_log_file')), 0755, true);
        }

        if ($this->isBridgeRunning()) {
            return ['started' => false, 'running' => true, 'message' => 'Bridge WhatsApp sudah berjalan.'];
        }

        file_put_contents((string) config('whatsapp.status_file'), json_encode([
            'ready' => false,
            'state' => 'starting_from_admin',
            'updated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        $pid = PHP_OS_FAMILY === 'Windows'
            ? $this->startBridgeOnWindows($storage)
            : $this->startBridgeOnUnix($storage);

        if ($pid !== '') {
            file_put_contents((string) config('whatsapp.bridge_pid_file'), $pid);
        }

        return ['started' => true, 'running' => $pid !== '', 'pid' => $pid, 'message' => 'Bridge WhatsApp dijalankan. Tunggu beberapa detik sampai QR muncul.'];
    }

    public function disconnectBridge(): array
    {
        $fallback = false;

        try {
            Http::connectTimeout(1)
                ->timeout(5)
                ->withToken((string) config('whatsapp.bridge_token'))
                ->post(config('whatsapp.bridge_url').'/disconnect')
                ->throw();
        } catch (\Throwable $exception) {
            $fallback = true;
            $processStopped = $this->stopPersistedBridgeProcess();

            Log::warning('whatsapp.disconnect_fallback', [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
                'process_stopped' => $processStopped,
            ]);
        }

        $this->clearLocalBridgeSession();

        file_put_contents((string) config('whatsapp.status_file'), json_encode([
            'ready' => false,
            'state' => 'disconnected',
            'fallback' => $fallback,
            'error' => null,
            'updated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        return [
            'disconnected' => true,
            'fallback' => $fallback,
            'message' => $fallback
                ? 'WhatsApp diputuskan melalui fallback karena bridge tidak merespons.'
                : 'WhatsApp berhasil diputuskan.',
        ];
    }

    public function isBridgeRunning(): bool
    {
        try {
            return Http::timeout(1)
                ->withToken((string) config('whatsapp.bridge_token'))
                ->get(config('whatsapp.bridge_url').'/status')
                ->successful();
        } catch (\Throwable) {
            // Fall back to the PID check for a bridge that is still booting.
        }

        $pidFile = (string) config('whatsapp.bridge_pid_file');
        if (! is_file($pidFile)) {
            return false;
        }
        $pid = trim((string) file_get_contents($pidFile));
        if ($pid === '' || ! ctype_digit($pid)) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->windowsLauncher->isRunning($pid);
        }

        return function_exists('posix_kill') ? @posix_kill((int) $pid, 0) : is_dir('/proc/'.$pid);
    }

    public function status(): array
    {
        $path = (string) config('whatsapp.status_file');
        if (! is_file($path)) {
            return ['ready' => false, 'state' => 'not_started', 'running' => $this->isBridgeRunning()];
        }

        $status = json_decode((string) file_get_contents($path), true) ?: ['ready' => false, 'state' => 'unknown'];
        $status['running'] = $this->isBridgeRunning();
        $status['stale'] = (bool) ($status['ready'] ?? false) && ! $status['running'];

        return $status;
    }

    public function qr(): ?string
    {
        $path = (string) config('whatsapp.qr_file');

        return is_file($path) ? file_get_contents($path) : null;
    }

    public function qrImage(): ?string
    {
        $path = (string) config('whatsapp.qr_image_file');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    private function startBridgeOnWindows(string $storage): string
    {
        return $this->windowsLauncher->start([
            'node',
            base_path('wa-bridge/server.js'),
        ], base_path(), [
            'WHATSAPP_BRIDGE_TOKEN' => (string) config('whatsapp.bridge_token'),
            'WA_BRIDGE_STORAGE' => $storage,
            'WA_BRIDGE_PORT' => '3100',
        ], (string) config('whatsapp.bridge_log_file'), (string) config('whatsapp.bridge_error_log_file'));
    }

    private function startBridgeOnUnix(string $storage): string
    {
        $command = sprintf(
            'cd %s && WHATSAPP_BRIDGE_TOKEN=%s WA_BRIDGE_STORAGE=%s WA_BRIDGE_PORT=3100 nohup npm run wa:bridge >> %s 2>&1 & echo $!',
            escapeshellarg(base_path()),
            escapeshellarg((string) config('whatsapp.bridge_token')),
            escapeshellarg($storage),
            escapeshellarg((string) config('whatsapp.bridge_log_file')),
        );

        return trim((string) shell_exec($command));
    }

    private function stopPersistedBridgeProcess(): bool
    {
        $pidFile = (string) config('whatsapp.bridge_pid_file');
        if (! is_file($pidFile)) {
            return false;
        }

        $pid = trim((string) file_get_contents($pidFile));
        if ($pid === '' || ! ctype_digit($pid) || (int) $pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->windowsLauncher->stop($pid);
        }

        $commandLineFile = '/proc/'.$pid.'/cmdline';
        if (! function_exists('posix_kill') || ! is_file($commandLineFile)) {
            return false;
        }

        $commandLine = str_replace("\0", ' ', (string) file_get_contents($commandLineFile));
        if (! str_contains($commandLine, 'wa-bridge/server.js')) {
            return false;
        }

        return @posix_kill((int) $pid, SIGTERM);
    }

    private function clearLocalBridgeSession(): void
    {
        File::delete([
            (string) config('whatsapp.bridge_pid_file'),
            (string) config('whatsapp.qr_file'),
            (string) config('whatsapp.qr_image_file'),
        ]);

        $sessionDirectory = dirname((string) config('whatsapp.status_file')).DIRECTORY_SEPARATOR.'session-baileys';
        if (File::isDirectory($sessionDirectory)) {
            File::deleteDirectory($sessionDirectory);
        }

        if (File::isDirectory($sessionDirectory)) {
            throw new \RuntimeException('Folder sesi WhatsApp tidak dapat dibersihkan.');
        }
    }
}
