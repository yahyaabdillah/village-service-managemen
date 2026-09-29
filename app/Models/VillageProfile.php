<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditUsers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VillageProfile extends Model
{
    use HasFactory, SoftDeletes, TracksAuditUsers;

    protected $guarded = [];

    private static ?string $activeNameCache = null;

    /** The active village's name, for page titles and branding; memoised per request. */
    public static function activeName(): string
    {
        return self::$activeNameCache ??= (static::query()->where('is_active', true)->value('village_name') ?: 'Layanan Desa');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$activeNameCache = null);
    }
}
