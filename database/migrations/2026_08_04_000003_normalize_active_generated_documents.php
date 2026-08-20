<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('generated_documents')->update(['is_active' => false, 'status' => 'superseded']);
        DB::table('generated_documents')->orderBy('service_request_id')->orderByDesc('id')->get()
            ->unique('service_request_id')
            ->each(fn ($document) => DB::table('generated_documents')->where('id', $document->id)
                ->update(['is_active' => true, 'status' => 'valid']));
    }

    public function down(): void
    {
        DB::table('generated_documents')->update(['is_active' => true, 'status' => 'valid']);
    }
};
