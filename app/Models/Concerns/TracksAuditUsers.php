<?php

namespace App\Models\Concerns;

use App\Support\AuditPresenter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

trait TracksAuditUsers
{
    protected static array $auditColumnCache = [];

    protected static function bootTracksAuditUsers(): void
    {
        static::creating(function ($model): void {
            $userId = auth()->id();
            if (! $userId) {
                return;
            }

            if ($model->hasAuditColumn('created_by') && blank($model->created_by)) {
                $model->created_by = $userId;
            }
            if ($model->hasAuditColumn('updated_by') && blank($model->updated_by)) {
                $model->updated_by = $userId;
            }
        });

        static::created(function ($model): void {
            $model->recordAuditActivity('created');
        });

        static::updating(function ($model): void {
            $userId = auth()->id();
            if ($userId && $model->hasAuditColumn('updated_by')) {
                $model->updated_by = $userId;
            }
        });

        static::updated(function ($model): void {
            // Only the columns that actually changed, with their previous values, so the
            // audit trail can show "Status: Diajukan → Berkas diverifikasi". Touching
            // timestamps alone is not an event worth a row.
            $changes = Arr::except($model->getChanges(), ['updated_at', 'updated_by', 'created_at', 'remember_token', 'password']);
            if ($changes === []) {
                return;
            }
            $original = $model->getOriginal();
            $old = array_intersect_key($original, $changes);
            $model->recordAuditActivity('updated', $changes, $old);
        });

        static::deleting(function ($model): void {
            $userId = auth()->id();
            if (! $userId || ! $model->hasAuditColumn('deleted_by')) {
                return;
            }

            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            $model->deleted_by = $userId;
            $model->saveQuietly();
            $model->recordAuditActivity('deleted');
        });
    }

    /**
     * @param  array<string, mixed>|null  $changes  the changed columns (updated only)
     * @param  array<string, mixed>  $old  their previous values
     */
    protected function recordAuditActivity(string $event, ?array $changes = null, array $old = []): void
    {
        if (! function_exists('activity')) {
            return;
        }

        $safe = fn (array $values) => Arr::except($values, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
        $identity = Arr::only($this->getAttributes(), AuditPresenter::SUBJECTS[class_basename($this)]['identity'] ?? []);
        $attributes = $changes === null ? $safe($this->getAttributes()) : $safe($identity + $changes);

        $properties = ['attributes' => $attributes];
        if ($old !== []) {
            $properties['old'] = $safe($old);
        }

        activity('business-model')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties($properties)
            ->log(class_basename($this).' '.$event);
    }

    protected function hasAuditColumn(string $column): bool
    {
        $key = $this->getConnectionName().'|'.$this->getTable().'|'.$column;

        return self::$auditColumnCache[$key] ??= Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), $column);
    }
}
