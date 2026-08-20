<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceType extends Model
{
    use HasFactory, SoftDeletes, TracksAuditUsers;

    protected $guarded = [];

    public function requirements(): HasMany
    {
        return $this->hasMany(ServiceRequirement::class)->orderBy('sort_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ServiceTypeField::class)->orderBy('sort_order');
    }

    public function activeFields(): HasMany
    {
        return $this->fields()->where('is_active', true);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
