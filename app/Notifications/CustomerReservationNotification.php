<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Models\SiteSetting;
use App\Support\ReservationMailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerReservationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Reservation $reservation, public string $state) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = SiteSetting::query()->first();
        $column = 'reservation_mail_'.$this->state;
        $template = $settings?->{$column} ?: ReservationMailTemplates::default($this->state);
        $subject = match ($this->state) {
            'confirmed' => "Reserva {$this->reservation->reference} confirmada",
            'cancelled' => "Actualización de la reserva {$this->reservation->reference}",
            default => "Solicitud de reserva {$this->reservation->reference} recibida",
        };
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hola, {$this->reservation->customer_name}")
            ->line(ReservationMailTemplates::render($template, $this->reservation->loadMissing('resource')))
            ->line('Conserva esta referencia para cualquier consulta: '.$this->reservation->reference)
            ->salutation('Gracias, '.config('brand.name'));

        if ($settings?->reservation_mail_from_address) {
            $mail->from($settings->reservation_mail_from_address, $settings->reservation_mail_from_name ?: config('brand.name'));
        }

        return $mail;
    }
}
