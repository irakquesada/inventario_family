<?php

namespace Tests\Feature\People;

use App\Livewire\People\EmployeeSchedule;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_split_shifts_and_leave_rest_days_empty(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = Employee::query()->create(['employee_number' => 'EMP-100', 'name' => 'Ana', 'is_active' => true]);

        Livewire::actingAs($admin)->test(EmployeeSchedule::class, ['employee' => $employee])
            ->set('periods.2', [
                ['starts_at' => '13:00', 'ends_at' => '16:00'],
                ['starts_at' => '19:00', 'ends_at' => '23:00'],
            ])
            ->call('save')->assertHasNoErrors();

        $this->assertDatabaseCount('employee_work_periods', 2);
        $this->assertDatabaseHas('employee_work_periods', ['employee_id' => $employee->id, 'weekday' => 2, 'starts_at' => '13:00']);
        $this->assertDatabaseMissing('employee_work_periods', ['employee_id' => $employee->id, 'weekday' => 1]);
    }

    public function test_overlapping_and_cross_midnight_shifts_are_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = Employee::query()->create(['employee_number' => 'EMP-101', 'name' => 'Luis', 'is_active' => true]);

        Livewire::actingAs($admin)->test(EmployeeSchedule::class, ['employee' => $employee])
            ->set('periods.0', [['starts_at' => '22:00', 'ends_at' => '02:00']])
            ->set('periods.1', [['starts_at' => '01:00', 'ends_at' => '05:00']])
            ->call('save')->assertHasErrors('periods');

        $this->assertDatabaseCount('employee_work_periods', 0);
    }

    public function test_staff_cannot_edit_employee_schedules(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $employee = Employee::query()->create(['employee_number' => 'EMP-102', 'name' => 'Sara', 'is_active' => true]);

        $this->actingAs($staff)->get(route('people.employees.schedule', $employee))->assertForbidden();
    }
}
