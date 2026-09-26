<?php

namespace App\Events;

use App\Models\SiteSetting;
use App\Support\ModuleManager;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LandingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [new Channel('landing')];
    }

    public function broadcastAs(): string
    {
        return 'landing.updated';
    }

    public function broadcastWith(): array
    {
        $settings = SiteSetting::query()->first();

        return [
            'updated_at' => now()->toIso8601String(),
            'reservations_enabled' => app(ModuleManager::class)->enabled('reservations') && ($settings?->reservations_enabled ?? false),
        ];
    }
}
