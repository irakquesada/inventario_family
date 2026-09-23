<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Warehouse;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Product $product,
        public Warehouse $warehouse,
        public float $quantity,
    ) {}

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels($notifiable, 'low_stock');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'low_stock',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_name' => $this->warehouse->name,
            'quantity' => $this->quantity,
            'minimum_stock' => (float) $this->product->minimum_stock,
            'message' => "{$this->product->name} ha alcanzado el stock mínimo en {$this->warehouse->name}.",
        ];
    }

    public function toTelegram(object $notifiable): string
    {
        $product = htmlspecialchars($this->product->name, ENT_QUOTES, 'UTF-8');
        $sku = htmlspecialchars($this->product->sku, ENT_QUOTES, 'UTF-8');
        $warehouse = htmlspecialchars($this->warehouse->name, ENT_QUOTES, 'UTF-8');
        $quantity = number_format($this->quantity, 3, ',', '.');
        $minimum = number_format((float) $this->product->minimum_stock, 3, ',', '.');

        return "⚠️ <b>STOCK BAJO</b>\n\n"
            ."📦 <b>Producto:</b> {$product}\n"
            ."🏷️ <b>SKU:</b> <code>{$sku}</code>\n"
            ."🏬 <b>Almacén:</b> {$warehouse}\n"
            ."📉 <b>Existencia:</b> {$quantity}\n"
            ."🎯 <b>Mínimo:</b> {$minimum}\n"
            .'🕒 <b>Hora:</b> '.now()->format('d-m-Y H:i:s');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Stock bajo: {$this->product->name}")
            ->greeting("Hola, {$notifiable->name}")
            ->line("{$this->product->name} ha alcanzado el stock mínimo en {$this->warehouse->name}.")
            ->line('Existencia: '.number_format($this->quantity, 3, ',', '.').' · mínimo: '.number_format((float) $this->product->minimum_stock, 3, ',', '.'))
            ->action('Revisar inventario', route('inventory.products.index'));
    }
}
