<?php

namespace App\Livewire\Public;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Warehouse;
use App\Services\Sales\CreateOnlineOrder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductCatalog extends Component
{
    use WithPagination;

    #[Url(as: 'buscar')]
    public string $search = '';

    #[Url(as: 'categoria')]
    public string $category = '';

    public array $cart = [];

    public bool $checkoutOpen = false;

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $pickupDate = '';

    public string $pickupTime = '';

    public string $orderNotes = '';

    public bool $privacyAccepted = false;

    public ?string $orderReference = null;

    public ?string $orderTrackingReference = null;

    public function mount(): void
    {
        $this->cart = session()->get('public_store_cart', []);
        $this->pickupDate = today()->addDay()->toDateString();
        $this->pickupTime = '12:00';
    }

    #[On('catalog-updated')]
    public function refreshCatalog(): void
    {
        // Receiving the event or fallback poll re-runs the catalog query.
    }

    #[On('landing-updated')]
    public function refreshStoreSettings(): void
    {
        // Store availability changes share the landing real-time channel.
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'category']);
        $this->resetPage();
    }

    public function addToCart(int $productId): void
    {
        $settings = SiteSetting::query()->firstOrFail();
        abort_unless($settings->online_store_enabled, 404);
        Product::query()->where('is_active', true)->where('is_sellable', true)->where('is_online_sellable', true)->whereNotNull('price')->findOrFail($productId);
        $this->cart[$productId] = min(99, (int) ($this->cart[$productId] ?? 0) + 1);
        $this->persistCart();
    }

    public function decrementCart(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }
        $this->cart[$productId]--;
        if ($this->cart[$productId] <= 0) {
            unset($this->cart[$productId]);
        }
        $this->persistCart();
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
        $this->persistCart();
    }

    public function openCheckout(): void
    {
        if ($this->cart === []) {
            return;
        }
        $this->checkoutOpen = true;
    }

    public function checkout(CreateOnlineOrder $creator): void
    {
        $settings = SiteSetting::query()->firstOrFail();
        abort_unless($settings->online_store_enabled && $settings->online_pickup_warehouse_id, 404);
        $data = $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:40'],
            'pickupDate' => ['required', 'date', 'after_or_equal:today'],
            'pickupTime' => ['required', 'date_format:H:i'],
            'orderNotes' => ['nullable', 'string', 'max:1000'],
            'privacyAccepted' => ['accepted'],
            'cart' => ['required', 'array', 'min:1'],
            'cart.*' => ['integer', 'min:1', 'max:99'],
        ]);
        $sale = $creator->handle(
            Warehouse::query()->findOrFail($settings->online_pickup_warehouse_id), $data['cart'],
            ['name' => $data['customerName'], 'email' => $data['customerEmail'], 'phone' => $data['customerPhone']],
            $data['pickupDate'], $data['pickupTime'], $data['orderNotes'],
        );
        $this->orderReference = $sale->number;
        $this->orderTrackingReference = $sale->reference;
        $this->cart = [];
        session()->forget('public_store_cart');
        $this->checkoutOpen = false;
    }

    public function render()
    {
        $settings = SiteSetting::query()->firstOrFail();
        $storeEnabled = $settings->online_store_enabled && $settings->online_pickup_warehouse_id;
        $pickupWarehouseId = $settings->online_pickup_warehouse_id;
        $products = Product::query()
            ->with(['category.parent', 'brand'])
            ->withSum(['balances as online_stock' => fn ($query) => $query->where('warehouse_id', $pickupWarehouseId)], 'quantity')
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->category !== '', function ($query): void {
                $query->whereHas('category', function ($category): void {
                    $category->where('slug', $this->category)
                        ->orWhereHas('parent', fn ($parent) => $parent->where('slug', $this->category));
                });
            })
            ->orderBy('name')
            ->paginate(12);

        $categories = Category::query()
            ->with('parent')
            ->where('is_active', true)
            ->whereHas('products', fn ($products) => $products->where('is_active', true)->where('is_sellable', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $cartProducts = Product::query()->whereKey(array_keys($this->cart))->get()->keyBy('id');
        $cartRows = collect($this->cart)->map(function ($quantity, $id) use ($cartProducts) {
            $product = $cartProducts->get((int) $id);

            return $product ? ['product' => $product, 'quantity' => (int) $quantity, 'total' => round((float) $product->price * (int) $quantity, 2)] : null;
        })->filter()->values();
        $cartTotal = round((float) $cartRows->sum('total'), 2);

        return view('livewire.public.product-catalog', compact('products', 'categories', 'settings', 'storeEnabled', 'cartRows', 'cartTotal'))
            ->layout('layouts.public', ['title' => 'Carta y productos']);
    }

    private function persistCart(): void
    {
        session()->put('public_store_cart', $this->cart);
    }
}
