<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryCount;
use App\Models\Warehouse;
use App\Services\Inventory\CreateInventoryCount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CountsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public ?int $warehouseId = null;

    public string $notes = '';

    public function mount(): void
    {
        $this->warehouseId = Warehouse::query()->where('is_active', true)->value('id');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function create(CreateInventoryCount $creator)
    {
        abort_unless(auth()->user()?->can('inventory.count.approve'), 403);
        $data = $this->validate([
            'warehouseId' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $count = $creator->handle(
            Warehouse::query()->findOrFail($data['warehouseId']),
            auth()->user(),
            $data['notes'] ?: null,
        );

        return $this->redirectRoute('inventory.counts.show', $count, navigate: true);
    }

    public function cancel(int $countId): void
    {
        abort_unless(auth()->user()?->can('inventory.count.approve'), 403);

        DB::transaction(function () use ($countId): void {
            $count = InventoryCount::query()->whereKey($countId)->lockForUpdate()->firstOrFail();
            if (! $count->isOpen()) {
                throw ValidationException::withMessages(['count' => 'Solo se pueden cancelar conteos abiertos.']);
            }
            $count->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);
        });

        session()->flash('status', 'Conteo cancelado. No se modificó ninguna existencia.');
    }

    public function render()
    {
        return view('livewire.inventory.counts-index', [
            'counts' => InventoryCount::query()
                ->with(['warehouse', 'creator', 'completer', 'canceller'])
                ->withCount([
                    'items',
                    'items as counted_items_count' => fn ($query) => $query->whereNotNull('counted_quantity'),
                    'items as differences_count' => fn ($query) => $query->whereNotNull('counted_quantity')->whereColumn('counted_quantity', '!=', 'expected_quantity'),
                ])
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->latest('started_at')
                ->paginate(20),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Conteos físicos']);
    }
}
