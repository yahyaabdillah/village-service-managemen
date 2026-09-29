<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use HasFactory, SoftDeletes, TracksAuditUsers;

    protected $guarded = [];

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class);
    }

    /** True when this is the template the publish action will use. */
    public function isLive(): bool
    {
        return $this->is_active && $this->is_default && $this->status === 'active';
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isLive() => 'Dipakai',
            $this->is_active && $this->status === 'active' => 'Aktif',
            $this->status === 'archived' => 'Diarsipkan',
            default => 'Draf',
        };
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->isLive() => 'success',
            $this->is_active && $this->status === 'active' => 'info',
            $this->status === 'archived' => 'muted',
            default => 'warning',
        };
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean', 'validated_at' => 'datetime'];
    }
}
