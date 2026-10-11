<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOnlineOrderNotification extends Notification
{
    use Queueable;

    public function __construct(private Sale $sale) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nuevo pedido online '.$this->sale->number,
            'message' => $this->sale->customer_name.' ha realizado un pedido para recoger por '.number_format((float) $this->sale->total, 2, ',', '.').' €.',
            'sale_id' => $this->sale->id,
            'url' => route('sales.index', ['search' => $this->sale->number]),
        ];
    }
}
