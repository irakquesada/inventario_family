<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestTelegramCommandTest extends TestCase
{
    public function test_it_sends_a_safe_telegram_test_message(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config()->set('services.telegram', [
            'enabled' => true,
            'bot_token' => 'secret-test-token',
            'chat_id' => '123456',
        ]);

        $this->artisan('app:test-telegram')
            ->expectsOutput('Prueba enviada correctamente a Telegram.')
            ->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/botsecret-test-token/sendMessage'
            && $request['chat_id'] === '123456'
            && str_contains($request['text'], 'PRUEBA CORRECTA'));
    }
}
