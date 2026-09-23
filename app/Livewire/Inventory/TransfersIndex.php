<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\TransferStock;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TransfersIndex extends Component
{
    use WithPagination;

    #[On('inventory-changed')]
    public function refreshInventory(): void {}

    #[Url]
    public string $search = '';

    #[Url(as: 'product')]
    public ?int $selectedProductId = null;

    public ?int $fromWarehouseId = null;

    public ?int $toWarehouseId = null;

    public string $quantity = '';

    public string $reason = '';

    public function mount(): void
    {
        $ids = Warehouse::query()->where('is_active', true)->orderBy('name')->limit(2)->pluck('id');
        $this->fromWarehouseId = $ids->get(0);
        $this->toWarehouseId = $ids->get(1);
    }

    public function updatedSearch(): void
    {
        $this->resetPage('productsPage');
    }

    public function selectProduct(int $productId): void
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);
        $this->selectedProductId = Product::query()->where('is_active', true)->findOrFail($productId)->id;
        $this->reset(['quantity', 'reason']);
        $this->resetValidation();
    }

    public function cancelSelection(): void
    {
        $this->reset(['selectedProductId', 'quantity', 'reason']);
        $this->resetValidation();
    }

    public function swapWarehouses(): void
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);
        [$this->fromWarehouseId, $this->toWarehouseId] = [$this->toWarehouseId, $this->fromWarehouseId];
        $this->resetValidation();
    }

    public function save(TransferStock $transfer): void
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);

        $data = $this->validate([
            'selectedProductId' => ['required', 'integer', Rule::exists('products', 'id')],
            'fromWarehouseId' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'toWarehouseId' => ['required', 'integer', Rule::exists('warehouses', 'id'), Rule::notIn([$this->fromWarehouseId])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $transfer->handle(
            Product::query()->findOrFail($data['selectedProductId']),
            Warehouse::query()->findOrFail($data['fromWarehouseId']),
            Warehouse::query()->findOrFail($data['toWarehouseId']),
            (float) $data['quantity'],
            auth()->user(),
            $data['reason'],
        );

        $this->reset(['quantity', 'reason']);
        $this->resetPage('historyPage');
        session()->flash('status', 'Transferencia completada correctamente.');
    }

    public function render()
    {
        $selectedProduct = $this->selectedProductId ? Product::query()->find($this->selectedProductId) : null;
        $sourceBalance = $selectedProduct && $this->fromWarehouseId
            ? (float) InventoryBalance::query()
                ->where('product_id', $selectedProduct->id)
                ->where('warehouse_id', $this->fromWarehouseId)
                ->value('quantity')
            : 0;

        return view('livewire.inventory.transfers-index', [
            'products' => Product::query()
                ->withSum('balances', 'quantity')
                ->where('is_active', true)
                ->when($this->search, fn ($query) => $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('sku', 'like', '%'.$this->search.'%')
                        ->orWhere('barcode', 'like', '%'.$this->search.'%');
                }))
                ->orderBy('name')
                ->paginate(10, pageName: 'productsPage'),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'warehouseNames' => Warehouse::query()->pluck('name', 'id'),
            'selectedProduct' => $selectedProduct,
            'sourceBalance' => $sourceBalance,
            'history' => StockMovement::query()
                ->with(['product', 'warehouse', 'user'])
                ->where('type', 'transfer_out')
                ->latest('created_at')
                ->paginate(25, pageName: 'historyPage'),
        ])->layout('layouts.app', ['title' => 'Transferencias']);
    }
}
