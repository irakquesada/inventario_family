<?php

namespace App\Services\Reservations;

use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class BusinessAvailability
{
    public function ensureWithinHours(CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages(['durationMinutes' => 'La hora final debe ser posterior al inicio.']);
        }

        foreach ([$startsAt->toImmutable()->startOfDay(), $startsAt->toImmutable()->subDay()->startOfDay()] as $businessDate) {
            foreach ($this->intervalsFor($businessDate) as $interval) {
                if ($startsAt->gte($interval[0]) && $endsAt->lte($interval[1])) {
                    return;
                }
            }
        }

        throw ValidationException::withMessages(['startTime' => 'La reserva queda fuera del horario disponible para esa fecha.']);
    }

    /** @return array<int, array{CarbonImmutable, CarbonImmutable}> */
    private function intervalsFor(CarbonImmutable $businessDate): array
    {
        $closure = BusinessClosure::query()->whereDate('date', $businessDate)->first();
        if ($closure?->is_closed) {
            return [];
        }

        $schedules = $closure
            ? collect([$closure])
            : BusinessHour::query()->where('weekday', $businessDate->dayOfWeek)->where('is_closed', false)->orderBy('opens_at')->get();

        return $schedules->filter(fn ($schedule) => $schedule->opens_at && $schedule->closes_at)->map(function ($schedule) use ($businessDate): array {
            $opensAt = CarbonImmutable::parse($businessDate->toDateString().' '.$schedule->opens_at, config('app.timezone'));
            $closesAt = CarbonImmutable::parse($businessDate->toDateString().' '.$schedule->closes_at, config('app.timezone'));
            if ($closesAt->lte($opensAt)) {
                $closesAt = $closesAt->addDay();
            }

            return [$opensAt, $closesAt];
        })->values()->all();
    }
}
