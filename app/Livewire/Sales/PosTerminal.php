<?php

namespace App\Livewire\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Sales\CreatePosSale;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class PosTerminal extends Component
{
    public string $search = '';

    public ?int $categoryId = null;

    public ?int $warehouseId = null;

    public array $cart = [];

    public string $discount = '0';

    public string $paymentMethod = 'cash';

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $notes = '';

    public ?int $lastSaleId = null;

    public function mount(): void
    {
        $this->warehouseId = Warehouse::query()->where('is_active', true)->value('id');
    }

    #[On('inventory-changed')]
    public function refreshStock(): void {}

    public function updatedSearch(): void
    {
        $value = trim($this->search);
        if (mb_strlen($value) < 3) {
            return;
        }
        $product = Product::query()->where('is_active', true)->where('is_sellable', true)
            ->where(fn ($query) => $query->where('barcode', $value)->orWhere('sku', $value))->first();
        if ($product) {
            $this->add($product->id);
            $this->search = '';
        }
    }

    public function add(int $productId): void
    {
        Product::query()->where('is_active', true)->where('is_sellable', true)->whereNotNull('price')->findOrFail($productId);
        $this->cart[$productId] = round((float) ($this->cart[$productId] ?? 0) + 1, 3);
    }

    public function decrement(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }
        $quantity = round((float) $this->cart[$productId] - 1, 3);
        if ($quantity <= 0) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId] = $quantity;
        }
    }

    public function remove(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->reset(['customerName', 'customerEmail', 'customerPhone', 'notes']);
        $this->discount = '0';
    }

    public function checkout(CreatePosSale $creator): void
    {
        abort_unless(auth()->user()?->can('sales.create'), 403);
        $data = $this->validate([
            'warehouseId' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'paymentMethod' => ['required', Rule::in(['cash', 'card', 'transfer', 'other'])],
            'discount' => ['required', 'numeric', 'min:0'], 'customerName' => ['nullable', 'string', 'max:255'],
            'customerEmail' => ['nullable', 'email', 'max:255'], 'customerPhone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'], 'cart' => ['required', 'array', 'min:1'], 'cart.*' => ['numeric', 'gt:0'],
        ]);
        $sale = $creator->handle(
            Warehouse::query()->findOrFail($data['warehouseId']), auth()->user(), $data['cart'], $data['paymentMethod'],
            (float) $data['discount'], ['name' => $data['customerName'], 'email' => $data['customerEmail'], 'phone' => $data['customerPhone']], $data['notes'],
        );
        $this->clearCart();
        $this->lastSaleId = $sale->id;
        session()->flash('status', "Venta {$sale->number} completada correctamente.");
    }

    public function render()
    {
        $products = Product::query()->with('category')->where('is_active', true)->where('is_sellable', true)->whereNotNull('price')
            ->when($this->categoryId, fn ($query) => $query->where('category_id', $this->categoryId))
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')->orWhere('barcode', 'like', '%'.$this->search.'%')))
            ->withSum(['balances as selected_stock' => fn ($query) => $query->where('warehouse_id', $this->warehouseId)], 'quantity')->orderBy('name')->limit(60)->get();
        $cartProducts = Product::query()->whereKey(array_keys($this->cart))->get()->keyBy('id');
        $cartRows = collect($this->cart)->map(function ($quantity, $id) use ($cartProducts) {
            $product = $cartProducts->get((int) $id);

            return $product ? ['product' => $product, 'quantity' => (float) $quantity, 'total' => round((float) $product->price * (float) $quantity, 2)] : null;
        })->filter()->values();
        $subtotal = round((float) $cartRows->sum('total'), 2);

        return view('livewire.sales.pos-terminal', [
            'products' => $products, 'cartRows' => $cartRows, 'subtotal' => $subtotal,
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Punto de venta']);
    }
}
