<?php

namespace App\Services\People;

use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToggleTimeClock
{
    public function clockIn(Employee $employee, User $actor, ?string $ipAddress): TimeEntry
    {
        return DB::transaction(function () use ($employee, $actor, $ipAddress): TimeEntry {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            if (! $employee->is_active) {
                throw ValidationException::withMessages(['clock' => 'El empleado no está activo.']);
            }
            if ($employee->timeEntries()->whereNull('clocked_out_at')->exists()) {
                throw ValidationException::withMessages(['clock' => 'Ya existe un fichaje abierto.']);
            }

            return $employee->timeEntries()->create([
                'recorded_by' => $actor->id, 'clocked_in_at' => now(), 'source' => 'web', 'ip_address' => $ipAddress,
            ]);
        });
    }

    public function clockOut(Employee $employee, User $actor): TimeEntry
    {
        return DB::transaction(function () use ($employee, $actor): TimeEntry {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $entry = $employee->timeEntries()->whereNull('clocked_out_at')->lockForUpdate()->first();
            if (! $entry) {
                throw ValidationException::withMessages(['clock' => 'No existe un fichaje abierto.']);
            }
            $entry->update(['clocked_out_at' => now(), 'recorded_by' => $actor->id]);

            return $entry;
        });
    }
}
