<?php

namespace App\Livewire\Inventory;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Inventory\CreatePurchaseOrder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseOrdersIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'active';

    public ?int $supplierId = null;

    public ?int $warehouseId = null;

    public string $expectedAt = '';

    public string $notes = '';

    public array $quantities = [];

    public array $costs = [];

    public function mount(): void
    {
        $this->warehouseId = Warehouse::query()->where('is_active', true)->value('id');
    }

    public function updatedSupplierId(): void
    {
        $this->quantities = [];
        $this->costs = [];
    }

    public function save(CreatePurchaseOrder $creator): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
        $data = $this->validate([
            'supplierId' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'warehouseId' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'expectedAt' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
            'costs.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $lines = collect($data['quantities'] ?? [])->filter(fn ($quantity) => (float) $quantity > 0)->map(fn ($quantity, $productId) => [
            'product_id' => (int) $productId, 'quantity' => $quantity, 'unit_cost' => $data['costs'][$productId] ?? null,
        ])->values()->all();
        $order = $creator->handle(Supplier::query()->findOrFail($data['supplierId']), Warehouse::query()->findOrFail($data['warehouseId']), auth()->user(), $lines, $data['expectedAt'] ?: null, $data['notes'] ?: null);
        $this->redirectRoute('inventory.purchase-orders.show', $order);
    }

    public function render()
    {
        $supplier = $this->supplierId ? Supplier::query()->find($this->supplierId) : null;

        return view('livewire.inventory.purchase-orders-index', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'references' => $supplier?->productReferences()->with('product')->orderBy('id')->get() ?? collect(),
            'orders' => PurchaseOrder::query()->with(['supplier', 'warehouse'])->withCount('lines')
                ->when($this->status === 'active', fn ($q) => $q->whereNotIn('status', ['received', 'cancelled']))
                ->when($this->status !== 'active' && $this->status !== 'all', fn ($q) => $q->where('status', $this->status))
                ->latest()->paginate(20),
        ])->layout('layouts.app', ['title' => 'Órdenes de compra']);
    }
}
