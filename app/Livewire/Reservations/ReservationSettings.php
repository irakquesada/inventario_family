<?php

namespace App\Livewire\Reservations;

use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Models\ReservationResource;
use App\Models\SiteSetting;
use App\Support\ReservationMailTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ReservationSettings extends Component
{
    public array $hours = [];

    public ?int $editingResourceId = null;

    public string $resourceName = '';

    public string $resourceCode = '';

    public string $resourceCapacity = '2';

    public string $resourceNotes = '';

    public string $closureDate = '';

    public bool $closureIsClosed = true;

    public string $closureOpensAt = '';

    public string $closureClosesAt = '';

    public string $closureReason = '';

    public string $mailFromName = '';

    public string $mailFromAddress = '';

    public string $mailReceived = '';

    public string $mailConfirmed = '';

    public string $mailCancelled = '';

    public function mount(): void
    {
        foreach (range(0, 6) as $day) {
            $this->hours[$day] = BusinessHour::query()->where('weekday', $day)->where('is_closed', false)
                ->orderBy('opens_at')->get()->map(fn ($hour) => [
                    'opens_at' => substr($hour->opens_at, 0, 5),
                    'closes_at' => substr($hour->closes_at, 0, 5),
                ])->values()->all();
        }
        $settings = SiteSetting::query()->firstOrCreate([], ['hero_title' => config('brand.name')]);
        $this->mailFromName = $settings->reservation_mail_from_name ?: config('mail.from.name');
        $this->mailFromAddress = $settings->reservation_mail_from_address ?: config('mail.from.address');
        $this->mailReceived = $settings->reservation_mail_received ?: ReservationMailTemplates::RECEIVED;
        $this->mailConfirmed = $settings->reservation_mail_confirmed ?: ReservationMailTemplates::CONFIRMED;
        $this->mailCancelled = $settings->reservation_mail_cancelled ?: ReservationMailTemplates::CANCELLED;
    }

    public function saveMailSettings(): void
    {
        $this->authorizeConfiguration();
        $data = $this->validate([
            'mailFromName' => ['nullable', 'string', 'max:255'],
            'mailFromAddress' => ['nullable', 'email', 'max:255'],
            'mailReceived' => ['required', 'string', 'max:5000'],
            'mailConfirmed' => ['required', 'string', 'max:5000'],
            'mailCancelled' => ['required', 'string', 'max:5000'],
        ]);
        SiteSetting::query()->firstOrFail()->update([
            'reservation_mail_from_name' => $data['mailFromName'] ?: null,
            'reservation_mail_from_address' => $data['mailFromAddress'] ?: null,
            'reservation_mail_received' => $data['mailReceived'],
            'reservation_mail_confirmed' => $data['mailConfirmed'],
            'reservation_mail_cancelled' => $data['mailCancelled'],
        ]);
        session()->flash('status', 'Correo de reservas actualizado.');
    }

    public function resetMailTemplates(): void
    {
        $this->authorizeConfiguration();
        $this->mailReceived = ReservationMailTemplates::RECEIVED;
        $this->mailConfirmed = ReservationMailTemplates::CONFIRMED;
        $this->mailCancelled = ReservationMailTemplates::CANCELLED;
    }

    public function addPeriod(int $day): void
    {
        $this->authorizeConfiguration();
        abort_unless($day >= 0 && $day <= 6, 422);
        $this->hours[$day][] = ['opens_at' => '', 'closes_at' => ''];
    }

    public function removePeriod(int $day, int $index): void
    {
        $this->authorizeConfiguration();
        unset($this->hours[$day][$index]);
        $this->hours[$day] = array_values($this->hours[$day]);
        $this->resetValidation();
    }

    public function saveHours(): void
    {
        $this->authorizeConfiguration();
        $this->validate([
            'hours.*.*.opens_at' => ['required', 'date_format:H:i'],
            'hours.*.*.closes_at' => ['required', 'date_format:H:i'],
        ]);
        if ($this->hoursOverlap()) {
            $this->addError('hours', 'Los turnos se solapan entre sí. Revisa también los cierres después de medianoche.');
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        DB::transaction(function (): void {
            BusinessHour::query()->delete();
            foreach ($this->hours as $day => $periods) {
                foreach ($periods as $period) {
                    BusinessHour::query()->create([
                        'weekday' => $day, 'is_closed' => false,
                        'opens_at' => $period['opens_at'], 'closes_at' => $period['closes_at'],
                    ]);
                }
            }
        });
        session()->flash('status', 'Horario semanal guardado.');
    }

    public function saveResource(): void
    {
        $this->authorizeConfiguration();
        $data = $this->validate([
            'resourceName' => ['required', 'string', 'max:255'],
            'resourceCode' => ['required', 'string', 'max:50', Rule::unique('reservation_resources', 'code')->ignore($this->editingResourceId)],
            'resourceCapacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'resourceNotes' => ['nullable', 'string', 'max:2000'],
        ]);
        ReservationResource::query()->updateOrCreate(['id' => $this->editingResourceId], [
            'name' => $data['resourceName'], 'code' => str($data['resourceCode'])->upper(),
            'capacity' => $data['resourceCapacity'], 'notes' => $data['resourceNotes'] ?: null,
        ]);
        $this->resetResource();
        session()->flash('status', 'Recurso guardado.');
    }

    public function editResource(int $id): void
    {
        $this->authorizeConfiguration();
        $resource = ReservationResource::query()->findOrFail($id);
        $this->editingResourceId = $resource->id;
        $this->resourceName = $resource->name;
        $this->resourceCode = $resource->code;
        $this->resourceCapacity = (string) $resource->capacity;
        $this->resourceNotes = $resource->notes ?? '';
    }

    public function toggleResource(int $id): void
    {
        $this->authorizeConfiguration();
        $resource = ReservationResource::query()->findOrFail($id);
        $resource->update(['is_active' => ! $resource->is_active]);
    }

    public function resetResource(): void
    {
        $this->reset(['editingResourceId', 'resourceName', 'resourceCode', 'resourceNotes']);
        $this->resourceCapacity = '2';
    }

    public function saveClosure(): void
    {
        $this->authorizeConfiguration();
        $data = $this->validate([
            'closureDate' => ['required', 'date'], 'closureIsClosed' => ['boolean'],
            'closureOpensAt' => [Rule::requiredIf(! $this->closureIsClosed), 'nullable', 'date_format:H:i'],
            'closureClosesAt' => [Rule::requiredIf(! $this->closureIsClosed), 'nullable', 'date_format:H:i'],
            'closureReason' => ['nullable', 'string', 'max:255'],
        ]);
        BusinessClosure::query()->updateOrCreate(['date' => $data['closureDate']], [
            'is_closed' => $data['closureIsClosed'],
            'opens_at' => $data['closureIsClosed'] ? null : $data['closureOpensAt'],
            'closes_at' => $data['closureIsClosed'] ? null : $data['closureClosesAt'],
            'reason' => $data['closureReason'] ?: null,
        ]);
        $this->reset(['closureDate', 'closureOpensAt', 'closureClosesAt', 'closureReason']);
        $this->closureIsClosed = true;
        session()->flash('status', 'Excepción de calendario guardada.');
    }

    public function deleteClosure(int $id): void
    {
        $this->authorizeConfiguration();
        BusinessClosure::query()->findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.reservations.reservation-settings', [
            'resources' => ReservationResource::query()->orderBy('name')->get(),
            'closures' => BusinessClosure::query()->whereDate('date', '>=', today())->orderBy('date')->limit(30)->get(),
        ])->layout('layouts.app', ['title' => 'Configuración de reservas']);
    }

    private function authorizeConfiguration(): void
    {
        abort_unless(auth()->user()?->can('reservations.configure'), 403);
    }

    private function hoursOverlap(): bool
    {
        $week = 7 * 24 * 60;
        $intervals = [];
        foreach ($this->hours as $day => $periods) {
            foreach ($periods as $period) {
                [$openHour, $openMinute] = array_map('intval', explode(':', $period['opens_at']));
                [$closeHour, $closeMinute] = array_map('intval', explode(':', $period['closes_at']));
                $start = ((int) $day * 1440) + ($openHour * 60) + $openMinute;
                $end = ((int) $day * 1440) + ($closeHour * 60) + $closeMinute;
                if ($end <= $start) {
                    $end += 1440;
                }
                $intervals[] = [$start, $end];
            }
        }
        foreach ($intervals as $index => [$start, $end]) {
            foreach ($intervals as $otherIndex => [$otherStart, $otherEnd]) {
                if ($index === $otherIndex) {
                    continue;
                }
                foreach ([$otherStart - $week, $otherStart, $otherStart + $week] as $shiftedStart) {
                    $shiftedEnd = $shiftedStart + ($otherEnd - $otherStart);
                    if ($start < $shiftedEnd && $end > $shiftedStart) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
