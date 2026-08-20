<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
