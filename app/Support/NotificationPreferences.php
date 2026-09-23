<?php

namespace App\Support;

use App\Notifications\Channels\TelegramChannel;

class NotificationPreferences
{
    public const EVENTS = [
        'low_stock' => ['label' => 'Stock bajo', 'description' => 'Cuando un producto vigilado cruza su stock mínimo.'],
        'expiring_lot' => ['label' => 'Lotes y caducidad', 'description' => 'Lotes caducados o próximos a su fecha límite.'],
        'new_reservation' => ['label' => 'Nuevas reservas', 'description' => 'Cada solicitud recibida desde la landing.'],
        'new_testimonial' => ['label' => 'Nuevas reseñas', 'description' => 'Opiniones recibidas que esperan moderación.'],
    ];

    public static function defaults(): array
    {
        return collect(array_keys(self::EVENTS))->mapWithKeys(fn (string $event) => [
            $event => ['database' => true, 'mail' => false, 'telegram' => false],
        ])->all();
    }

    public static function channels(object $notifiable, string $event): array
    {
        $setting = $notifiable->notificationPreference;

        if (! $setting) {
            return config('services.telegram.enabled') && config('services.telegram.chat_id')
                ? ['database', TelegramChannel::class]
                : ['database'];
        }

        $preferences = array_replace_recursive(self::defaults(), $setting->preferences ?? []);
        $selected = $preferences[$event] ?? self::defaults()[$event] ?? ['database' => true];
        $channels = [];

        if ($selected['database'] ?? false) {
            $channels[] = 'database';
        }
        if ($selected['mail'] ?? false) {
            $channels[] = 'mail';
        }
        if (($selected['telegram'] ?? false) && config('services.telegram.enabled') && $setting->telegram_chat_id) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }
}
