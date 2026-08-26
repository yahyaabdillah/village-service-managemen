<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BackupService
{
    public function create(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension wajib tersedia untuk membuat backup.');
        }

        $filename = 'backup-'.now()->format('Ymd-His').'.zip';
        $storagePath = 'backups/'.$filename;
        $absolutePath = tempnam(sys_get_temp_dir(), 'vsm-backup-');
        if ($absolutePath === false) {
            throw new RuntimeException('Tidak bisa membuat file backup sementara.');
        }

        $zip = new ZipArchive;
        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($absolutePath);
            throw new RuntimeException('Tidak bisa membuat archive backup.');
        }

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'database_connection' => config('database.default'),
            'includes' => ['database_sqlite_if_present', 'mysql_dump_if_configured', 'private_storage_without_backups'],
        ];

        $sqlitePath = database_path('database.sqlite');
        if (is_file($sqlitePath)) {
            $zip->addFile($sqlitePath, 'database/database.sqlite');
        }

        if (config('database.default') === 'mysql') {
            $dump = $this->mysqlDump();
            if ($dump !== null) {
                $zip->addFromString('database/mysql.sql', $dump);
                $manifest['mysql_dump'] = true;
            }
        }

        $privateDisk = Storage::disk('private');
        $storedFiles = 0;
        foreach ($privateDisk->allFiles() as $path) {
            if (explode('/', $path, 2)[0] === 'backups') {
                continue;
            }

            $stream = $privateDisk->readStream($path);
            if (! is_resource($stream)) {
                $zip->close();
                @unlink($absolutePath);
                throw new RuntimeException("File private storage tidak dapat dibaca: {$path}");
            }

            try {
                $contents = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }

            if ($contents === false || ! $zip->addFromString('storage/private/'.$path, $contents)) {
                $zip->close();
                @unlink($absolutePath);
                throw new RuntimeException("File gagal ditambahkan ke backup: {$path}");
            }
            $storedFiles++;
        }

        $manifest['private_storage_files'] = $storedFiles;
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        $archive = fopen($absolutePath, 'rb');
        if (! is_resource($archive)) {
            @unlink($absolutePath);
            throw new RuntimeException('Archive backup tidak dapat dibaca.');
        }

        try {
            if (! $privateDisk->put($storagePath, $archive)) {
                throw new RuntimeException('Archive backup tidak dapat disimpan ke private storage.');
            }
        } finally {
            fclose($archive);
            @unlink($absolutePath);
        }

        return $storagePath;
    }

    private function mysqlDump(): ?string
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        if (! $database || ! $username) {
            return null;
        }

        $command = sprintf(
            'MYSQL_PWD=%s mysqldump --single-transaction --quick --host=%s --port=%s --user=%s %s',
            escapeshellarg((string) $password),
            escapeshellarg((string) $host),
            escapeshellarg((string) $port),
            escapeshellarg((string) $username),
            escapeshellarg((string) $database),
        );

        $result = Process::timeout((int) env('BACKUP_MYSQL_DUMP_TIMEOUT', 120))->run($command);

        if (! $result->successful()) {
            report(new RuntimeException('MySQL dump failed: '.$result->errorOutput()));

            return null;
        }

        return $result->output();
    }
}
