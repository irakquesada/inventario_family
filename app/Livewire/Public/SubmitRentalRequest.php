<?php

namespace App\Livewire\Public;

use App\Models\RentalInvitation;
use App\Models\RentalRate;
use App\Models\Warehouse;
use App\Services\Rentals\ManageRental;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SubmitRentalRequest extends Component
{
    public string $token;

    public string $rentalRateId = '';

    public string $warehouseId = '';

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $quantity = '1';

    public string $startedAt = '';

    public string $plannedEndsAt = '';

    public string $notes = '';

    public string $website = '';

    public bool $privacyAccepted = false;

    public bool $submitted = false;

    public bool $invitationUnavailable = false;

    public ?string $rentalNumber = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitationUnavailable = ! $this->invitation()?->isUsable();
        $this->warehouseId = (string) Warehouse::query()->where('is_active', true)->value('id');
        $this->startedAt = now()->addHour()->format('Y-m-d\TH:i');
    }

    public function submit(ManageRental $manager): void
    {
        if ($this->invitationUnavailable || ! $this->invitation()?->isUsable()) {
            $this->invitationUnavailable = true;

            return;
        }
        if ($this->website !== '') {
            $this->submitted = true;

            return;
        }
        $key = 'rental-request:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['customerName' => 'Has realizado demasiados intentos. Espera antes de volver a intentarlo.']);
        }
        RateLimiter::hit($key, 3600);
        $data = $this->validate([
            'rentalRateId' => ['required', 'integer', 'exists:rental_rates,id'], 'warehouseId' => ['required', 'integer', 'exists:warehouses,id'],
            'customerName' => ['required', 'string', 'min:2', 'max:255'], 'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:40'], 'quantity' => ['required', 'numeric', 'gt:0'],
            'startedAt' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'], 'plannedEndsAt' => ['required', 'date_format:Y-m-d\TH:i', 'after:startedAt'],
            'notes' => ['nullable', 'string', 'max:1000'], 'privacyAccepted' => ['accepted'],
        ]);
        DB::transaction(function () use ($data, $manager): void {
            $invitation = RentalInvitation::query()->where('token_hash', hash('sha256', $this->token))->lockForUpdate()->first();
            if (! $invitation?->isUsable()) {
                throw ValidationException::withMessages(['customerName' => 'Este formulario ya no está disponible. Solicita un nuevo QR.']);
            }
            $rental = $manager->start(RentalRate::query()->findOrFail($data['rentalRateId']), Warehouse::query()->findOrFail($data['warehouseId']), null,
                ['name' => $data['customerName'], 'email' => mb_strtolower($data['customerEmail']), 'phone' => $data['customerPhone']],
                (float) $data['quantity'], $data['startedAt'], $data['plannedEndsAt'], $data['notes'], 'reserved', 'qr');
            $invitation->increment('submissions_count');
            $invitation->update(['last_used_at' => now()]);
            $this->rentalNumber = $rental->number;
        });
        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.public.submit-rental-request', [
            'rates' => RentalRate::query()->with('product')->where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true)->where('is_rentable', true))->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.public', ['title' => 'Solicitar alquiler']);
    }

    private function invitation(): ?RentalInvitation
    {
        return RentalInvitation::query()->where('token_hash', hash('sha256', $this->token))->first();
    }
}
