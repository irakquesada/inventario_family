<?php

namespace App\Notifications;

use App\Models\Testimonial;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTestimonialNotification extends Notification
{
    use Queueable;

    public function __construct(public Testimonial $testimonial) {}

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels($notifiable, 'new_testimonial');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'new_testimonial',
            'testimonial_id' => $this->testimonial->id,
            'title' => 'Nueva reseña pendiente',
            'message' => "{$this->testimonial->customer_name} ha enviado una reseña de {$this->testimonial->rating} estrellas.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva reseña pendiente de moderación')
            ->greeting("Hola, {$notifiable->name}")
            ->line("{$this->testimonial->customer_name} ha enviado una reseña de {$this->testimonial->rating} estrellas.")
            ->action('Revisar reseña', route('admin.landing.settings'));
    }

    public function toTelegram(object $notifiable): string
    {
        $name = htmlspecialchars($this->testimonial->customer_name, ENT_QUOTES, 'UTF-8');

        return "⭐ <b>NUEVA RESEÑA PENDIENTE</b>\n\n"
            ."👤 <b>Cliente:</b> {$name}\n"
            ."⭐ <b>Valoración:</b> {$this->testimonial->rating}/5\n"
            .'🕒 <b>Hora:</b> '.now()->format('d-m-Y H:i:s');
    }
}
