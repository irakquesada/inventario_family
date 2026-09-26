<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use App\Models\ReservationResource;
use App\Models\User;
use App\Notifications\NewReservationNotification;
use App\Support\ReservationCustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePublicReservation
{
    public function __construct(private BusinessAvailability $availability, private CreateReservation $creator, private ReservationCustomerNotifier $customerNotifier) {}

    public function handle(array $data): Reservation
    {
        $startsAt = CarbonImmutable::parse($data['date'].' '.$data['start_time'], config('app.timezone'));
        $this->availability->ensureWithinHours($startsAt, $startsAt->addMinutes((int) $data['duration_minutes']));
        $resources = ReservationResource::query()->where('is_active', true)
            ->where('capacity', '>=', $data['party_size'])->orderBy('capacity')->orderBy('id')->get();

        foreach ($resources as $resource) {
            try {
                $reservation = $this->creator->handle($resource, [...$data, 'status' => 'pending'], source: 'public');
                DB::afterCommit(fn () => User::role('admin')->where('is_active', true)->each(
                    fn (User $admin) => $admin->notify(new NewReservationNotification($reservation->load('resource')))
                ));
                DB::afterCommit(fn () => $this->customerNotifier->send($reservation, 'received'));

                return $reservation;
            } catch (ValidationException $exception) {
                if (! array_key_exists('startTime', $exception->errors())) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages(['startTime' => 'No hay disponibilidad para ese horario y número de personas.']);
    }
}
