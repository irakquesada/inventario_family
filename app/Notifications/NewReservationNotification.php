<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewReservationNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels($notifiable, 'new_reservation');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'new_reservation', 'reservation_id' => $this->reservation->id,
            'reference' => $this->reservation->reference, 'customer_name' => $this->reservation->customer_name,
            'message' => "Nueva solicitud de reserva {$this->reservation->reference} para {$this->reservation->starts_at->format('d/m/Y H:i')}.",
        ];
    }

    public function toTelegram(object $notifiable): string
    {
        $name = htmlspecialchars($this->reservation->customer_name, ENT_QUOTES, 'UTF-8');
        $resource = htmlspecialchars($this->reservation->resource->name, ENT_QUOTES, 'UTF-8');

        return "📅 <b>NUEVA RESERVA PENDIENTE</b>\n\n"
            ."👤 <b>Cliente:</b> {$name}\n"
            ."🕒 <b>Fecha:</b> {$this->reservation->starts_at->format('d-m-Y H:i')}\n"
            ."👥 <b>Personas:</b> {$this->reservation->party_size}\n"
            ."📍 <b>Recurso:</b> {$resource}\n"
            ."🔖 <b>Referencia:</b> <code>{$this->reservation->reference}</code>";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nueva reserva {$this->reservation->reference}")
            ->greeting("Hola, {$notifiable->name}")
            ->line("{$this->reservation->customer_name} ha solicitado una reserva para {$this->reservation->starts_at->format('d/m/Y H:i')}.")
            ->line("Personas: {$this->reservation->party_size} · Referencia: {$this->reservation->reference}")
            ->action('Abrir reservas', route('reservations.index'));
    }
}
