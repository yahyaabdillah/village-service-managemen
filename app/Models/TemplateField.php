<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TemplateField extends Model
{
    use HasFactory, SoftDeletes, TracksAuditUsers;

    protected $guarded = [];

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    protected function casts(): array
    {
        return ['mapping_config' => 'array'];
    }
}
