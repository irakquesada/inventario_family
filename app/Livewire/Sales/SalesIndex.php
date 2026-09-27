<?php

namespace App\Livewire\Sales;

use App\Models\Sale;
use App\Services\Sales\RefundSale;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SalesIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public ?int $refundingSaleId = null;

    public string $refundReason = '';

    #[On('sales-changed')]
    public function refreshSales(): void {}

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function beginRefund(int $saleId): void
    {
        abort_unless(auth()->user()?->can('sales.refund'), 403);
        $this->refundingSaleId = Sale::query()->findOrFail($saleId)->id;
        $this->refundReason = '';
    }

    public function cancelRefund(): void
    {
        $this->reset(['refundingSaleId', 'refundReason']);
    }

    public function refund(RefundSale $service): void
    {
        abort_unless(auth()->user()?->can('sales.refund'), 403);
        $service->handle(Sale::query()->findOrFail($this->refundingSaleId), auth()->user(), $this->refundReason);
        $this->cancelRefund();
        session()->flash('status', 'Venta reembolsada y existencias restauradas correctamente.');
    }

    public function render()
    {
        $query = Sale::query()->with(['warehouse', 'user'])->withCount('items')
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query->where('number', 'like', '%'.$this->search.'%')->orWhere('customer_name', 'like', '%'.$this->search.'%')))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo));

        return view('livewire.sales.sales-index', [
            'sales' => (clone $query)->latest()->paginate(20),
            'summary' => ['count' => (clone $query)->count(), 'total' => (float) (clone $query)->whereNotIn('status', ['cancelled', 'refunded'])->sum('total')],
        ])->layout('layouts.app', ['title' => 'Ventas']);
    }
}
