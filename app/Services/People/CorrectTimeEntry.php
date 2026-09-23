<?php

namespace App\Services\People;

use App\Models\TimeEntry;
use App\Models\TimeEntryAdjustment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectTimeEntry
{
    public function handle(TimeEntry $entry, User $actor, string $clockedInAt, ?string $clockedOutAt, string $reason, ?string $ipAddress): TimeEntry
    {
        $newIn = CarbonImmutable::parse($clockedInAt, config('app.timezone'));
        $newOut = $clockedOutAt ? CarbonImmutable::parse($clockedOutAt, config('app.timezone')) : null;
        if ($newOut && $newOut->lte($newIn)) {
            throw ValidationException::withMessages(['correctionOut' => 'La salida debe ser posterior a la entrada.']);
        }

        return DB::transaction(function () use ($entry, $actor, $newIn, $newOut, $reason, $ipAddress): TimeEntry {
            $entry = TimeEntry::query()->lockForUpdate()->findOrFail($entry->id);
            TimeEntryAdjustment::query()->create([
                'time_entry_id' => $entry->id, 'user_id' => $actor->id,
                'previous_clocked_in_at' => $entry->clocked_in_at, 'previous_clocked_out_at' => $entry->clocked_out_at,
                'new_clocked_in_at' => $newIn, 'new_clocked_out_at' => $newOut,
                'reason' => $reason, 'ip_address' => $ipAddress,
            ]);
            $entry->update(['clocked_in_at' => $newIn, 'clocked_out_at' => $newOut, 'recorded_by' => $actor->id]);

            return $entry;
        });
    }
}
