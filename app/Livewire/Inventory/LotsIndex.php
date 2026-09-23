<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryLot;
use App\Models\Warehouse;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LotsIndex extends Component
{
    use WithPagination;

    #[On('inventory-changed')]
    public function refreshInventory(): void {}

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'available';

    #[Url]
    public ?int $warehouseId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.inventory.lots-index', [
            'lots' => InventoryLot::query()
                ->with(['product', 'warehouse'])
                ->when($this->warehouseId, fn ($query) => $query->where('warehouse_id', $this->warehouseId))
                ->when($this->status === 'available', fn ($query) => $query->where('quantity', '>', 0))
                ->when($this->status === 'expired', fn ($query) => $query->where('quantity', '>', 0)->whereDate('expiry_date', '<', today()))
                ->when($this->status === 'expiring', fn ($query) => $query->where('quantity', '>', 0)->whereBetween('expiry_date', [today(), today()->addDays(30)]))
                ->when($this->status === 'empty', fn ($query) => $query->where('quantity', 0))
                ->when($this->search, fn ($query) => $query->where(function ($query): void {
                    $query->where('lot_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('product', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%'));
                }))
                ->orderByRaw('expiry_date IS NULL')
                ->orderBy('expiry_date')
                ->paginate(30),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Lotes y caducidad']);
    }
}
