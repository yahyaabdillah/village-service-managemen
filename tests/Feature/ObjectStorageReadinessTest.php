<?php

namespace Tests\Feature;

use App\Services\BackupService;
use App\Services\PrivateFileStorage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ObjectStorageReadinessTest extends TestCase
{
    public function test_object_disk_is_private_and_s3_compatible(): void
    {
        $this->assertSame('s3', config('filesystems.disks.object.driver'));
        $this->assertTrue(config('filesystems.disks.object.throw'));
        $this->assertArrayNotHasKey('visibility', config('filesystems.disks.object'));
        $this->assertArrayHasKey('endpoint', config('filesystems.disks.object'));
        $this->assertArrayHasKey('use_path_style_endpoint', config('filesystems.disks.object'));
        $this->assertSame('when_required', config('filesystems.disks.object.request_checksum_calculation'));
    }

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

    public function test_backup_streams_private_objects_without_requiring_a_local_disk_path(): void
    {
        $sourceStream = fopen('php://temp', 'w+b');
        fwrite($sourceStream, 'remote document');
        rewind($sourceStream);

        $archiveContents = null;
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('allFiles')->once()->andReturn(['service-requests/REQ-1/document.txt']);
        $disk->shouldReceive('readStream')
            ->once()
            ->with('service-requests/REQ-1/document.txt')
            ->andReturn($sourceStream);
        $disk->shouldReceive('put')
            ->once()
            ->withArgs(function (string $path, $stream) use (&$archiveContents): bool {
                $archiveContents = stream_get_contents($stream);

                return str_starts_with($path, 'backups/backup-') && is_string($archiveContents);
            })
            ->andReturnTrue();

        Storage::shouldReceive('disk')->with('private')->andReturn($disk);

        $path = app(BackupService::class)->create();

        $this->assertStringStartsWith('backups/backup-', $path);
        $this->assertNotNull($archiveContents);

        $temporaryArchive = tempnam(sys_get_temp_dir(), 'backup-test-');
        file_put_contents($temporaryArchive, $archiveContents);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($temporaryArchive));
        $this->assertSame(
            'remote document',
            $zip->getFromName('storage/private/service-requests/REQ-1/document.txt'),
        );
        $zip->close();
        @unlink($temporaryArchive);
    }

    public function test_health_check_verifies_private_storage_without_leaving_test_objects(): void
    {
        Storage::fake('private');

        $this->get(route('health'))
            ->assertOk()
            ->assertJsonPath('checks.private_storage.ok', true);

        $this->assertSame([], Storage::disk('private')->allFiles('healthcheck'));
    }
}
