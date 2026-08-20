<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('activitylog.database_connection'));

        if (! $schema->hasTable('activity_log') || $schema->hasColumn('activity_log', 'batch_uuid')) {
            return;
        }

        $schema->table('activity_log', function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(config('activitylog.database_connection'));

        if (! $schema->hasTable('activity_log') || ! $schema->hasColumn('activity_log', 'batch_uuid')) {
            return;
        }

        $schema->table('activity_log', function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }
};
