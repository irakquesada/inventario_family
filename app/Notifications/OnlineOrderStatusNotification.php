<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnlineOrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private Sale $sale) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = ['preparing' => 'ya está en preparación', 'ready' => 'ya está listo para recoger', 'completed' => 'ha sido entregado'];

        return (new MailMessage)
            ->subject('Actualización del pedido '.$this->sale->number)
            ->greeting('Hola, '.$this->sale->customer_name)
            ->line('Tu pedido '.$labels[$this->sale->status].'.')
            ->when($this->sale->status === 'ready', fn (MailMessage $mail) => $mail->line('Puedes pasar por '.$this->sale->warehouse->name.' con tu referencia.'))
            ->action('Consultar pedido', route('public.orders.show', $this->sale->reference));
    }
}
