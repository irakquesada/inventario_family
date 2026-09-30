<?php

namespace App\Livewire\Public;

use App\Models\ReviewInvitation;
use App\Models\User;
use App\Notifications\NewTestimonialNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmitTestimonial extends Component
{
    use WithFileUploads;

    public string $token;

    public string $name = '';

    public string $email = '';

    public int $rating = 5;

    public string $comment = '';

    public $photo;

    public bool $privacyAccepted = false;

    public bool $marketingAccepted = false;

    public string $website = '';

    public bool $submitted = false;

    public bool $invitationUnavailable = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitationUnavailable = ! $this->invitation()?->isUsable();
    }

    public function submit(): void
    {
        if ($this->invitationUnavailable || ! $this->invitation()?->isUsable()) {
            $this->invitationUnavailable = true;

            return;
        }
        if ($this->website !== '') {
            $this->submitted = true;

            return;
        }
        $rateKey = 'testimonial-submit:'.request()->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            throw ValidationException::withMessages(['comment' => 'Has realizado demasiados intentos. Espera antes de volver a intentarlo.']);
        }
        RateLimiter::hit($rateKey, 3600);
        $data = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:10', 'max:1500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'privacyAccepted' => ['accepted'], 'marketingAccepted' => ['boolean'],
        ]);
        $path = $this->photo?->store('testimonials', 'public');
        try {
            $testimonial = DB::transaction(function () use ($data, $path) {
                $invitation = ReviewInvitation::query()->where('token_hash', hash('sha256', $this->token))->lockForUpdate()->first();
                if (! $invitation?->isUsable()) {
                    throw ValidationException::withMessages(['comment' => 'Esta invitación ya no está disponible. Solicita un nuevo código al establecimiento.']);
                }
                $testimonial = $invitation->testimonials()->create([
                    'customer_name' => $data['name'], 'customer_email' => mb_strtolower($data['email']),
                    'rating' => $data['rating'], 'comment' => $data['comment'], 'profile_photo_path' => $path,
                    'source' => 'qr', 'moderation_status' => 'pending', 'is_active' => false,
                    'privacy_consented_at' => now(), 'marketing_consented_at' => $data['marketingAccepted'] ? now() : null,
                    'submission_ip' => request()->ip(),
                ]);
                $invitation->increment('submissions_count');
                $invitation->update(['last_used_at' => now()]);

                return $testimonial;
            });
            User::role('admin')->where('is_active', true)->each(
                fn (User $admin) => $admin->notify(new NewTestimonialNotification($testimonial))
            );
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }
        $this->submitted = true;
        $this->reset(['name', 'email', 'comment', 'photo', 'privacyAccepted', 'marketingAccepted']);
    }

    public function render()
    {
        return view('livewire.public.submit-testimonial')
            ->layout('layouts.public', ['title' => 'Comparte tu experiencia']);
    }

    private function invitation(): ?ReviewInvitation
    {
        return ReviewInvitation::query()->where('token_hash', hash('sha256', $this->token))->first();
    }
}
