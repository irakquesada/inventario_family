<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_login_page(): void
    {
        $this->get('/iniciar-sesion')
            ->assertOk()
            ->assertSee('data-password-toggle', false)
            ->assertSee('Mostrar');
    }

    public function test_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/iniciar-sesion', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect('/panel');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('access_logs', ['user_id' => $user->id, 'event' => 'login']);
        $this->post('/cerrar-sesion')->assertRedirect('/iniciar-sesion');
        $this->assertGuest();
        $this->assertDatabaseHas('access_logs', ['user_id' => $user->id, 'event' => 'logout']);
    }

    public function test_turnstile_widget_is_rendered_when_enabled(): void
    {
        config()->set('services.turnstile.enabled', true);
        config()->set('services.turnstile.site_key', 'public-site-key');

        $this->get('/iniciar-sesion')
            ->assertOk()
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertSee('data-sitekey="public-site-key"', false);
    }

    public function test_login_is_rejected_when_turnstile_validation_fails(): void
    {
        config()->set('services.turnstile.enabled', true);
        config()->set('services.turnstile.secret_key', 'private-secret-key');
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/iniciar-sesion', [
            'email' => $user->email,
            'password' => 'secret-password',
            'cf-turnstile-response' => 'invalid-token',
        ])->assertSessionHasErrors('turnstile');

        $this->assertGuest();
    }

    public function test_user_can_log_in_after_successful_turnstile_validation(): void
    {
        config()->set('services.turnstile.enabled', true);
        config()->set('services.turnstile.secret_key', 'private-secret-key');
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/iniciar-sesion', [
            'email' => $user->email,
            'password' => 'secret-password',
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect('/panel');

        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'private-secret-key'
            && $request['response'] === 'valid-token');
    }
}
