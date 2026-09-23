<?php

namespace App\Livewire\Inventory;

use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SuppliersIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $taxId = '';

    public string $contactName = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    public bool $isActive = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $this->authorizeManagement();
        $data = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'taxId' => ['nullable', 'string', 'max:40', Rule::unique('suppliers', 'tax_id')->ignore($this->editingId)],
            'contactName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['boolean'],
        ]);

        Supplier::query()->updateOrCreate(['id' => $this->editingId], [
            'name' => $data['name'],
            'tax_id' => $data['taxId'] ?: null,
            'contact_name' => $data['contactName'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'notes' => $data['notes'] ?: null,
            'is_active' => $data['isActive'],
        ]);

        $this->resetForm();
        session()->flash('status', 'Proveedor guardado correctamente.');
    }

    public function edit(int $supplierId): void
    {
        $this->authorizeManagement();
        $supplier = Supplier::query()->findOrFail($supplierId);
        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->taxId = $supplier->tax_id ?? '';
        $this->contactName = $supplier->contact_name ?? '';
        $this->email = $supplier->email ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->address = $supplier->address ?? '';
        $this->notes = $supplier->notes ?? '';
        $this->isActive = $supplier->is_active;
        $this->resetValidation();
    }

    public function delete(int $supplierId): void
    {
        $this->authorizeManagement();
        $supplier = Supplier::query()->findOrFail($supplierId);

        if ($supplier->productReferences()->exists() || $supplier->purchaseOrders()->exists()) {
            $this->addError('delete', 'No se puede eliminar un proveedor con productos u órdenes vinculadas. Desactívalo.');

            return;
        }

        $supplier->delete();
        $this->resetForm();
        session()->flash('status', 'Proveedor eliminado.');
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'taxId', 'contactName', 'email', 'phone', 'address', 'notes',
        ]);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.inventory.suppliers-index', [
            'suppliers' => Supplier::query()
                ->withCount('products')
                ->when($this->search, fn ($query) => $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('tax_id', 'like', '%'.$this->search.'%')
                        ->orWhere('contact_name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                }))
                ->orderBy('name')
                ->paginate(20),
        ])->layout('layouts.app', ['title' => 'Proveedores']);
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
    }
}
