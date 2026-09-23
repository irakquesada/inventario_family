<?php

namespace App\Events;

use App\Models\StockMovement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public StockMovement $movement) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventory')];
    }

    public function broadcastAs(): string
    {
        return 'inventory.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'movement_id' => $this->movement->id,
            'product_id' => $this->movement->product_id,
            'warehouse_id' => $this->movement->warehouse_id,
            'type' => $this->movement->type,
            'occurred_at' => $this->movement->created_at->toIso8601String(),
        ];
    }
}
