<?php

namespace App\Livewire\Inventory;

use App\Models\Brand;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class BrandsIndex extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public function save(): void
    {
        $this->ensureCanManage();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
        ]);

        Brand::query()->updateOrCreate(['id' => $this->editingId], [
            'name' => $data['name'],
            'slug' => str($data['name'])->slug().($this->editingId ? '-'.$this->editingId : '-'.str()->lower(str()->random(6))),
            'description' => $data['description'],
            'is_active' => $data['isActive'],
        ]);
        $this->resetForm();
        session()->flash('status', 'Marca guardada correctamente.');
    }

    public function edit(int $id): void
    {
        $this->ensureCanManage();
        $brand = Brand::query()->findOrFail($id);
        $this->editingId = $brand->id;
        $this->name = $brand->name;
        $this->description = $brand->description ?? '';
        $this->isActive = $brand->is_active;
    }

    public function delete(int $id): void
    {
        $this->ensureCanManage();
        $brand = Brand::query()->findOrFail($id);
        if ($brand->products()->exists()) {
            $this->addError('delete', 'No se puede eliminar una marca asociada a productos.');

            return;
        }
        $brand->delete();
        $this->resetForm();
        session()->flash('status', 'Marca eliminada.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.inventory.brands-index', [
            'brands' => Brand::query()->withCount('products')->orderBy('name')->paginate(15),
        ])->layout('layouts.app', ['title' => 'Marcas']);
    }

    private function ensureCanManage(): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
    }
}
