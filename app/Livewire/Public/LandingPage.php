<?php

namespace App\Livewire\Public;

use App\Models\BusinessHour;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Services\Reservations\CreatePublicReservation;
use App\Support\ModuleManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class LandingPage extends Component
{
    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $partySize = '2';

    public string $date = '';

    public string $startTime = '';

    public string $durationMinutes = '90';

    public string $notes = '';

    public bool $privacyAccepted = false;

    public string $website = '';

    public ?string $reservationReference = null;

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function reserve(CreatePublicReservation $creator): void
    {
        $settings = SiteSetting::query()->first();
        abort_unless($settings?->reservations_enabled && app(ModuleManager::class)->enabled('reservations'), 404);
        if ($this->website !== '') {
            $this->reservationReference = 'SOLICITUD-RECIBIDA';

            return;
        }
        $key = 'public-reservation:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['startTime' => 'Has realizado demasiados intentos. Espera unos minutos.']);
        }
        RateLimiter::hit($key, 600);
        $data = $this->validate([
            'customerName' => ['required', 'string', 'min:2', 'max:255'],
            'customerEmail' => ['nullable', 'email', 'max:255'], 'customerPhone' => ['nullable', 'string', 'max:40'],
            'partySize' => ['required', 'integer', 'min:1', 'max:1000'],
            'date' => ['required', 'date', 'after_or_equal:today'], 'startTime' => ['required', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', Rule::in([60, 90, 120, 150, 180])],
            'notes' => ['nullable', 'string', 'max:1000'], 'privacyAccepted' => ['accepted'],
        ]);
        if ($data['customerEmail'] === '' && $data['customerPhone'] === '') {
            throw ValidationException::withMessages(['customerPhone' => 'Indica un teléfono o correo para confirmar la reserva.']);
        }
        $reservation = $creator->handle([
            'customer_name' => $data['customerName'], 'customer_email' => $data['customerEmail'],
            'customer_phone' => $data['customerPhone'], 'party_size' => $data['partySize'],
            'date' => $data['date'], 'start_time' => $data['startTime'],
            'duration_minutes' => $data['durationMinutes'], 'notes' => $data['notes'],
        ]);
        $this->reservationReference = $reservation->reference;
        $this->reset(['customerName', 'customerEmail', 'customerPhone', 'startTime', 'notes', 'privacyAccepted']);
        $this->partySize = '2';
        $this->durationMinutes = '90';
    }

    public function render()
    {
        $settings = SiteSetting::query()->firstOrNew([], ['hero_title' => config('brand.name')]);
        $modules = app(ModuleManager::class);
        $settings->reservations_enabled = $settings->reservations_enabled && $modules->enabled('reservations');

        return view('livewire.public.landing-page', [
            'settings' => $settings,
            'inventoryAvailable' => $modules->enabled('inventory'),
            'reservationsAvailable' => $modules->enabled('reservations'),
            'mapEmbedUrl' => $settings->address
                ? 'https://www.google.com/maps?q='.rawurlencode(str_replace("\n", ', ', $settings->address)).'&output=embed'
                : null,
            'products' => $modules->enabled('inventory')
                ? Product::query()->with('category')->where('is_active', true)->where('featured_on_landing', true)->orderBy('name')->limit(8)->get()
                : collect(),
            'testimonials' => Testimonial::query()->where('is_active', true)->orderBy('sort_order')->limit(9)->get(),
            'hours' => BusinessHour::query()->where('is_closed', false)->orderBy('weekday')->orderBy('opens_at')->get()->groupBy('weekday'),
        ])->layout('layouts.public', ['title' => $settings->hero_title]);
    }
}
