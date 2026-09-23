<?php

namespace App\Livewire\Inventory;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierProducts extends Component
{
    use WithPagination;

    public int $supplierId;

    #[Url]
    public string $search = '';

    public ?int $editingId = null;

    public ?int $productId = null;

    public string $supplierSku = '';

    public string $unitCost = '';

    public string $minimumOrderQuantity = '1';

    public string $leadTimeDays = '';

    public bool $isPreferred = false;

    public string $notes = '';

    public function mount(Supplier $supplier): void
    {
        $this->supplierId = $supplier->id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage('productsPage');
    }

    public function selectProduct(int $productId): void
    {
        $this->authorizeManagement();
        $this->productId = Product::query()->where('is_active', true)->findOrFail($productId)->id;
        $this->editingId = null;
        $this->resetReferenceFields();
    }

    public function save(): void
    {
        $this->authorizeManagement();
        $data = $this->validate([
            'productId' => [
                'required', 'integer', Rule::exists('products', 'id'),
                Rule::unique('product_supplier', 'product_id')
                    ->where(fn ($query) => $query->where('supplier_id', $this->supplierId))
                    ->ignore($this->editingId),
            ],
            'supplierSku' => ['nullable', 'string', 'max:100'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'minimumOrderQuantity' => ['required', 'numeric', 'gt:0'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'isPreferred' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data): void {
            if ($data['isPreferred']) {
                ProductSupplier::query()->where('product_id', $data['productId'])->update(['is_preferred' => false]);
            }

            ProductSupplier::query()->updateOrCreate(['id' => $this->editingId], [
                'supplier_id' => $this->supplierId,
                'product_id' => $data['productId'],
                'supplier_sku' => $data['supplierSku'] ?: null,
                'unit_cost' => $data['unitCost'] === '' ? null : $data['unitCost'],
                'minimum_order_quantity' => $data['minimumOrderQuantity'],
                'lead_time_days' => $data['leadTimeDays'] === '' ? null : $data['leadTimeDays'],
                'is_preferred' => $data['isPreferred'],
                'notes' => $data['notes'] ?: null,
            ]);
        });

        $this->cancel();
        session()->flash('status', 'Referencia de compra guardada.');
    }

    public function edit(int $referenceId): void
    {
        $this->authorizeManagement();
        $reference = ProductSupplier::query()->where('supplier_id', $this->supplierId)->findOrFail($referenceId);
        $this->editingId = $reference->id;
        $this->productId = $reference->product_id;
        $this->supplierSku = $reference->supplier_sku ?? '';
        $this->unitCost = $reference->unit_cost ?? '';
        $this->minimumOrderQuantity = $reference->minimum_order_quantity;
        $this->leadTimeDays = $reference->lead_time_days ?? '';
        $this->isPreferred = $reference->is_preferred;
        $this->notes = $reference->notes ?? '';
        $this->resetValidation();
    }

    public function delete(int $referenceId): void
    {
        $this->authorizeManagement();
        ProductSupplier::query()->where('supplier_id', $this->supplierId)->findOrFail($referenceId)->delete();
        $this->cancel();
        session()->flash('status', 'Referencia de compra eliminada.');
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'productId']);
        $this->resetReferenceFields();
        $this->resetValidation();
    }

    private function resetReferenceFields(): void
    {
        $this->reset(['supplierSku', 'unitCost', 'leadTimeDays', 'notes']);
        $this->minimumOrderQuantity = '1';
        $this->isPreferred = false;
    }

    public function render()
    {
        $supplier = Supplier::query()->findOrFail($this->supplierId);

        return view('livewire.inventory.supplier-products', [
            'supplier' => $supplier,
            'references' => ProductSupplier::query()
                ->with('product.category')
                ->whereBelongsTo($supplier)
                ->orderByDesc('is_preferred')
                ->orderBy('id')
                ->paginate(20, pageName: 'referencesPage'),
            'products' => Product::query()
                ->where('is_active', true)
                ->when($this->search, fn ($query) => $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('sku', 'like', '%'.$this->search.'%')
                        ->orWhere('barcode', 'like', '%'.$this->search.'%');
                }))
                ->orderBy('name')
                ->paginate(10, pageName: 'productsPage'),
            'selectedProduct' => $this->productId ? Product::query()->find($this->productId) : null,
        ])->layout('layouts.app', ['title' => 'Productos de '.$supplier->name]);
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
    }
}
