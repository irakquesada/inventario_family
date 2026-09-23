<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class TestTelegram extends Command
{
    protected $signature = 'app:test-telegram';

    protected $description = 'Envía una notificación de prueba a Telegram sin mostrar las credenciales';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.chat_id');

        if (! config('services.telegram.enabled') || $token === '' || $chatId === '') {
            $this->error('Telegram no está completamente configurado o no está activado.');

            return self::FAILURE;
        }

        try {
            Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "✅ <b>PRUEBA CORRECTA</b>\n\n"
                        .htmlspecialchars(config('brand.name'), ENT_QUOTES, 'UTF-8')
                        ." ya puede enviar alertas de inventario.\n"
                        .'🕒 <b>Hora:</b> '.now()->format('d-m-Y H:i:s'),
                    'parse_mode' => 'HTML',
                    'link_preview_options' => json_encode(['is_disabled' => true]),
                ])
                ->throw();
        } catch (Throwable $exception) {
            $this->error('Telegram rechazó la prueba. Revisa el token, el chat y que el bot pueda escribir.');

            return self::FAILURE;
        }

        $this->info('Prueba enviada correctamente a Telegram.');

        return self::SUCCESS;
    }
}
