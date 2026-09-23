<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $token = (string) config('services.telegram.bot_token');
        $chatId = (string) ($notifiable->notificationPreference?->telegram_chat_id ?: config('services.telegram.chat_id'));

        if ($token === '' || $chatId === '') {
            return;
        }

        try {
            Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 500)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $notification->toTelegram($notifiable),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ])
                ->throw();
        } catch (Throwable $exception) {
            Log::warning('No se pudo entregar una notificación por Telegram.', [
                'notification' => $notification::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
