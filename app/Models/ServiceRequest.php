<?php

namespace App\Models;

use App\Models\Concerns\TracksAuditUsers;
use App\Services\WhatsAppNotificationService;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, SoftDeletes, TracksAuditUsers;

    protected $guarded = [];

    public static function statuses(): array
    {
        return [
            'submitted' => 'Pengajuan diterima',
            'verified' => 'Sedang diverifikasi',
            'processing' => 'Sedang diproses',
            'completed' => 'Selesai',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
        ];
    }

    public static function allowedTransitions(): array
    {
        return [
            'submitted' => ['verified', 'rejected', 'cancelled'],
            'verified' => ['processing', 'completed', 'rejected', 'cancelled'],
            'processing' => ['completed', 'rejected', 'cancelled'],
            'completed' => [],
            'rejected' => [],
            'cancelled' => [],
        ];
    }

    public static function makeRequestCode(): string
    {
        do {
            $code = 'REQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::where('request_code', $code)->exists());

        return $code;
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ServiceRequestFieldValue::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(RequestFile::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ServiceRequestStatusHistory::class)->latest('created_at');
    }

    public function publicStatusHistories(): HasMany
    {
        return $this->hasMany(ServiceRequestStatusHistory::class)
            ->where('is_public', true)
            ->latest('created_at');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    public function publicStatusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::allowedTransitions()[$this->status] ?? [], true);
    }

    public function transitionTo(string $status, ?string $publicNote = null, ?string $internalNote = null, ?int $actorId = null): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new InvalidArgumentException("Invalid status transition from {$this->status} to {$status}.");
        }

        $from = $this->status;
        $timestampColumns = [
            'verified' => 'verified_at',
            'processing' => 'processed_at',
            'completed' => 'completed_at',
            'rejected' => 'rejected_at',
            'cancelled' => 'cancelled_at',
        ];
        $actorColumns = [
            'verified' => 'verified_by',
            'processing' => 'processed_by',
            'completed' => 'completed_by',
            'rejected' => 'rejected_by',
        ];

        $actorId ??= auth()->id();
        $this->status = $status;
        if (isset($timestampColumns[$status])) {
            $this->{$timestampColumns[$status]} = now();
        }
        if ($actorId && isset($actorColumns[$status])) {
            $this->{$actorColumns[$status]} = $actorId;
        }
        if ($publicNote) {
            $this->public_note = $publicNote;
        }
        if ($internalNote) {
            $this->internal_note = $internalNote;
        }
        $this->save();

        $this->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $status,
            'note' => $publicNote,
            'is_public' => true,
            'changed_by' => $actorId,
        ]);

        $requestId = $this->id;
        DB::afterCommit(fn () => app(WhatsAppNotificationService::class)
            ->notifyStatusChanged(static::find($requestId), $status, $publicNote));
    }

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
