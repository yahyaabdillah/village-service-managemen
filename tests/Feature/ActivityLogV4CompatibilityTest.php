<?php

namespace Tests\Feature;

use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogV4CompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_uses_activitylog_v4(): void
    {
        $version = InstalledVersions::getPrettyVersion('spatie/laravel-activitylog');

        $this->assertNotNull($version);
        $this->assertStringStartsWith('4.', ltrim($version, 'v'));
    }

    public function test_activity_log_schema_has_batch_uuid_column(): void
    {
        $this->assertTrue(Schema::hasColumn('activity_log', 'batch_uuid'));
    }

    public function test_activity_can_be_recorded_with_v4_schema(): void
    {
        activity('compatibility-test')->log('Activitylog v4 compatibility');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'compatibility-test',
            'description' => 'Activitylog v4 compatibility',
            'batch_uuid' => null,
        ]);
    }
}
