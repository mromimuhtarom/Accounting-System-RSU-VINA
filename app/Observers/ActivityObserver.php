<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ActivityObserver
{
    private static ?\WeakMap $beforeDelete = null;

    public function deleting(Model $model): void
    {
        self::$beforeDelete ??= new \WeakMap();
        self::$beforeDelete[$model] = $model->getAttributes();
    }

    public function created(Model $model): void
    {
        app(ActivityLogger::class)->record($model, 'created', null, $model->getAttributes(), array_keys($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        app(ActivityLogger::class)->record($model, 'updated', $model->getRawOriginal(), $model->getAttributes(), array_keys($model->getChanges()));
    }

    public function deleted(Model $model): void
    {
        $before = self::$beforeDelete[$model] ?? $model->getAttributes();
        app(ActivityLogger::class)->record($model, 'deleted', $before, null, array_keys($before));
        unset(self::$beforeDelete[$model]);
    }

    public function restored(Model $model): void
    {
        app(ActivityLogger::class)->record($model, 'restored', null, $model->getAttributes(), ['deleted_at']);
    }
}
