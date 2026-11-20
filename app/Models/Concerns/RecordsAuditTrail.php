<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an AuditLog row whenever the model is created, updated or deleted.
 *
 * The trait is intentionally lightweight (no observer classes to register).
 * Seeders run with `WithoutModelEvents`, so seeded data stays quiet.
 */
trait RecordsAuditTrail
{
    public static function bootRecordsAuditTrail(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->log('created', $model, [], $model->getRawOriginal());
        });

        static::updated(function (Model $model): void {
            $changes = $model->getDirty();

            if ($changes === []) {
                return;
            }

            $before = [];

            foreach (array_keys($changes) as $key) {
                $before[$key] = $model->getRawOriginal($key);
            }

            app(AuditLogger::class)->log('updated', $model, $before, $changes);
        });

        static::deleted(function (Model $model): void {
            app(AuditLogger::class)->log('deleted', $model, $model->getRawOriginal(), []);
        });
    }
}
