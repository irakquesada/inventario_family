<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use App\Models\ReservationResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateReservation
{
    public function __construct(private BusinessAvailability $availability) {}

    public function handle(ReservationResource $resource, array $data, ?User $user = null, string $source = 'admin'): Reservation
    {
        $startsAt = CarbonImmutable::parse($data['date'].' '.$data['start_time'], config('app.timezone'));
        $endsAt = $startsAt->addMinutes((int) $data['duration_minutes']);
        $this->availability->ensureWithinHours($startsAt, $endsAt);

        return DB::transaction(function () use ($resource, $data, $user, $source, $startsAt, $endsAt): Reservation {
            $resource = ReservationResource::query()->whereKey($resource->id)->lockForUpdate()->firstOrFail();
            if (! $resource->is_active) {
                throw ValidationException::withMessages(['resourceId' => 'El recurso seleccionado no está activo.']);
            }
            if ((int) $data['party_size'] > $resource->capacity) {
                throw ValidationException::withMessages(['partySize' => "La capacidad máxima es {$resource->capacity}."]);
            }

            $overlap = Reservation::query()
                ->whereBelongsTo($resource, 'resource')
                ->whereIn('status', ['pending', 'confirmed', 'seated'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->lockForUpdate()
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['startTime' => 'Ese recurso ya está reservado durante parte del intervalo.']);
            }

            return Reservation::query()->create([
                'reference' => 'RS-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'reservation_resource_id' => $resource->id,
                'created_by' => $user?->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?: null,
                'customer_phone' => $data['customer_phone'] ?: null,
                'party_size' => $data['party_size'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $data['status'] ?? 'confirmed',
                'source' => $source,
                'notes' => $data['notes'] ?: null,
            ]);
        });
    }
}
