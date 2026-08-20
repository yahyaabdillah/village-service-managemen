<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_type_fields', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_required')->index();
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active')->index();
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->boolean('is_default')->default(false)->after('version')->index();
            $table->timestamp('validated_at')->nullable()->after('is_default');
        });

        Schema::table('template_fields', function (Blueprint $table) {
            $table->json('mapping_config')->nullable()->after('variable_key');
        });

        Schema::table('generated_documents', function (Blueprint $table) {
            $table->string('mime_type')->nullable()->after('file_type');
            $table->string('checksum', 64)->nullable()->after('file_size');
            $table->string('status')->default('valid')->after('checksum')->index();
            $table->boolean('is_active')->default(true)->after('status')->index();
            $table->text('generation_reason')->nullable()->after('is_active');
        });

        DB::table('document_templates')->where('is_active', false)->update(['status' => 'archived']);
        DB::table('document_templates')->where('is_active', true)->update(['status' => 'active']);

        DB::table('document_templates')
            ->where('is_active', true)
            ->orderBy('service_type_id')
            ->orderByDesc('id')
            ->get()
            ->unique('service_type_id')
            ->each(fn ($template) => DB::table('document_templates')->where('id', $template->id)->update(['is_default' => true]));

        DB::table('generated_documents')->update(['is_active' => false, 'status' => 'superseded']);
        DB::table('generated_documents')->orderBy('service_request_id')->orderByDesc('id')->get()
            ->unique('service_request_id')
            ->each(fn ($document) => DB::table('generated_documents')->where('id', $document->id)->update(['is_active' => true, 'status' => 'valid']));
    }

    public function down(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->dropColumn(['mime_type', 'checksum', 'status', 'is_active', 'generation_reason']);
        });
        Schema::table('template_fields', fn (Blueprint $table) => $table->dropColumn('mapping_config'));
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn(['status', 'version', 'is_default', 'validated_at']);
        });
        Schema::table('service_type_fields', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
