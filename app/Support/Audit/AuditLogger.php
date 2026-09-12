<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Log an auditable event.
     */
    public static function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Log a model creation event.
     */
    public static function created(Model $model, ?array $attributes = null): AuditLog
    {
        return static::log(
            action: static::actionForModel($model, 'created'),
            model: $model,
            newValues: $attributes ?? $model->getAttributes(),
        );
    }

    /**
     * Log a model update event.
     */
    public static function updated(Model $model, array $oldValues, array $newValues): AuditLog
    {
        return static::log(
            action: static::actionForModel($model, 'updated'),
            model: $model,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }

    /**
     * Log a model deletion event.
     */
    public static function deleted(Model $model): AuditLog
    {
        return static::log(
            action: static::actionForModel($model, 'deleted'),
            model: $model,
            oldValues: $model->getAttributes(),
        );
    }

    /**
     * Derive action name from model class name.
     */
    private static function actionForModel(Model $model, string $verb): string
    {
        $shortName = class_basename($model);

        return strtolower($shortName).'.'.$verb;
    }
}
