<?php

namespace App\Livewire\Sales;

use App\Events\SaleChanged;
use App\Models\Sale;
use App\Notifications\OnlineOrderStatusNotification;
use App\Services\Sales\RefundSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
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

    public function advanceOnlineOrder(int $saleId): void
    {
        abort_unless(auth()->user()?->can('sales.manage'), 403);
        $sale = Sale::query()->findOrFail($saleId);
        abort_unless($sale->channel === 'online', 404);
        $next = ['confirmed' => 'preparing', 'preparing' => 'ready', 'ready' => 'completed'][$sale->status] ?? null;
        if (! $next) {
            throw ValidationException::withMessages(['order' => 'El pedido ya no admite ese cambio.']);
        }
        DB::transaction(function () use ($sale, $next): void {
            $previous = $sale->status;
            $sale->update(['status' => $next, 'completed_at' => $next === 'completed' ? now() : $sale->completed_at]);
            $sale->statusHistory()->create(['user_id' => auth()->id(), 'from_status' => $previous, 'to_status' => $next, 'notes' => 'Estado actualizado desde gestión de ventas.']);
        });
        SaleChanged::dispatch($sale->fresh());
        try {
            Notification::route('mail', $sale->customer_email)->notify(new OnlineOrderStatusNotification($sale->fresh('warehouse')));
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el cambio de estado del pedido online.', ['sale_id' => $sale->id, 'exception' => $exception->getMessage()]);
        }
        session()->flash('status', 'Pedido '.$sale->number.' actualizado.');
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
