<?php

namespace App\Events;

use App\Models\Rental;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RentalChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Rental $rental) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('rentals')];
    }

    public function broadcastAs(): string
    {
        return 'rentals.changed';
    }

    public function broadcastWith(): array
    {
        return ['rental_id' => $this->rental->id, 'number' => $this->rental->number, 'status' => $this->rental->status];
    }
}
