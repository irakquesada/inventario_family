<?php

namespace App\Notifications;

use App\Models\Rental;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RentalConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(private Rental $rental) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unit = ['minute' => 'minuto', 'hour' => 'hora', 'day' => 'día', 'fixed' => 'periodo'][$this->rental->billing_unit] ?? $this->rental->billing_unit;
        $mail = (new MailMessage)
            ->subject('Confirmación de alquiler '.$this->rental->number.' · '.config('brand.name'))
            ->greeting('Hola, '.$this->rental->customer_name)
            ->line('Hemos revisado y confirmado los datos de tu alquiler.')
            ->line('Referencia: '.$this->rental->number)
            ->line('Producto: '.$this->rental->product->name.' · Cantidad: '.rtrim(rtrim(number_format((float) $this->rental->quantity, 3, '.', ''), '0'), '.'))
            ->line('Tarifa: '.$this->rental->rate->name.' · '.number_format((float) $this->rental->rate_price, 2, ',', '.').' € por '.$unit)
            ->line('Inicio previsto: '.$this->rental->started_at->format('d/m/Y H:i'))
            ->when($this->rental->planned_ends_at !== null, fn (MailMessage $message) => $message->line('Devolución prevista: '.$this->rental->planned_ends_at->format('d/m/Y H:i')))
            ->line($this->rental->grace_minutes > 0 ? 'Cortesía aplicada al cálculo: '.$this->rental->grace_minutes.' minutos.' : 'Esta tarifa no incluye minutos de cortesía.')
            ->when($this->rental->estimated_total !== null, fn (MailMessage $message) => $message->line('Importe estimado: '.number_format((float) $this->rental->estimated_total, 2, ',', '.').' €. El importe final puede variar según la devolución.'))
            ->line('Conserva esta referencia y contacta con el establecimiento si necesitas corregir algún dato.')
            ->salutation('Gracias por confiar en '.config('brand.name'));

        return $mail;
    }
}
