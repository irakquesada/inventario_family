<?php

namespace App\Livewire\Inventory;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InitializeLotTracking;
use App\Services\Inventory\LotStock;
use App\Services\Inventory\ProductCsv;
use App\Services\Inventory\RecordStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ProductsIndex extends Component
{
    use WithFileUploads, WithPagination;

    #[On('inventory-changed')]
    public function refreshInventory(): void {}

    #[Url]
    public string $search = '';

    #[Url]
    public bool $lowStock = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $sku = '';

    public string $barcode = '';

    public ?int $categoryId = null;

    public ?int $brandId = null;

    public string $unit = 'unidad';

    public string $cost = '';

    public string $price = '';

    public string $minimumStock = '0';

    public string $description = '';

    public bool $isActive = true;

    public bool $isSellable = false;

    public bool $featuredOnLanding = false;

    public string $existingLandingImagePath = '';

    public $landingImage;

    public bool $notifyLowStock = false;

    public bool $tracksLots = false;

    public bool $originallyTracksLots = false;

    public string $existingQuantity = '0';

    public bool $requiresExpiry = false;

    public string $expiryWarningDays = '7';

    public string $initialQuantity = '0';

    public ?int $initialWarehouseId = null;

    public string $initialReason = 'Stock inicial';

    public string $initialLotNumber = '';

    public string $initialExpiryDate = '';

    public $importFile;

    public function mount(): void
    {
        $this->initialWarehouseId = Warehouse::query()->where('is_active', true)->value('id');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLowStock(): void
    {
        $this->resetPage();
    }

    public function toggleLowStock(): void
    {
        $this->lowStock = ! $this->lowStock;
        $this->resetPage();
    }

    public function import(ProductCsv $csv): void
    {
        $this->ensureCanManage();
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $result = $csv->import($this->importFile->getRealPath());
        $this->reset('importFile');
        $this->resetPage();
        session()->flash('status', "Importación completada: {$result['created']} creados y {$result['updated']} actualizados.");
    }

    public function save(RecordStockMovement $recorder, LotStock $lotStock, InitializeLotTracking $initializer): void
    {
        $this->ensureCanManage();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($this->editingId)],
            'barcode' => ['nullable', 'string', 'max:80', Rule::unique('products', 'barcode')->ignore($this->editingId)],
            'categoryId' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'brandId' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'unit' => ['required', 'string', 'max:20'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'minimumStock' => ['required', 'numeric', 'min:0'],
            'notifyLowStock' => ['boolean'],
            'tracksLots' => ['boolean'],
            'requiresExpiry' => ['boolean'],
            'expiryWarningDays' => ['required', 'integer', 'min:0', 'max:3650'],
            'initialQuantity' => ['nullable', 'numeric', 'min:0'],
            'initialWarehouseId' => [
                Rule::requiredIf($this->editingId === null && (float) $this->initialQuantity > 0),
                'nullable', 'integer', Rule::exists('warehouses', 'id'),
            ],
            'initialReason' => [
                Rule::requiredIf($this->editingId === null && (float) $this->initialQuantity > 0),
                'nullable', 'string', 'min:3', 'max:255',
            ],
            'initialLotNumber' => [
                Rule::requiredIf(
                    ($this->editingId === null && $this->tracksLots && (float) $this->initialQuantity > 0)
                    || ($this->editingId !== null && $this->tracksLots && ! $this->originallyTracksLots && (float) $this->existingQuantity > 0)
                ),
                'nullable', 'string', 'max:100',
            ],
            'initialExpiryDate' => [
                Rule::requiredIf(
                    ($this->editingId === null && $this->requiresExpiry && (float) $this->initialQuantity > 0)
                    || ($this->editingId !== null && $this->requiresExpiry && ! $this->originallyTracksLots && (float) $this->existingQuantity > 0)
                ),
                'nullable', 'date',
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['boolean'],
            'isSellable' => ['boolean'],
            'featuredOnLanding' => ['boolean'],
            'landingImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($data['requiresExpiry'] && ! $data['tracksLots']) {
            throw ValidationException::withMessages(['requiresExpiry' => 'Activa primero el control por lotes.']);
        }
        if ($data['featuredOnLanding'] && Product::query()->where('featured_on_landing', true)->when($this->editingId, fn ($query) => $query->where('id', '!=', $this->editingId))->count() >= 8) {
            throw ValidationException::withMessages(['featuredOnLanding' => 'La landing admite un máximo de 8 productos destacados.']);
        }

        $isCreating = $this->editingId === null;

        $needsLotInitialization = false;
        if (! $isCreating) {
            $existing = Product::query()->withSum('balances', 'quantity')->withSum('lots', 'quantity')->findOrFail($this->editingId);
            $balance = round((float) ($existing->balances_sum_quantity ?? 0), 3);
            $lotBalance = round((float) ($existing->lots_sum_quantity ?? 0), 3);
            if ($data['tracksLots'] && ! $existing->tracks_lots && $balance !== $lotBalance) {
                if ($lotBalance !== 0.0) {
                    throw ValidationException::withMessages(['tracksLots' => 'Las cantidades por lote no coinciden con el saldo actual.']);
                }
                $needsLotInitialization = $balance > 0;
            }
            if (! $data['tracksLots'] && $existing->tracks_lots && $lotBalance !== 0.0) {
                throw ValidationException::withMessages(['tracksLots' => 'No se puede desactivar mientras existan cantidades en lotes.']);
            }
        }

        $landingImagePath = $this->landingImage
            ? $this->landingImage->store('products', 'public')
            : ($this->existingLandingImagePath ?: null);

        DB::transaction(function () use ($data, $isCreating, $recorder, $lotStock, $initializer, $needsLotInitialization, $landingImagePath): void {
            $product = Product::query()->updateOrCreate(['id' => $this->editingId], [
                'name' => $data['name'],
                'slug' => str($data['name'])->slug().($this->editingId ? '-'.$this->editingId : '-'.str()->lower(str()->random(6))),
                'sku' => str($data['sku'])->upper(),
                'barcode' => $data['barcode'] ?: null,
                'category_id' => $data['categoryId'],
                'brand_id' => $data['brandId'],
                'unit' => $data['unit'],
                'cost' => $data['cost'] === '' ? null : $data['cost'],
                'price' => $data['price'] === '' ? null : $data['price'],
                'minimum_stock' => $data['minimumStock'],
                'notify_low_stock' => $data['notifyLowStock'],
                'tracks_lots' => $data['tracksLots'],
                'requires_expiry' => $data['requiresExpiry'],
                'expiry_warning_days' => $data['expiryWarningDays'],
                'description' => $data['description'],
                'is_active' => $data['isActive'],
                'is_sellable' => $data['isSellable'],
                'featured_on_landing' => $data['featuredOnLanding'],
                'landing_image_path' => $landingImagePath,
            ]);

            if ($isCreating && (float) $data['initialQuantity'] > 0) {
                abort_unless(auth()->user()?->can('inventory.adjust'), 403);
                $warehouse = Warehouse::query()->findOrFail($data['initialWarehouseId']);
                if ($product->tracks_lots) {
                    $lotStock->receive(
                        $product, $warehouse, 'opening_balance', (float) $data['initialQuantity'],
                        auth()->user(), $data['initialReason'], $data['initialLotNumber'],
                        $data['initialExpiryDate'] ?: null,
                    );
                } else {
                    $recorder->handle(
                        $product, $warehouse, 'opening_balance', (float) $data['initialQuantity'],
                        auth()->user(), $data['initialReason'],
                    );
                }
            }

            if ($needsLotInitialization) {
                $initializer->handle(
                    $product, auth()->user(), $data['initialLotNumber'], $data['initialExpiryDate'] ?: null,
                );
            }
        });

        $this->resetForm();
        session()->flash('status', 'Producto guardado correctamente.');
    }

    public function edit(int $id): void
    {
        $this->ensureCanManage();
        $product = Product::query()->findOrFail($id);
        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode ?? '';
        $this->categoryId = $product->category_id;
        $this->brandId = $product->brand_id;
        $this->unit = $product->unit;
        $this->cost = $product->cost ?? '';
        $this->price = $product->price ?? '';
        $this->minimumStock = $product->minimum_stock;
        $this->notifyLowStock = $product->notify_low_stock;
        $this->tracksLots = $product->tracks_lots;
        $this->originallyTracksLots = $product->tracks_lots;
        $this->existingQuantity = (string) $product->balances()->sum('quantity');
        $this->requiresExpiry = $product->requires_expiry;
        $this->expiryWarningDays = (string) $product->expiry_warning_days;
        $this->description = $product->description ?? '';
        $this->isActive = $product->is_active;
        $this->isSellable = $product->is_sellable;
        $this->featuredOnLanding = $product->featured_on_landing;
        $this->existingLandingImagePath = $product->landing_image_path ?? '';
        $this->landingImage = null;
    }

    public function delete(int $id): void
    {
        $this->ensureCanManage();
        $product = Product::query()->findOrFail($id);

        if ($product->movements()->exists() || $product->balances()->where('quantity', '!=', 0)->exists() || $product->purchaseOrderLines()->exists()) {
            $this->addError('delete', 'No se puede eliminar un producto con movimientos, existencias u órdenes de compra. Desactívalo.');

            return;
        }

        $product->balances()->delete();
        $product->delete();
        $this->resetForm();
        session()->flash('status', 'Producto eliminado.');
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'sku', 'barcode', 'categoryId', 'brandId',
            'cost', 'price', 'description',
        ]);
        $this->unit = 'unidad';
        $this->minimumStock = '0';
        $this->isActive = true;
        $this->isSellable = false;
        $this->featuredOnLanding = false;
        $this->existingLandingImagePath = '';
        $this->landingImage = null;
        $this->notifyLowStock = false;
        $this->tracksLots = false;
        $this->originallyTracksLots = false;
        $this->existingQuantity = '0';
        $this->requiresExpiry = false;
        $this->expiryWarningDays = '7';
        $this->initialQuantity = '0';
        $this->initialReason = 'Stock inicial';
        $this->initialWarehouseId = Warehouse::query()->where('is_active', true)->value('id');
        $this->initialLotNumber = '';
        $this->initialExpiryDate = '';
        $this->resetValidation();
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'brand', 'preferredSupplierReference.supplier'])
            ->withSum('balances', 'quantity')
            ->when($this->search, fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('sku', 'like', '%'.$this->search.'%')
                    ->orWhere('barcode', 'like', '%'.$this->search.'%');
            }))
            ->when($this->lowStock, fn ($query) => $query
                ->where('is_active', true)
                ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inventory_balances WHERE inventory_balances.product_id = products.id) <= products.minimum_stock'))
            ->orderBy('name')
            ->paginate(20);

        $lowStockCount = Product::query()
            ->where('is_active', true)
            ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inventory_balances WHERE inventory_balances.product_id = products.id) <= products.minimum_stock')
            ->count();

        return view('livewire.inventory.products-index', compact('products', 'lowStockCount'))
            ->with([
                'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
                'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
                'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            ])
            ->layout('layouts.app', ['title' => 'Productos']);
    }

    private function ensureCanManage(): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
    }
}
