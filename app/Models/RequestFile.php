<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestFile extends Model
{
    use HasFactory, SoftDeletes, TracksAuditUsers;

    protected $guarded = [];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ServiceRequirement::class, 'service_requirement_id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_required' => 'boolean', 'is_published' => 'boolean', 'allowed_file_types' => 'array', 'options' => 'array', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'processed_at' => 'datetime', 'completed_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime', 'published_at' => 'datetime', 'generated_at' => 'datetime'];
    }
}
