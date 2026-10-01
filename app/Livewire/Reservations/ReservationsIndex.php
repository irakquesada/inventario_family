<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Models\ReservationResource;
use App\Services\Reservations\CreateReservation;
use App\Support\ReservationCustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ReservationsIndex extends Component
{
    use WithPagination;

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

    public string $calendarMonth = '';

    #[Url(as: 'historial_desde')]
    public string $historyFrom = '';

    #[Url(as: 'historial_hasta')]
    public string $historyTo = '';

    #[Url(as: 'historial_estado')]
    public string $historyStatus = '';

    public function mount(): void
    {
        $this->date = $this->normalizedDate($this->date, today()->toDateString());
        $this->calendarMonth = CarbonImmutable::parse($this->date)->format('Y-m');
        $this->historyFrom = $this->normalizedDate($this->historyFrom, today()->subDays(30)->toDateString());
        $this->historyTo = $this->normalizedDate($this->historyTo, today()->toDateString());
        $this->resourceId = ReservationResource::query()->where('is_active', true)->orderBy('name')->value('id');
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)->addMonth()->format('Y-m');
    }

    public function selectCalendarDay(string $date): void
    {
        $this->date = CarbonImmutable::parse($date)->toDateString();
    }

    public function updatedHistoryFrom(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedHistoryTo(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedHistoryStatus(): void
    {
        $this->resetPage('historyPage');
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
        $this->date = $this->normalizedDate($this->date, today()->toDateString());
        $this->historyFrom = $this->normalizedDate($this->historyFrom, today()->subDays(30)->toDateString());
        $this->historyTo = $this->normalizedDate($this->historyTo, today()->toDateString());
        if ($this->historyTo < $this->historyFrom) {
            $this->historyTo = $this->historyFrom;
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)->startOfMonth();
        $calendarStart = $month->startOfWeek();
        $calendarReservations = Reservation::query()->with('resource')
            ->whereBetween('starts_at', [$calendarStart->startOfDay(), $calendarStart->addDays(41)->endOfDay()])
            ->whereNotIn('status', ['cancelled'])->orderBy('starts_at')->get()->groupBy(fn ($reservation) => $reservation->starts_at->toDateString());
        $calendarDays = collect(range(0, 41))->map(function (int $offset) use ($calendarStart, $calendarReservations, $month): array {
            $date = $calendarStart->addDays($offset);

            return ['date' => $date, 'in_month' => $date->month === $month->month, 'reservations' => $calendarReservations->get($date->toDateString(), collect())];
        });
        $currentStart = $month;
        $currentEnd = $month->endOfMonth();
        $previousStart = $month->subMonth()->startOfMonth();
        $previousEnd = $month->subMonth()->endOfMonth();
        $statistics = $this->statistics($currentStart, $currentEnd);
        $previousStatistics = $this->statistics($previousStart, $previousEnd);
        $history = Reservation::query()->with(['resource', 'creator'])
            ->when($this->historyFrom, fn ($query) => $query->whereDate('starts_at', '>=', $this->historyFrom))
            ->when($this->historyTo, fn ($query) => $query->whereDate('starts_at', '<=', $this->historyTo))
            ->when($this->historyStatus, fn ($query) => $query->where('status', $this->historyStatus))
            ->latest('starts_at')->paginate(20, pageName: 'historyPage');

        return view('livewire.reservations.reservations-index', [
            'resources' => ReservationResource::query()->where('is_active', true)->orderBy('name')->get(),
            'reservations' => Reservation::query()->with(['resource', 'creator'])
                ->whereDate('starts_at', $this->date)
                ->when($this->resourceFilter, fn ($q) => $q->where('reservation_resource_id', $this->resourceFilter))
                ->orderBy('starts_at')->get(),
            'month' => $month, 'calendarDays' => $calendarDays, 'statistics' => $statistics,
            'previousStatistics' => $previousStatistics, 'history' => $history,
        ])->layout('layouts.app', ['title' => 'Reservas']);
    }

    private function statistics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = Reservation::query()->whereBetween('starts_at', [$from->startOfDay(), $to->endOfDay()]);
        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();

        return [
            'total' => $total,
            'people' => (int) (clone $query)->whereNotIn('status', ['cancelled', 'no_show'])->sum('party_size'),
            'completed' => $completed,
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'no_show' => (clone $query)->where('status', 'no_show')->count(),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    private function normalizedDate(string $date, string $fallback): string
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $date ?: $fallback)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
