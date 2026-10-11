<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnlineOrderCustomerNotification extends Notification
{
    use Queueable;

    public function __construct(private Sale $sale) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pedido '.$this->sale->number.' recibido · '.config('brand.name'))
            ->greeting('¡Gracias, '.$this->sale->customer_name.'!')
            ->line('Hemos recibido tu pedido para recoger en tienda.')
            ->line('Referencia: '.$this->sale->number)
            ->line('Total al recoger: '.number_format((float) $this->sale->total, 2, ',', '.').' €')
            ->line('Fecha solicitada: '.$this->sale->pickup_date?->format('d/m/Y').' a las '.$this->sale->pickup_time)
            ->line('Te avisaremos cuando esté listo. Conserva esta referencia para identificarlo.')
            ->action('Seguir mi pedido', route('public.orders.show', $this->sale->reference));
    }
}
