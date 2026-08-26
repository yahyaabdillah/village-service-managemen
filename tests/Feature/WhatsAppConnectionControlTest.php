<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppConnectionControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.bridge_url' => 'http://127.0.0.1:3100',
            'whatsapp.bridge_token' => 'test-token',
        ]);
    }

    public function test_connected_page_shows_success_notice_and_disconnect_action(): void
    {
        Http::fake(['127.0.0.1:3100/status' => Http::response(['ready' => true, 'state' => 'ready'])]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('WhatsApp berhasil terhubung')
            ->assertSee('Putuskan WhatsApp')
            ->assertSee(route('admin.whatsapp.disconnect'), false);
    }

    public function test_disconnected_page_shows_qr(): void
    {
        Http::fake([
            '127.0.0.1:3100/status' => Http::response(['ready' => false, 'state' => 'qr']),
            '127.0.0.1:3100/qr' => Http::response(['qr' => 'raw-qr', 'qrImage' => 'data:image/png;base64,abc']),
        ]);
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Scan QR di bawah')
            ->assertDontSee('Putuskan WhatsApp');
    }

    public function test_unreachable_bridge_shows_warning(): void
    {
        Http::fake(fn () => throw new ConnectionException('Bridge tidak merespons.'));
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Bridge tidak terjangkau')
            ->assertDontSee('Putuskan WhatsApp');
    }

    public function test_admin_can_disconnect_bridge(): void
    {
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
    }

    public function test_disconnect_reports_failure_when_bridge_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Bridge tidak merespons.'));
        $this->seed();
        $admin = User::where('email', 'admin@desa.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.whatsapp.disconnect'))
            ->assertRedirect(route('admin.whatsapp.index'))
            ->assertSessionHas('status', 'WhatsApp gagal diputuskan. Bridge tidak merespons, coba lagi beberapa saat.');
    }
}
