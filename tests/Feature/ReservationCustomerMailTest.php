<?php

namespace Tests\Feature;

use App\Livewire\Reservations\ReservationSettings;
use App\Models\Reservation;
use App\Models\ReservationResource;
use App\Models\User;
use App\Notifications\CustomerReservationNotification;
use App\Support\ReservationCustomerNotifier;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationCustomerMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receives_an_on_demand_reservation_email(): void
    {
        Notification::fake();
        $resource = ReservationResource::query()->create(['name' => 'Mesa 1', 'code' => 'M1', 'capacity' => 4]);
        $reservation = Reservation::query()->create([
            'reference' => 'RS-EMAIL', 'reservation_resource_id' => $resource->id,
            'customer_name' => 'Laura', 'customer_email' => 'laura@example.com', 'party_size' => 2,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'status' => 'pending', 'source' => 'public',
        ]);

        app(ReservationCustomerNotifier::class)->send($reservation, 'received');

        Notification::assertSentOnDemand(CustomerReservationNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === ['laura@example.com' => 'Laura']);
    }

    public function test_admin_can_customize_and_restore_reservation_templates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ReservationSettings::class)
            ->set('mailFromName', 'Fonda Juanita')
            ->set('mailFromAddress', 'reservas@example.com')
            ->set('mailReceived', 'Recibida {{reference}}')
            ->call('saveMailSettings')->assertHasNoErrors();

        $this->assertDatabaseHas('site_settings', ['reservation_mail_from_name' => 'Fonda Juanita', 'reservation_mail_received' => 'Recibida {{reference}}']);
    }
}
