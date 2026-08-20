<?php

namespace App\Console\Commands;

use App\Models\VillageProfile;
use App\Services\ProfessionalLetterTemplateService;
use Illuminate\Console\Command;

class SyncOfficialDocumentTemplates extends Command
{
    protected $signature = 'documents:sync-templates';

    protected $description = 'Build ulang template PDF resmi dan mapping dari profil desa aktif';

    public function handle(ProfessionalLetterTemplateService $templates): int
    {
        $profile = VillageProfile::where('is_active', true)->first();
        if (! $profile) {
            $this->error('Profil desa aktif tidak ditemukan.');

            return self::FAILURE;
        }

        $templates->syncDefaultTemplates($profile);
        $this->info('Template PDF resmi dan mapping berhasil disinkronkan.');

        return self::SUCCESS;
    }
}
