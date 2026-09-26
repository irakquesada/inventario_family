<?php

namespace App\Livewire\Admin;

use App\Models\PublicAnalyticsEvent;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class AnalyticsDashboard extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->subDays(29)->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    #[On('analytics-recorded')]
    public function refreshAnalytics(): void {}

    public function render()
    {
        $base = PublicAnalyticsEvent::query()->whereBetween('occurred_at', [now()->parse($this->from)->startOfDay(), now()->parse($this->to)->endOfDay()]);
        $daily = (clone $base)->where('event_type', 'page_view')->selectRaw('DATE(occurred_at) day, COUNT(*) total, COUNT(DISTINCT visitor_hash) visitors')->groupBy('day')->orderBy('day')->get();
        $maxDaily = max(1, (int) $daily->max('total'));

        return view('livewire.admin.analytics-dashboard', [
            'pageViews' => (clone $base)->where('event_type', 'page_view')->count(),
            'visitors' => (clone $base)->distinct('visitor_hash')->count('visitor_hash'),
            'clicks' => (clone $base)->where('event_type', 'click')->count(),
            'productViews' => (clone $base)->where('event_type', 'product_view')->count(),
            'daily' => $daily,
            'maxDaily' => $maxDaily,
            'pages' => (clone $base)->where('event_type', 'page_view')->selectRaw('path, COUNT(*) total')->groupBy('path')->orderByDesc('total')->limit(10)->get(),
            'elements' => (clone $base)->where('event_type', 'click')->selectRaw('label, path, COUNT(*) total')->groupBy('label', 'path')->orderByDesc('total')->limit(10)->get(),
            'products' => (clone $base)->where('event_type', 'product_view')->with('product')->selectRaw('product_id, COUNT(*) total')->groupBy('product_id')->orderByDesc('total')->limit(12)->get(),
            'devices' => (clone $base)->selectRaw('device_type, COUNT(*) total')->groupBy('device_type')->pluck('total', 'device_type'),
            'recent' => (clone $base)->with('product')->latest('occurred_at')->limit(20)->get(),
        ])->layout('layouts.app', ['title' => 'Analítica pública']);
    }
}
