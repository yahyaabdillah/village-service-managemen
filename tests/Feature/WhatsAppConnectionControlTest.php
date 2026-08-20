<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WindowsDetachedProcessLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppConnectionControlTest extends TestCase
{
    use RefreshDatabase;

    private string $runtimeDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runtimeDirectory = storage_path('framework/testing/whatsapp-control');
        File::deleteDirectory($this->runtimeDirectory);
        File::ensureDirectoryExists($this->runtimeDirectory);
        config([
            'whatsapp.bridge_url' => 'http://127.0.0.1:3100',
            'whatsapp.bridge_token' => 'test-token',
            'whatsapp.status_file' => $this->runtimeDirectory.'/status.json',
            'whatsapp.qr_file' => $this->runtimeDirectory.'/qr.txt',
            'whatsapp.qr_image_file' => $this->runtimeDirectory.'/qr.png',
            'whatsapp.bridge_pid_file' => $this->runtimeDirectory.'/bridge.pid',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->runtimeDirectory);

        parent::tearDown();
    }

    public function test_connected_page_shows_success_notice_and_disconnect_action(): void
    {
        $this->writeStatus(['ready' => true, 'state' => 'ready']);
        Http::fake(['127.0.0.1:3100/status' => Http::response(['ready' => true, 'state' => 'ready'])]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('WhatsApp berhasil terhubung')
            ->assertSee('Putuskan WhatsApp')
            ->assertSee(route('admin.whatsapp.disconnect'), false)
            ->assertDontSee('Mulai Pairing / Tampilkan QR');
    }

    public function test_disconnected_page_shows_pairing_action(): void
    {
        $this->writeStatus(['ready' => false, 'state' => 'not_started']);
        Http::fake(['127.0.0.1:3100/status' => Http::response([], 503)]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Mulai Pairing / Tampilkan QR')
            ->assertSee(route('admin.whatsapp.start'), false)
            ->assertDontSee('Putuskan WhatsApp');
    }

    public function test_stale_connected_page_shows_force_cleanup_action(): void
    {
        $this->writeStatus(['ready' => true, 'state' => 'ready']);
        Http::fake(['127.0.0.1:3100/status' => Http::response([], 503)]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Sesi WhatsApp terdeteksi stale')
            ->assertSee('Bersihkan Sesi Stale')
            ->assertSee(route('admin.whatsapp.disconnect'), false)
            ->assertDontSee('Mulai Pairing / Tampilkan QR');
    }

    public function test_admin_can_disconnect_bridge_and_clear_transient_pairing_files(): void
    {
        $this->writeStatus(['ready' => true, 'state' => 'ready']);
        File::put($this->runtimeDirectory.'/bridge.pid', '12345');
        File::put($this->runtimeDirectory.'/qr.txt', 'stale-qr');
        File::put($this->runtimeDirectory.'/qr.png', 'stale-image');
        Http::fake([
            '127.0.0.1:3100/status' => Http::response(['ready' => true, 'state' => 'ready']),
            '127.0.0.1:3100/disconnect' => Http::response(['ok' => true]),
        ]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.whatsapp.disconnect'))
            ->assertRedirect(route('admin.whatsapp.index'))
            ->assertSessionHas('status', 'WhatsApp berhasil diputuskan.');

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3100/disconnect'
            && $request->hasHeader('Authorization', 'Bearer test-token'));
        $this->assertFileDoesNotExist($this->runtimeDirectory.'/bridge.pid');
        $this->assertFileDoesNotExist($this->runtimeDirectory.'/qr.txt');
        $this->assertFileDoesNotExist($this->runtimeDirectory.'/qr.png');
        $this->assertSame('disconnected', json_decode(File::get($this->runtimeDirectory.'/status.json'), true)['state']);
    }

    public function test_unlink_falls_back_to_local_cleanup_when_bridge_is_stale(): void
    {
        $this->writeStatus(['ready' => true, 'state' => 'ready']);
        File::put($this->runtimeDirectory.'/bridge.pid', '12345');
        File::put($this->runtimeDirectory.'/qr.txt', 'stale-qr');
        File::ensureDirectoryExists($this->runtimeDirectory.'/session-baileys');
        File::put($this->runtimeDirectory.'/session-baileys/creds.json', '{"registered":true}');

        $launcher = new class extends WindowsDetachedProcessLauncher
        {
            public array $stoppedPids = [];

            public function isRunning(string $pid): bool
            {
                return true;
            }

            public function stop(string $pid): bool
            {
                $this->stoppedPids[] = $pid;

                return true;
            }
        };
        $this->app->instance(WindowsDetachedProcessLauncher::class, $launcher);

        Http::fake(fn () => throw new ConnectionException('Bridge tidak merespons.'));
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.whatsapp.disconnect'))
            ->assertRedirect(route('admin.whatsapp.index'))
            ->assertSessionHas('status', 'WhatsApp diputuskan melalui fallback karena bridge tidak merespons.');

        $this->assertSame(['12345'], $launcher->stoppedPids);
        $this->assertDirectoryDoesNotExist($this->runtimeDirectory.'/session-baileys');
        $this->assertFileDoesNotExist($this->runtimeDirectory.'/bridge.pid');
        $this->assertFileDoesNotExist($this->runtimeDirectory.'/qr.txt');
        $status = json_decode(File::get($this->runtimeDirectory.'/status.json'), true);
        $this->assertSame('disconnected', $status['state']);
        $this->assertTrue($status['fallback']);
    }

    public function test_bridge_exposes_authenticated_disconnect_endpoint(): void
    {
        $server = File::get(base_path('wa-bridge/server.js'));

        $this->assertStringContainsString("app.post('/disconnect'", $server);
        $this->assertStringContainsString('.logout()', $server);
        $this->assertStringContainsString('fs.rmSync(authDir', $server);
    }

    private function writeStatus(array $status): void
    {
        File::put($this->runtimeDirectory.'/status.json', json_encode($status, JSON_THROW_ON_ERROR));
    }
}
