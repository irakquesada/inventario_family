<?php

namespace App\Livewire;

use App\Models\InventoryCount;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\ModuleManager;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    #[On('inventory-changed')]
    public function refreshInventory(): void {}

    public function markNotificationRead(string $id): void
    {
        auth()->user()->unreadNotifications()->whereKey($id)->firstOrFail()->markAsRead();
    }

    public function render()
    {
        $modules = app(ModuleManager::class);

        return view('livewire.dashboard', [
            'moduleManager' => $modules,
            'productsCount' => $modules->enabled('inventory') ? Product::query()->count() : 0,
            'warehousesCount' => $modules->enabled('inventory') ? Warehouse::query()->count() : 0,
            'movementsCount' => $modules->enabled('inventory') ? StockMovement::query()->count() : 0,
            'transfersCount' => $modules->enabled('inventory') ? StockMovement::query()->where('type', 'transfer_out')->count() : 0,
            'pendingPurchaseOrdersCount' => $modules->enabled('inventory') && auth()->user()->can('inventory.manage')
                ? PurchaseOrder::query()->whereIn('status', ['draft', 'ordered', 'partially_received'])->count()
                : 0,
            'todayReservationsCount' => $modules->enabled('reservations') && auth()->user()->can('reservations.view')
                ? Reservation::query()->whereDate('starts_at', today())->whereNotIn('status', ['cancelled', 'no_show'])->count()
                : 0,
            'alerts' => auth()->user()->unreadNotifications()->latest()->limit(10)->get(),
            'openCounts' => $modules->enabled('inventory') && auth()->user()->can('inventory.count')
                ? InventoryCount::query()
                    ->with('warehouse')
                    ->withCount([
                        'items',
                        'items as counted_items_count' => fn ($query) => $query->whereNotNull('counted_quantity'),
                    ])
                    ->where('status', 'open')
                    ->oldest('started_at')
                    ->get()
                : collect(),
            'expiringLots' => $modules->enabled('inventory') && auth()->user()->can('inventory.view')
                ? InventoryLot::query()
                    ->with(['product', 'warehouse'])
                    ->where('quantity', '>', 0)
                    ->whereNotNull('expiry_date')
                    ->orderBy('expiry_date')
                    ->get()
                    ->filter(fn ($lot) => $lot->expiry_date->lte(today()->addDays($lot->product->expiry_warning_days)))
                    ->take(10)
                : collect(),
        ])->layout('layouts.app', ['title' => 'Panel']);
    }
}
