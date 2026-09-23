<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\LotStock;
use App\Services\Inventory\RecordStockMovement;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MovementsIndex extends Component
{
    use WithPagination;

    #[On('inventory-changed')]
    public function refreshInventory(): void {}

    #[Url]
    public string $search = '';

    #[Url(as: 'product')]
    public ?int $selectedProductId = null;

    #[Url]
    public string $type = 'receipt';

    public ?int $warehouseId = null;

    public string $quantity = '';

    public string $reason = '';

    public string $lotNumber = '';

    public string $expiryDate = '';

    public function mount(): void
    {
        $this->warehouseId = Warehouse::query()->where('is_active', true)->value('id');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('productsPage');
    }

    public function selectProduct(int $productId, string $type = 'receipt'): void
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);
        $this->selectedProductId = Product::query()->where('is_active', true)->findOrFail($productId)->id;
        $this->type = in_array($type, ['receipt', 'issue', 'adjustment'], true) ? $type : 'receipt';
        $this->reset(['quantity', 'reason', 'lotNumber', 'expiryDate']);
        $this->resetValidation();
    }

    public function cancelSelection(): void
    {
        $this->reset(['selectedProductId', 'quantity', 'reason', 'lotNumber', 'expiryDate']);
        $this->type = 'receipt';
        $this->resetValidation();
    }

    public function save(RecordStockMovement $recorder, LotStock $lotStock): void
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);

        $product = $this->selectedProductId ? Product::query()->findOrFail($this->selectedProductId) : null;
        $data = $this->validate([
            'selectedProductId' => ['required', 'integer', Rule::exists('products', 'id')],
            'warehouseId' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'type' => ['required', Rule::in(['receipt', 'issue', 'adjustment'])],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'lotNumber' => [
                Rule::requiredIf($product?->tracks_lots && in_array($this->type, ['receipt', 'adjustment'], true) && (float) $this->quantity > 0),
                'nullable', 'string', 'max:100',
            ],
            'expiryDate' => [
                Rule::requiredIf($product?->requires_expiry && in_array($this->type, ['receipt', 'adjustment'], true) && (float) $this->quantity > 0),
                'nullable', 'date',
            ],
        ]);

        $quantity = (float) $data['quantity'];
        $quantity = match ($data['type']) {
            'receipt' => abs($quantity),
            'issue' => -abs($quantity),
            default => $quantity,
        };

        $product = Product::query()->findOrFail($data['selectedProductId']);
        $warehouse = Warehouse::query()->findOrFail($data['warehouseId']);
        if ($product->tracks_lots) {
            if ($quantity > 0) {
                $lotStock->receive(
                    $product, $warehouse, $data['type'], $quantity, auth()->user(), $data['reason'],
                    $data['lotNumber'], $data['expiryDate'] ?: null,
                );
            } else {
                $lotStock->consume(
                    $product, $warehouse, $data['type'], abs($quantity), auth()->user(), $data['reason'],
                    allowExpired: $data['type'] === 'adjustment',
                );
            }
        } else {
            $recorder->handle($product, $warehouse, $data['type'], $quantity, auth()->user(), $data['reason']);
        }

        $this->reset(['quantity', 'reason', 'lotNumber', 'expiryDate']);
        $this->resetPage('historyPage');
        session()->flash('status', 'Movimiento registrado correctamente.');
    }

    public function render()
    {
        $selectedProduct = $this->selectedProductId
            ? Product::query()->with(['category', 'brand'])->find($this->selectedProductId)
            : null;

        $currentBalance = $selectedProduct && $this->warehouseId
            ? (float) InventoryBalance::query()
                ->where('product_id', $selectedProduct->id)
                ->where('warehouse_id', $this->warehouseId)
                ->value('quantity')
            : 0;

        return view('livewire.inventory.movements-index', [
            'products' => Product::query()
                ->with(['category', 'brand'])
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
            'selectedProduct' => $selectedProduct,
            'currentBalance' => $currentBalance,
            'lots' => $selectedProduct?->tracks_lots && $this->warehouseId
                ? $selectedProduct->lots()->where('warehouse_id', $this->warehouseId)->where('quantity', '>', 0)->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->get()
                : collect(),
            'history' => StockMovement::query()
                ->with(['product', 'warehouse', 'user', 'lotAllocations.lot'])
                ->when($this->selectedProductId, fn ($query) => $query->where('product_id', $this->selectedProductId))
                ->latest('created_at')
                ->paginate(25, pageName: 'historyPage'),
        ])->layout('layouts.app', ['title' => 'Movimientos']);
    }
}
