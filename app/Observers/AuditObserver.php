<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditObserver
{
    private const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);
        if ($changes === []) {
            return;
        }
        $before = collect(array_keys($changes))->mapWithKeys(fn (string $key) => [$key => $model->getOriginal($key)])->all();
        $this->record($model, 'updated', $before, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getOriginal(), null);
    }

    private function record(Model $model, string $action, ?array $before, ?array $after): void
    {
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $model::class,
            'subject_id' => (string) $model->getKey(),
            'subject_label' => $this->label($model),
            'before' => $before === null ? null : Arr::except($before, self::HIDDEN),
            'after' => $after === null ? null : Arr::except($after, self::HIDDEN),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
            'occurred_at' => now(),
        ]);
    }

    private function label(Model $model): string
    {
        foreach (['number', 'name', 'email', 'sku', 'code', 'lot_number', 'key'] as $attribute) {
            if ($model->getAttribute($attribute)) {
                return (string) $model->getAttribute($attribute);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
