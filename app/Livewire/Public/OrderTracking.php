<?php

namespace App\Livewire\Public;

use App\Models\Sale;
use Livewire\Component;

class OrderTracking extends Component
{
    public Sale $sale;

    public function mount(Sale $sale): void
    {
        abort_unless($sale->channel === 'online', 404);
        $this->sale = $sale->load(['items', 'warehouse']);
    }

    public function render()
    {
        $steps = ['confirmed' => 'Recibido', 'preparing' => 'En preparación', 'ready' => 'Listo para recoger', 'completed' => 'Entregado'];
        $current = array_search($this->sale->status, array_keys($steps), true);

        return view('livewire.public.order-tracking', compact('steps', 'current'))
            ->layout('layouts.public', ['title' => 'Seguimiento de pedido']);
    }
}
