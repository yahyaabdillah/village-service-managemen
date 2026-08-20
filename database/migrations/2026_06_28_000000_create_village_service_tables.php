<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function audit(Blueprint $table): void
    {
        $table->timestamps();
        $table->softDeletes();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
    }

    public function up(): void
    {
        Schema::create('family_cards', function (Blueprint $table) {
            $table->id();
            $table->string('family_card_number')->unique();
            $table->string('head_of_family_name');
            $table->text('address');
            $table->string('hamlet')->nullable()->index();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('postal_code')->nullable();
            $table->index(['rt', 'rw']);
            $this->audit($table);
        });
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nik')->unique();
            $table->string('name')->index();
            $table->string('gender');
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address');
            $table->string('hamlet')->nullable()->index();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('religion')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('occupation')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->index(['rt', 'rw']);
            $this->audit($table);
        });
        Schema::create('village_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('village_name');
            $table->string('district')->nullable();
            $table->string('regency')->nullable();
            $table->string('province')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('village_head_name')->nullable();
            $table->string('village_head_nip')->nullable();
            $table->string('default_signer_name')->nullable();
            $table->string('default_signer_title')->nullable();
            $table->string('letterhead_logo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table);
        });
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $this->audit($table);
        });
        Schema::create('service_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true)->index();
            $table->json('allowed_file_types')->nullable();
            $table->unsignedInteger('max_file_size_kb')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $this->audit($table);
        });
        Schema::create('service_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('field_key');
            $table->string('field_type');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['service_type_id', 'field_key']);
            $this->audit($table);
        });
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('template_file_path');
            $table->string('original_file_name')->nullable();
            $table->unsignedInteger('page_count')->default(1);
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });
        Schema::create('template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('variable_key');
            $table->unsignedInteger('page_number')->default(1);
            $table->decimal('x_position', 5, 2);
            $table->decimal('y_position', 5, 2);
            $table->decimal('width', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('font_size', 5, 2)->default(11);
            $table->string('text_align')->default('left');
            $table->string('text_color')->default('#000000');
            $table->string('font_weight')->nullable();
            $this->audit($table);
        });
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();
            $table->foreignId('service_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nik')->index();
            $table->string('applicant_name');
            $table->string('phone');
            $table->text('address');
            $table->string('hamlet')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('letter_number')->nullable()->index();
            $table->string('status')->default('submitted')->index();
            $table->dateTime('submitted_at')->nullable()->index();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('public_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->string('document_source')->nullable();
            $table->string('generated_document_path')->nullable();
            $table->string('uploaded_document_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $this->audit($table);
        });
        Schema::create('service_request_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_key')->index();
            $table->string('label');
            $table->text('value')->nullable();
            $this->audit($table);
        });
        Schema::create('request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->index();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $this->audit($table);
        });
        Schema::create('service_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->index();
            $table->text('note')->nullable();
            $table->boolean('is_public')->default(true);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('file_path');
            $table->string('original_file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('generated_at')->nullable();
            $this->audit($table);
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $this->audit($table);
        });
    }

    public function down(): void
    {
        foreach (['announcements', 'generated_documents', 'service_request_status_histories', 'request_files', 'service_request_field_values', 'service_requests', 'template_fields', 'document_templates', 'service_type_fields', 'service_requirements', 'service_types', 'village_profiles', 'residents', 'family_cards'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
