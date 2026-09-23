<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    public function enabled(): bool
    {
        return (bool) config('services.turnstile.enabled');
    }

    public function verify(?string $token, ?string $ipAddress): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token) || blank(config('services.turnstile.secret_key'))) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->retry(2, 200, throw: false)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]));
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
