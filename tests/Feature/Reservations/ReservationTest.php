<?php

namespace Tests\Feature\Reservations;

use App\Livewire\Reservations\ReservationsIndex;
use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Models\Reservation;
use App\Models\ReservationResource;
use App\Models\User;
use App\Services\Reservations\CreateReservation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected ReservationResource $resource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resource = ReservationResource::query()->create(['name' => 'Mesa 1', 'code' => 'M1', 'capacity' => 4]);
        BusinessHour::query()->create(['weekday' => 1, 'opens_at' => '12:00', 'closes_at' => '23:00']);
    }

    public function test_overlaps_are_rejected_but_adjacent_reservations_are_allowed(): void
    {
        $creator = app(CreateReservation::class);
        $creator->handle($this->resource, $this->data('2026-08-24', '19:00', 90));

        try {
            $creator->handle($this->resource, $this->data('2026-08-24', '20:00', 60));
            $this->fail('Debía rechazar el solapamiento.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('startTime', $exception->errors());
        }

        $creator->handle($this->resource, $this->data('2026-08-24', '20:30', 60));
        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_capacity_and_business_hours_are_enforced(): void
    {
        try {
            app(CreateReservation::class)->handle($this->resource, [...$this->data('2026-08-24', '19:00', 60), 'party_size' => 5]);
            $this->fail('Debía rechazar el exceso de capacidad.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('partySize', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        app(CreateReservation::class)->handle($this->resource, $this->data('2026-08-24', '23:00', 60));
    }

    public function test_exceptional_closure_overrides_weekly_hours(): void
    {
        BusinessClosure::query()->create(['date' => '2026-08-24', 'is_closed' => true, 'reason' => 'Festivo']);

        $this->expectException(ValidationException::class);
        app(CreateReservation::class)->handle($this->resource, $this->data('2026-08-24', '19:00', 60));
    }

    public function test_overnight_hours_accept_reservations_after_midnight(): void
    {
        BusinessHour::query()->create(['weekday' => 5, 'opens_at' => '18:00', 'closes_at' => '02:00']);

        $reservation = app(CreateReservation::class)->handle($this->resource, $this->data('2026-08-29', '01:00', 60));

        $this->assertSame('2026-08-29 01:00', $reservation->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-08-29 02:00', $reservation->ends_at->format('Y-m-d H:i'));
    }

    public function test_split_schedule_accepts_each_shift_but_rejects_the_break(): void
    {
        BusinessHour::query()->create(['weekday' => 2, 'opens_at' => '13:00', 'closes_at' => '16:00']);
        BusinessHour::query()->create(['weekday' => 2, 'opens_at' => '19:00', 'closes_at' => '23:00']);
        $creator = app(CreateReservation::class);

        $creator->handle($this->resource, $this->data('2026-08-25', '13:30', 60));
        $creator->handle($this->resource, $this->data('2026-08-25', '19:00', 90));

        try {
            $creator->handle($this->resource, $this->data('2026-08-25', '17:00', 60));
            $this->fail('Debía rechazar una reserva durante el descanso entre turnos.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('startTime', $exception->errors());
        }
        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_staff_can_operate_but_cannot_configure_reservations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        Livewire::actingAs($staff)->test(ReservationsIndex::class)->assertOk();
        $this->actingAs($staff)->get(route('reservations.settings'))->assertForbidden();
    }

    public function test_reservation_screen_shows_calendar_statistics_and_filtered_history(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Reservation::query()->create([
            'reference' => 'RES-SEP', 'reservation_resource_id' => $this->resource->id,
            'customer_name' => 'Cliente septiembre', 'customer_phone' => '600000001', 'party_size' => 4,
            'starts_at' => '2026-09-14 19:00:00', 'ends_at' => '2026-09-14 20:30:00', 'status' => 'completed',
        ]);
        Reservation::query()->create([
            'reference' => 'RES-AUG', 'reservation_resource_id' => $this->resource->id,
            'customer_name' => 'Cliente agosto', 'customer_phone' => '600000002', 'party_size' => 2,
            'starts_at' => '2026-08-17 19:00:00', 'ends_at' => '2026-08-17 20:30:00', 'status' => 'cancelled',
        ]);

        Livewire::actingAs($staff)->test(ReservationsIndex::class)
            ->set('calendarMonth', '2026-09')->set('date', '2026-09-14')
            ->set('historyFrom', '2026-09-01')->set('historyTo', '2026-09-30')
            ->assertSee('Cliente septiembre')->assertDontSee('Cliente agosto')
            ->assertSee('Anterior: 1')->assertSee('Histórico de reservas');
    }

    private function data(string $date, string $time, int $duration): array
    {
        return [
            'customer_name' => 'María', 'customer_email' => '', 'customer_phone' => '600000000',
            'party_size' => 2, 'date' => $date, 'start_time' => $time,
            'duration_minutes' => $duration, 'notes' => '',
        ];
    }
}
