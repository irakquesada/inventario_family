<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CategoriesIndex extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $parentId = null;

    public string $description = '';

    public bool $isActive = true;

    public function save(): void
    {
        $this->ensureCanManage();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($this->editingId)],
            'parentId' => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::notIn(array_filter([$this->editingId]))],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
        ]);

        Category::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $data['name'],
                'slug' => str($data['name'])->slug().($this->editingId ? '-'.$this->editingId : '-'.str()->lower(str()->random(6))),
                'parent_id' => $data['parentId'],
                'description' => $data['description'],
                'is_active' => $data['isActive'],
            ],
        );

        $this->resetForm();
        session()->flash('status', 'Categoría guardada correctamente.');
    }

    public function edit(int $id): void
    {
        $this->ensureCanManage();
        $category = Category::query()->findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parentId = $category->parent_id;
        $this->description = $category->description ?? '';
        $this->isActive = $category->is_active;
    }

    public function delete(int $id): void
    {
        $this->ensureCanManage();
        $category = Category::query()->withCount(['children'])->findOrFail($id);

        if ($category->children_count || $category->products()->exists()) {
            $this->addError('delete', 'No se puede eliminar una categoría con subcategorías o productos.');

            return;
        }

        $category->delete();
        $this->resetForm();
        session()->flash('status', 'Categoría eliminada.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'parentId', 'description']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.inventory.categories-index', [
            'categories' => Category::query()->with('parent')->withCount('children')->orderBy('name')->paginate(15),
            'parentOptions' => Category::query()->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Categorías']);
    }

    private function ensureCanManage(): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
    }
}
