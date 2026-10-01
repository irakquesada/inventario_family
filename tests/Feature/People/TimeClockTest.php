<?php

namespace Tests\Feature\People;

use App\Livewire\People\EmployeesIndex;
use App\Livewire\People\JobPositionsIndex;
use App\Livewire\People\TimeClock;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TimeClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_employee_linked_to_a_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $worker = User::factory()->create();
        Livewire::actingAs($admin)->test(JobPositionsIndex::class)
            ->set('name', 'Camarero')->set('code', 'CAMARERO')->call('save')->assertHasNoErrors();
        $positionId = JobPosition::query()->value('id');

        Livewire::actingAs($admin)->test(EmployeesIndex::class)
            ->set('employeeNumber', 'emp-001')->set('userId', (string) $worker->id)
            ->assertSet('name', $worker->name)->assertSet('email', $worker->email)
            ->set('positionId', (string) $positionId)
            ->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-001', 'name' => $worker->name,
            'user_id' => $worker->id, 'job_position_id' => $positionId,
        ]);
    }

    public function test_employee_can_clock_in_and_out_without_opening_two_entries(): void
    {
        Carbon::setTestNow('2026-08-27 09:00:00');
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('staff');
        $employee = Employee::query()->create([
            'user_id' => $user->id, 'employee_number' => 'EMP-002', 'name' => 'Luis Empleado', 'is_active' => true,
        ]);
        $employee->workPeriods()->createMany([
            ['weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '13:00'],
            ['weekday' => 3, 'starts_at' => '17:00', 'ends_at' => '21:00'],
        ]);

        Livewire::actingAs($user)->test(TimeClock::class)
            ->assertSee('Mi horario semanal')->assertSee('09:00–13:00')->assertSee('17:00–21:00')->assertSee('8 h')
            ->call('clockIn')->assertHasNoErrors();
        Livewire::actingAs($user)->test(TimeClock::class)->call('clockIn')->assertHasErrors('clock');

        Carbon::setTestNow('2026-08-27 17:15:00');
        Livewire::actingAs($user)->test(TimeClock::class)->call('clockOut')->assertHasNoErrors();

        $entry = $employee->timeEntries()->firstOrFail();
        $this->assertSame('2026-08-27 09:00:00', $entry->clocked_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-27 17:15:00', $entry->clocked_out_at->format('Y-m-d H:i:s'));
        $this->assertSame($user->id, $entry->recorded_by);
    }

    public function test_employee_can_filter_and_print_only_their_own_time_entries(): void
    {
        Carbon::setTestNow('2026-09-30 12:00:00');
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('staff');
        $employee = Employee::query()->create([
            'user_id' => $user->id, 'employee_number' => 'EMP-003', 'name' => 'Sara Fichajes', 'is_active' => true,
        ]);
        $employee->timeEntries()->create(['clocked_in_at' => '2026-09-10 09:00:00', 'clocked_out_at' => '2026-09-10 17:00:00']);
        $employee->timeEntries()->create(['clocked_in_at' => '2026-08-10 10:00:00', 'clocked_out_at' => '2026-08-10 12:00:00']);

        Livewire::actingAs($user)->test(TimeClock::class)
            ->set('entriesFrom', '2026-09-01')->set('entriesTo', '2026-09-30')
            ->assertSee('10/09/2026')->assertDontSee('10/08/2026')
            ->assertSee('8 h')->assertSee('Imprimir fichajes');
    }
}
