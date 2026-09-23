<?php

namespace App\Livewire\Admin;

use App\Models\ReviewInvitation;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Str;
use Livewire\Component;

class ReviewInvitations extends Component
{
    public string $label = '';

    public string $expiresInDays = '7';

    public string $maxSubmissions = '1';

    public ?string $generatedUrl = null;

    public ?string $qrDataUri = null;

    public function generate(): void
    {
        $this->authorizeContent();
        $data = $this->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'expiresInDays' => ['required', 'integer', 'min:1', 'max:90'],
            'maxSubmissions' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $token = Str::random(64);
        ReviewInvitation::query()->create([
            'token_hash' => hash('sha256', $token), 'label' => $data['label'] ?: null,
            'created_by' => auth()->id(), 'max_submissions' => $data['maxSubmissions'],
            'expires_at' => now()->addDays((int) $data['expiresInDays']),
        ]);
        $this->generatedUrl = route('public.testimonials.submit', ['token' => $token]);
        $result = (new Builder(
            writer: new SvgWriter, data: $this->generatedUrl, encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 360, margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();
        $this->qrDataUri = $result->getDataUri();
        $this->reset(['label']);
    }

    public function revoke(int $id): void
    {
        $this->authorizeContent();
        ReviewInvitation::query()->findOrFail($id)->update(['revoked_at' => now()]);
    }

    public function render()
    {
        return view('livewire.admin.review-invitations', [
            'invitations' => ReviewInvitation::query()->with('creator')->latest()->limit(20)->get(),
        ]);
    }

    private function authorizeContent(): void
    {
        abort_unless(auth()->user()?->can('content.manage'), 403);
    }
}
