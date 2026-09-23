<?php

namespace App\Console\Commands;

use App\Models\InventoryLot;
use App\Models\User;
use App\Notifications\ExpiringLotNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class NotifyExpiringLots extends Command
{
    protected $signature = 'inventory:notify-expiring-lots';

    protected $description = 'Notifica una sola vez los lotes dentro de su umbral de caducidad';

    public function handle(): int
    {
        $notified = 0;

        InventoryLot::query()
            ->with(['product', 'warehouse'])
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereNull('expiry_notified_at')
            ->orderBy('id')
            ->chunkById(100, function ($lots) use (&$notified): void {
                foreach ($lots as $lot) {
                    if ($lot->expiry_date->gt(today()->addDays($lot->product->expiry_warning_days))) {
                        continue;
                    }

                    DB::transaction(function () use ($lot, &$notified): void {
                        $lockedLot = InventoryLot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
                        if ($lockedLot->expiry_notified_at || (float) $lockedLot->quantity <= 0) {
                            return;
                        }

                        $admins = User::role('admin')->where('is_active', true)->get();
                        if ($admins->isEmpty()) {
                            return;
                        }
                        Notification::send($admins, new ExpiringLotNotification($lot));
                        $lockedLot->update(['expiry_notified_at' => now()]);
                        $notified++;
                    });
                }
            });

        $this->info("Alertas de caducidad emitidas: {$notified}");

        return self::SUCCESS;
    }
}
