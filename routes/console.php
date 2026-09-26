<?php

use App\Models\PublicAnalyticsEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:notify-expiring-lots')->dailyAt('08:00')->withoutOverlapping();
Schedule::call(fn () => PublicAnalyticsEvent::query()->where('occurred_at', '<', now()->subMonths(13))->delete())
    ->monthlyOn(1, '03:30')->name('analytics-retention')->withoutOverlapping();
