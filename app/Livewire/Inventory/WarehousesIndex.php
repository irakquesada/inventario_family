<?php

namespace App\Livewire\Inventory;

use App\Models\Warehouse;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class WarehousesIndex extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $address = '';

    public bool $isActive = true;

    public function save(): void
    {
        $this->ensureCanManage();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('warehouses', 'code')->ignore($this->editingId)],
            'address' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['boolean'],
        ]);

        Warehouse::query()->updateOrCreate(['id' => $this->editingId], [
            'name' => $data['name'],
            'code' => str($data['code'])->upper(),
            'address' => $data['address'],
            'is_active' => $data['isActive'],
        ]);
        $this->resetForm();
        session()->flash('status', 'Almacén guardado correctamente.');
    }

    public function edit(int $id): void
    {
        $this->ensureCanManage();
        $warehouse = Warehouse::query()->findOrFail($id);
        $this->editingId = $warehouse->id;
        $this->name = $warehouse->name;
        $this->code = $warehouse->code;
        $this->address = $warehouse->address ?? '';
        $this->isActive = $warehouse->is_active;
    }

    public function delete(int $id): void
    {
        $this->ensureCanManage();
        $warehouse = Warehouse::query()->findOrFail($id);
        if ($warehouse->balances()->exists()) {
            $this->addError('delete', 'No se puede eliminar un almacén con historial o existencias.');

            return;
        }
        $warehouse->delete();
        $this->resetForm();
        session()->flash('status', 'Almacén eliminado.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'address']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.inventory.warehouses-index', [
            'warehouses' => Warehouse::query()->withCount('balances')->orderBy('name')->paginate(15),
        ])->layout('layouts.app', ['title' => 'Almacenes']);
    }

    private function ensureCanManage(): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
    }
}
