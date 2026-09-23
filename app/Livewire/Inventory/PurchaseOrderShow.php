<?php

namespace App\Livewire\Inventory;

use App\Models\PurchaseOrder;
use App\Services\Inventory\ReceivePurchaseOrder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class PurchaseOrderShow extends Component
{
    #[On('inventory-changed')]
    public function refreshInventory(): void
    {
        $this->purchaseOrder->refresh();
    }

    public PurchaseOrder $purchaseOrder;

    public array $receiptLines = [];

    public string $receiptNotes = '';

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->purchaseOrder = $purchaseOrder;
    }

    public function markOrdered(): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
        DB::transaction(function (): void {
            $order = PurchaseOrder::query()->whereKey($this->purchaseOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'draft', 422);
            $order->update(['status' => 'ordered', 'ordered_at' => now(), 'ordered_by' => auth()->id()]);
        });
        $this->purchaseOrder->refresh();
        session()->flash('status', 'Orden marcada como enviada al proveedor.');
    }

    public function cancel(): void
    {
        abort_unless(auth()->user()?->can('inventory.manage'), 403);
        DB::transaction(function (): void {
            $order = PurchaseOrder::query()->whereKey($this->purchaseOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($order->status, ['draft', 'ordered'], true), 422);
            $order->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => auth()->id()]);
        });
        $this->purchaseOrder->refresh();
        session()->flash('status', 'Orden cancelada sin modificar existencias.');
    }

    public function receive(ReceivePurchaseOrder $receiver): void
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);
        $this->validate([
            'receiptLines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'receiptLines.*.lot_number' => ['nullable', 'string', 'max:100'],
            'receiptLines.*.expiry_date' => ['nullable', 'date'],
            'receiptNotes' => ['nullable', 'string', 'max:5000'],
        ]);
        $receipt = $receiver->handle($this->purchaseOrder, auth()->user(), $this->receiptLines, $this->receiptNotes ?: null);
        $this->reset(['receiptLines', 'receiptNotes']);
        $this->purchaseOrder->refresh();
        session()->flash('status', "Recepción {$receipt->number} registrada correctamente.");
    }

    public function render()
    {
        $this->purchaseOrder->load(['supplier', 'warehouse', 'creator', 'lines.product', 'receipts.receiver', 'receipts.lines.orderLine.product']);

        return view('livewire.inventory.purchase-order-show')->layout('layouts.app', ['title' => $this->purchaseOrder->number]);
    }
}
