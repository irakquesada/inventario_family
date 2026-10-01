<?php

namespace Tests\Feature\People;

use App\Livewire\People\ScheduleOverview;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_every_employee_shift_rest_and_absence_in_the_week(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $ana = Employee::query()->create(['employee_number' => 'EMP-500', 'name' => 'Ana Turnos', 'is_active' => true]);
        $luis = Employee::query()->create(['employee_number' => 'EMP-501', 'name' => 'Luis Descanso', 'is_active' => true]);
        $ana->workPeriods()->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $vacation = AbsenceType::query()->where('code', 'VACACIONES')->firstOrFail();
        AbsenceRequest::query()->create([
            'employee_id' => $luis->id, 'absence_type_id' => $vacation->id,
            'starts_on' => '2026-09-29', 'ends_on' => '2026-09-30',
            'requested_days' => 2, 'status' => 'approved', 'reason' => 'Descanso anual',
        ]);

        Livewire::actingAs($admin)->withQueryParams(['semana' => '2026-09-28'])
            ->test(ScheduleOverview::class)
            ->assertSee('Ana Turnos')->assertSee('09:00-17:00')
            ->assertSee('Luis Descanso')->assertSee('Vacaciones')->assertSee('Descanso');
    }

    public function test_general_schedule_is_safe_when_there_are_no_employees_and_exports_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('people.schedules.overview', ['semana' => 'dato-invalido']))
            ->assertOk()->assertSee('Todavía no hay empleados activos');
        $this->actingAs($admin)->get(route('people.schedules.pdf', ['week' => '2026-09-28']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
