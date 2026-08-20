<?php

namespace App\Models\Concerns;

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
            $model->recordAuditActivity('updated');
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

    protected function recordAuditActivity(string $event): void
    {
        if (! function_exists('activity')) {
            return;
        }

        activity('business-model')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties(['attributes' => $this->getAttributes()])
            ->log(class_basename($this).' '.$event);
    }

    protected function hasAuditColumn(string $column): bool
    {
        $key = $this->getConnectionName().'|'.$this->getTable().'|'.$column;

        return self::$auditColumnCache[$key] ??= Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), $column);
    }
}
