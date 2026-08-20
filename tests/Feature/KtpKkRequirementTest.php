<?php

namespace Tests\Feature;

use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KtpKkRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_ktp_kk_introduction_uses_alternative_identity_documents_instead_of_ktp(): void
    {
        $this->seed();

        $service = ServiceType::where('slug', 'surat-pengantar-ktp-kk')->firstOrFail();

        $this->assertDatabaseMissing('service_requirements', [
            'service_type_id' => $service->id,
            'name' => 'KTP Pemohon',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('service_requirements', [
            'service_type_id' => $service->id,
            'name' => 'Kartu Keluarga (KK) atau Akta Kelahiran',
            'is_required' => true,
            'deleted_at' => null,
        ]);

        $this->get(route('requests.create', $service))
            ->assertOk()
            ->assertSee('Kartu Keluarga (KK) atau Akta Kelahiran')
            ->assertSee('KTP tidak wajib untuk pengajuan KTP pertama.')
            ->assertDontSee('KTP Pemohon');
    }
}
