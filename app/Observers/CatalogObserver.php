<?php

namespace App\Observers;

use App\Events\CatalogUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatalogObserver
{
    public function saved(Model $model): void
    {
        $this->broadcastAfterCommit($model);
    }

    public function deleted(Model $model): void
    {
        $this->broadcastAfterCommit($model);
    }

    private function broadcastAfterCommit(Model $model): void
    {
        DB::afterCommit(function () use ($model): void {
            try {
                CatalogUpdated::dispatch();
            } catch (\Throwable $exception) {
                Log::warning('No se pudo emitir la actualización del catálogo público.', [
                    'model' => $model::class,
                    'model_id' => $model->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
