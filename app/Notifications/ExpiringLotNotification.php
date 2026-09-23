<?php

namespace App\Notifications;

use App\Models\InventoryLot;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpiringLotNotification extends Notification
{
    use Queueable;

    public function __construct(public InventoryLot $lot) {}

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels($notifiable, 'expiring_lot');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'expiring_lot',
            'product_id' => $this->lot->product_id,
            'product_name' => $this->lot->product->name,
            'warehouse_id' => $this->lot->warehouse_id,
            'warehouse_name' => $this->lot->warehouse->name,
            'lot_id' => $this->lot->id,
            'lot_number' => $this->lot->lot_number,
            'quantity' => (float) $this->lot->quantity,
            'expiry_date' => $this->lot->expiry_date->toDateString(),
            'message' => "El lote {$this->lot->lot_number} de {$this->lot->product->name} requiere atención por caducidad.",
        ];
    }

    public function toTelegram(object $notifiable): string
    {
        $product = htmlspecialchars($this->lot->product->name, ENT_QUOTES, 'UTF-8');
        $lot = htmlspecialchars($this->lot->lot_number, ENT_QUOTES, 'UTF-8');
        $warehouse = htmlspecialchars($this->lot->warehouse->name, ENT_QUOTES, 'UTF-8');
        $quantity = number_format((float) $this->lot->quantity, 3, ',', '.');
        $status = $this->lot->expiry_date->isPast() ? 'CADUCADO' : 'PRÓXIMO A CADUCAR';

        return "🗓️ <b>{$status}</b>\n\n"
            ."📦 <b>Producto:</b> {$product}\n"
            ."🏷️ <b>Lote:</b> <code>{$lot}</code>\n"
            ."🏬 <b>Almacén:</b> {$warehouse}\n"
            ."📊 <b>Existencia:</b> {$quantity}\n"
            .'⏳ <b>Caducidad:</b> '.$this->lot->expiry_date->format('d-m-Y');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Caducidad: {$this->lot->product->name}")
            ->greeting("Hola, {$notifiable->name}")
            ->line("El lote {$this->lot->lot_number} de {$this->lot->product->name} requiere atención.")
            ->line('Fecha de caducidad: '.$this->lot->expiry_date->format('d/m/Y'))
            ->action('Revisar lotes', route('inventory.lots.index'));
    }
}
