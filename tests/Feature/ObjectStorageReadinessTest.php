<?php

namespace Tests\Feature;

use App\Services\PrivateFileStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ObjectStorageReadinessTest extends TestCase
{
    public function test_private_file_can_be_materialized_for_local_only_libraries(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('document-templates/example.pdf', '%PDF-test-content');

        $temporaryPath = null;
        $contents = app(PrivateFileStorage::class)->withLocalFile(
            'document-templates/example.pdf',
            function (string $path) use (&$temporaryPath): string {
                $temporaryPath = $path;

                $this->assertFileExists($path);

                return (string) file_get_contents($path);
            },
        );

        $this->assertSame('%PDF-test-content', $contents);
        $this->assertNotNull($temporaryPath);
        $this->assertFileDoesNotExist($temporaryPath);
    }

    public function test_storage_migration_command_copies_files_without_loading_them_into_the_database(): void
    {
        Storage::fake('private-local');
        Storage::fake('object');
        Storage::disk('private-local')->put('service-requests/REQ-1/ktp.pdf', '%PDF-document');
        Storage::disk('private-local')->put('document-templates/template.pdf', '%PDF-template');

        $this->artisan('storage:migrate-private', [
            '--from' => 'private-local',
            '--to' => 'object',
        ])->assertSuccessful();

        Storage::disk('object')->assertExists('service-requests/REQ-1/ktp.pdf');
        Storage::disk('object')->assertExists('document-templates/template.pdf');
        $this->assertSame(
            '%PDF-document',
            Storage::disk('object')->get('service-requests/REQ-1/ktp.pdf'),
        );
    }

    public function test_storage_migration_dry_run_does_not_write_objects(): void
    {
        Storage::fake('private-local');
        Storage::fake('object');
        Storage::disk('private-local')->put('generated-documents/final.pdf', '%PDF-final');

        $this->artisan('storage:migrate-private', [
            '--from' => 'private-local',
            '--to' => 'object',
            '--dry-run' => true,
        ])->assertSuccessful();

        Storage::disk('object')->assertMissing('generated-documents/final.pdf');
    }
}
