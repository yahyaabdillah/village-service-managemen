<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use HasFactory, SoftDeletes, TracksAuditUsers;

    protected $guarded = [];

    public function familyCard(): BelongsTo
    {
        return $this->belongsTo(FamilyCard::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_required' => 'boolean', 'is_published' => 'boolean', 'allowed_file_types' => 'array', 'options' => 'array', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'processed_at' => 'datetime', 'completed_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime', 'published_at' => 'datetime', 'generated_at' => 'datetime'];
    }
}
