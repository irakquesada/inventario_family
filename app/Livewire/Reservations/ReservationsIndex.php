<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Models\ReservationResource;
use App\Services\Reservations\CreateReservation;
use App\Support\ReservationCustomerNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReservationsIndex extends Component
{
    #[Url]
    public string $date = '';

    #[Url]
    public ?int $resourceFilter = null;

    public ?int $resourceId = null;

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $partySize = '2';

    public string $startTime = '';

    public string $durationMinutes = '90';

    public string $notes = '';

    public function mount(): void
    {
        $this->date = $this->date ?: today()->toDateString();
        $this->resourceId = ReservationResource::query()->where('is_active', true)->orderBy('name')->value('id');
    }

    public function previousDay(): void
    {
        $this->date = now()->parse($this->date)->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->date = now()->parse($this->date)->addDay()->toDateString();
    }

    public function today(): void
    {
        $this->date = today()->toDateString();
    }

    public function save(CreateReservation $creator): void
    {
        abort_unless(auth()->user()?->can('reservations.manage'), 403);
        $data = $this->validate([
            'resourceId' => ['required', 'integer', Rule::exists('reservation_resources', 'id')->where('is_active', true)],
            'customerName' => ['required', 'string', 'min:2', 'max:255'],
            'customerEmail' => ['nullable', 'email', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:40'],
            'partySize' => ['required', 'integer', 'min:1', 'max:1000'],
            'date' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', Rule::in([30, 45, 60, 90, 120, 150, 180, 240])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($data['customerEmail'] === '' && $data['customerPhone'] === '') {
            throw ValidationException::withMessages(['customerPhone' => 'Indica al menos un teléfono o correo de contacto.']);
        }

        $reservation = $creator->handle(ReservationResource::query()->findOrFail($data['resourceId']), [
            'customer_name' => $data['customerName'], 'customer_email' => $data['customerEmail'],
            'customer_phone' => $data['customerPhone'], 'party_size' => $data['partySize'],
            'date' => $data['date'], 'start_time' => $data['startTime'],
            'duration_minutes' => $data['durationMinutes'], 'notes' => $data['notes'],
        ], auth()->user());
        $this->reset(['customerName', 'customerEmail', 'customerPhone', 'startTime', 'notes']);
        $this->partySize = '2';
        $this->durationMinutes = '90';
        session()->flash('status', "Reserva {$reservation->reference} creada correctamente.");
    }

    public function changeStatus(int $reservationId, string $status, ReservationCustomerNotifier $customerNotifier): void
    {
        abort_unless(auth()->user()?->can('reservations.manage'), 403);
        $transitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['seated', 'cancelled', 'no_show'],
            'seated' => ['completed', 'cancelled'],
        ];
        $reservation = DB::transaction(function () use ($reservationId, $status, $transitions): Reservation {
            $reservation = Reservation::query()->whereKey($reservationId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($status, $transitions[$reservation->status] ?? [], true), 422);
            $reservation->update([
                'status' => $status,
                'cancelled_at' => $status === 'cancelled' ? now() : null,
                'cancelled_by' => $status === 'cancelled' ? auth()->id() : null,
            ]);

            return $reservation;
        });
        if (in_array($status, ['confirmed', 'cancelled'], true)) {
            $customerNotifier->send($reservation, $status);
        }
        session()->flash('status', 'Estado de la reserva actualizado.');
    }

    public function render()
    {
        return view('livewire.reservations.reservations-index', [
            'resources' => ReservationResource::query()->where('is_active', true)->orderBy('name')->get(),
            'reservations' => Reservation::query()->with(['resource', 'creator'])
                ->whereDate('starts_at', $this->date)
                ->when($this->resourceFilter, fn ($q) => $q->where('reservation_resource_id', $this->resourceFilter))
                ->orderBy('starts_at')->get(),
        ])->layout('layouts.app', ['title' => 'Reservas']);
    }
}
