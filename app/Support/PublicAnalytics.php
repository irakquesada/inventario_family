<?php

namespace App\Support;

use App\Events\AnalyticsRecorded;
use App\Models\PublicAnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicAnalytics
{
    public function record(Request $request, string $eventType, ?string $path = null, ?string $label = null, ?int $productId = null): void
    {
        if ($this->isBot($request)) {
            return;
        }

        $userAgent = (string) $request->userAgent();
        $daySalt = now()->format('Y-m-d');
        PublicAnalyticsEvent::query()->create([
            'event_type' => $eventType,
            'path' => $path ? Str::limit($path, 500, '') : null,
            'label' => $label ? Str::limit(strip_tags($label), 255, '') : null,
            'product_id' => $productId,
            'visitor_hash' => hash_hmac('sha256', $daySalt.'|'.$request->ip().'|'.$userAgent, (string) config('app.key')),
            'session_hash' => hash_hmac('sha256', $request->session()->getId(), (string) config('app.key')),
            'referrer_host' => parse_url((string) $request->headers->get('referer'), PHP_URL_HOST) ?: null,
            'device_type' => preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent) ? 'mobile' : 'desktop',
            'occurred_at' => now(),
        ]);

        try {
            AnalyticsRecorded::dispatch();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function isBot(Request $request): bool
    {
        return preg_match('/bot|crawl|spider|slurp|preview|monitor/i', (string) $request->userAgent()) === 1;
    }
}
