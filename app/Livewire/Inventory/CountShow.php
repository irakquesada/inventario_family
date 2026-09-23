<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Services\Inventory\CompleteInventoryCount;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CountShow extends Component
{
    use WithPagination;

    public int $countId;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all';

    /** @var array<int, string> */
    public array $quantities = [];

    /** @var array<int, string> */
    public array $lotNumbers = [];

    /** @var array<int, string> */
    public array $expiryDates = [];

    public function mount(InventoryCount $inventoryCount): void
    {
        $this->countId = $inventoryCount->id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function saveItem(int $itemId): void
    {
        abort_unless(auth()->user()?->can('inventory.count'), 403);
        $count = InventoryCount::query()->findOrFail($this->countId);
        if (! $count->isOpen()) {
            throw ValidationException::withMessages(['count' => 'Este conteo ya está cerrado.']);
        }

        $data = $this->validate([
            "quantities.{$itemId}" => ['required', 'numeric', 'min:0', 'max:99999999999'],
            "lotNumbers.{$itemId}" => ['nullable', 'string', 'max:100'],
            "expiryDates.{$itemId}" => ['nullable', 'date'],
        ], attributes: ["quantities.{$itemId}" => 'cantidad contada']);

        $item = $count->items()->whereKey($itemId)->firstOrFail();
        $item->update([
            'counted_quantity' => round((float) $data['quantities'][$itemId], 3),
            'counted_by' => auth()->id(),
            'counted_at' => now(),
            'reconciliation_lot_number' => $data['lotNumbers'][$itemId] ?: null,
            'reconciliation_expiry_date' => $data['expiryDates'][$itemId] ?: null,
        ]);

        session()->flash('item-status', 'Cantidad guardada.');
    }

    public function setZero(int $itemId): void
    {
        $this->quantities[$itemId] = '0';
        $this->saveItem($itemId);
    }

    public function complete(CompleteInventoryCount $completer)
    {
        abort_unless(auth()->user()?->can('inventory.count.approve'), 403);
        $completer->handle(InventoryCount::query()->findOrFail($this->countId), auth()->user());
        session()->flash('status', 'Conteo conciliado. Las diferencias se registraron como movimientos auditables.');

        return $this->redirectRoute('inventory.counts.show', ['inventoryCount' => $this->countId], navigate: true);
    }

    public function render()
    {
        $count = InventoryCount::query()
            ->with(['warehouse', 'creator', 'completer'])
            ->withCount([
                'items',
                'items as counted_items_count' => fn ($query) => $query->whereNotNull('counted_quantity'),
                'items as differences_count' => fn ($query) => $query->whereNotNull('counted_quantity')->whereColumn('counted_quantity', '!=', 'expected_quantity'),
            ])
            ->findOrFail($this->countId);

        $items = InventoryCountItem::query()
            ->with(['product.category', 'counter'])
            ->whereBelongsTo($count)
            ->when($this->search, fn ($query) => $query->whereHas('product', function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('sku', 'like', '%'.$this->search.'%')
                    ->orWhere('barcode', 'like', '%'.$this->search.'%');
            }))
            ->when($this->filter === 'pending', fn ($query) => $query->whereNull('counted_quantity'))
            ->when($this->filter === 'differences', fn ($query) => $query->whereNotNull('counted_quantity')->whereColumn('counted_quantity', '!=', 'expected_quantity'))
            ->orderByRaw('counted_quantity IS NOT NULL')
            ->orderBy('product_id')
            ->paginate(20);

        foreach ($items as $item) {
            if (! array_key_exists($item->id, $this->quantities)) {
                $this->quantities[$item->id] = $item->counted_quantity ?? '';
                $this->lotNumbers[$item->id] = $item->reconciliation_lot_number ?? '';
                $this->expiryDates[$item->id] = $item->reconciliation_expiry_date?->toDateString() ?? '';
            }
        }

        return view('livewire.inventory.count-show', compact('count', 'items'))
            ->layout('layouts.app', ['title' => 'Conteo '.$count->reference]);
    }
}
