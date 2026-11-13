<?php

namespace App\Services\People;

use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\TimeEntryAdjustment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageTimeEntry
{
    public function create(Employee $employee, User $actor, string $clockedInAt, ?string $clockedOutAt, string $reason, ?string $ipAddress): TimeEntry
    {
        [$clockIn, $clockOut] = $this->dates($clockedInAt, $clockedOutAt);
        $this->ensureNoOverlap($employee, $clockIn, $clockOut);

        return DB::transaction(function () use ($employee, $actor, $clockIn, $clockOut, $reason, $ipAddress): TimeEntry {
            $entry = TimeEntry::query()->create([
                'employee_id' => $employee->id, 'recorded_by' => $actor->id,
                'clocked_in_at' => $clockIn, 'clocked_out_at' => $clockOut,
                'source' => 'admin', 'ip_address' => $ipAddress, 'notes' => trim($reason),
            ]);
            TimeEntryAdjustment::query()->create([
                'time_entry_id' => $entry->id, 'user_id' => $actor->id, 'action' => 'created',
                'previous_clocked_in_at' => $clockIn, 'previous_clocked_out_at' => $clockOut,
                'new_clocked_in_at' => $clockIn, 'new_clocked_out_at' => $clockOut,
                'reason' => trim($reason), 'ip_address' => $ipAddress,
            ]);

            return $entry;
        });
    }

    public function delete(TimeEntry $entry, User $actor, string $reason, ?string $ipAddress): void
    {
        DB::transaction(function () use ($entry, $actor, $reason, $ipAddress): void {
            $entry = TimeEntry::query()->lockForUpdate()->findOrFail($entry->id);
            TimeEntryAdjustment::query()->create([
                'time_entry_id' => $entry->id, 'user_id' => $actor->id, 'action' => 'deleted',
                'previous_clocked_in_at' => $entry->clocked_in_at, 'previous_clocked_out_at' => $entry->clocked_out_at,
                'new_clocked_in_at' => $entry->clocked_in_at, 'new_clocked_out_at' => $entry->clocked_out_at,
                'reason' => trim($reason), 'ip_address' => $ipAddress,
            ]);
            $entry->delete();
        });
    }

    private function dates(string $clockedInAt, ?string $clockedOutAt): array
    {
        $clockIn = CarbonImmutable::parse($clockedInAt, config('app.timezone'));
        $clockOut = $clockedOutAt ? CarbonImmutable::parse($clockedOutAt, config('app.timezone')) : null;
        if ($clockOut && $clockOut->lte($clockIn)) {
            throw ValidationException::withMessages(['entryOut' => 'La salida debe ser posterior a la entrada.']);
        }

        return [$clockIn, $clockOut];
    }

    private function ensureNoOverlap(Employee $employee, CarbonImmutable $clockIn, ?CarbonImmutable $clockOut): void
    {
        $end = $clockOut ?? $clockIn->addYears(10);
        $overlap = TimeEntry::query()->whereBelongsTo($employee)
            ->where('clocked_in_at', '<', $end)
            ->where(fn ($query) => $query->whereNull('clocked_out_at')->orWhere('clocked_out_at', '>', $clockIn))
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['entryIn' => 'El nuevo fichaje se solapa con otro registro del empleado.']);
        }
    }
}
