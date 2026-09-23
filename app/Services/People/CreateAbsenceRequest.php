<?php

namespace App\Services\People;

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AbsenceRequestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class CreateAbsenceRequest
{
    public function handle(Employee $employee, AbsenceType $type, array $data): AbsenceRequest
    {
        $start = CarbonImmutable::parse($data['starts_on']);
        $end = CarbonImmutable::parse($data['ends_on']);
        if ($end->lt($start) || $start->year !== $end->year) {
            throw ValidationException::withMessages(['endsOn' => 'El intervalo debe terminar después de comenzar y quedar dentro del mismo año.']);
        }
        if (! $data['is_full_day'] && ! $start->isSameDay($end)) {
            throw ValidationException::withMessages(['endsOn' => 'Una ausencia por horas debe comenzar y terminar el mismo día.']);
        }

        return DB::transaction(function () use ($employee, $type, $data, $start, $end): AbsenceRequest {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $overlap = $employee->absenceRequests()->whereIn('status', ['pending', 'approved'])
                ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['startsOn' => 'Ya existe una solicitud pendiente o aprobada en esas fechas.']);
            }
            $days = $this->requestedDays($employee, $start, $end, $data['is_full_day'], $data['starts_at'] ?? null, $data['ends_at'] ?? null);
            if ($days <= 0) {
                throw ValidationException::withMessages(['startsOn' => 'El intervalo no contiene horas de trabajo planificadas.']);
            }
            $request = $employee->absenceRequests()->create([
                'absence_type_id' => $type->id, 'starts_on' => $start, 'ends_on' => $end,
                'starts_at' => $data['is_full_day'] ? null : $data['starts_at'], 'ends_at' => $data['is_full_day'] ? null : $data['ends_at'],
                'is_full_day' => $data['is_full_day'], 'requested_days' => $days, 'status' => 'pending',
                'reason' => $data['reason'], 'attachment_path' => $data['attachment_path'] ?? null, 'attachment_name' => $data['attachment_name'] ?? null,
            ]);
            $request->load(['employee', 'type']);
            Notification::send(User::query()->where('is_active', true)->role('admin')->get(), new AbsenceRequestNotification($request, 'submitted'));

            return $request;
        });
    }

    private function requestedDays(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, bool $fullDay, ?string $startsAt, ?string $endsAt): float
    {
        if ($fullDay) {
            $days = 0;
            for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                if ($employee->workPeriods()->where('weekday', $date->dayOfWeek)->exists()) {
                    $days++;
                }
            }

            return (float) $days;
        }
        $startMinutes = $this->minutes($startsAt);
        $endMinutes = $this->minutes($endsAt);
        if ($endMinutes <= $startMinutes) {
            $endMinutes += 1440;
        }
        $planned = $employee->workPeriods()->where('weekday', $start->dayOfWeek)->get()->sum(function ($period): int {
            $from = $this->minutes($period->starts_at);
            $to = $this->minutes($period->ends_at);

            return $to > $from ? $to - $from : ($to + 1440) - $from;
        });

        return $planned > 0 ? round(min(1, ($endMinutes - $startMinutes) / $planned), 2) : 0;
    }

    private function minutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }

        return ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
    }
}
