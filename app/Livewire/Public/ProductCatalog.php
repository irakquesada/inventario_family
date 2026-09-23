<?php

namespace App\Livewire\Public;

use App\Models\Category;
use App\Models\Product;
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

    public function render()
    {
        $products = Product::query()
            ->with(['category.parent', 'brand'])
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

        return view('livewire.public.product-catalog', compact('products', 'categories'))
            ->layout('layouts.public', ['title' => 'Carta y productos']);
    }
}
