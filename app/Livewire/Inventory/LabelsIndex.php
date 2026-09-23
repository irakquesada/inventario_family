<?php

namespace App\Livewire\Inventory;

use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class LabelsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    /** @var array<int, string|int> */
    public array $selectedProductIds = [];

    public int $columns = 3;

    public int $copies = 1;

    public bool $showPrice = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->selectedProductIds = [];
    }

    public function selectPage(array $ids): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
        $this->selectedProductIds = array_values(array_unique([...$this->selectedProductIds, ...$ids]));
    }

    public function download()
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
        $data = $this->validate([
            'selectedProductIds' => ['required', 'array', 'min:1', 'max:100'],
            'selectedProductIds.*' => ['integer', Rule::exists('products', 'id')],
            'columns' => ['required', Rule::in([2, 3])],
            'copies' => ['required', 'integer', 'min:1', 'max:10'],
            'showPrice' => ['boolean'],
        ]);

        $products = Product::query()->whereKey($data['selectedProductIds'])->orderBy('name')->get();
        $renderer = new SvgRenderer;
        $type = new TypeCode128;
        $labels = collect();

        foreach ($products as $product) {
            $value = $product->barcode ?: $product->sku;
            $barcode = $type->getBarcode($value);
            $svg = $renderer->render($barcode, max(180, $barcode->getWidth() * 2), 48);

            for ($copy = 0; $copy < $data['copies']; $copy++) {
                $labels->push([
                    'product' => $product,
                    'value' => $value,
                    'image' => 'data:image/svg+xml;base64,'.base64_encode($svg),
                ]);
            }
        }

        $pdf = Pdf::loadView('pdf.product-labels', [
            'rows' => $labels->chunk($data['columns']),
            'columns' => $data['columns'],
            'showPrice' => $data['showPrice'],
        ])->setPaper('a4');

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'etiquetas-'.now()->format('Y-m-d-His').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function render()
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($this->search, fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('sku', 'like', '%'.$this->search.'%')
                    ->orWhere('barcode', 'like', '%'.$this->search.'%');
            }))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.inventory.labels-index', compact('products'))
            ->layout('layouts.app', ['title' => 'Etiquetas']);
    }
}
