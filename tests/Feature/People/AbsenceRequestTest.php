<?php

namespace Tests\Feature\People;

use App\Livewire\People\AbsencesAdmin;
use App\Livewire\People\MyAbsences;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\EmployeeAbsenceBalance;
use App\Models\User;
use App\Services\People\AttendanceReport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AbsenceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_requests_vacation_and_admin_approval_consumes_balance(): void
    {
        Carbon::setTestNow('2026-08-28 10:00:00');
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('staff');
        $employee = Employee::query()->create(['user_id' => $user->id, 'employee_number' => 'EMP-300', 'name' => 'Laura', 'is_active' => true]);
        $employee->workPeriods()->createMany([
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00'], ['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00'],
        ]);
        EmployeeAbsenceBalance::query()->create(['employee_id' => $employee->id, 'year' => 2026, 'entitled_days' => 5]);
        $vacation = AbsenceType::query()->where('code', 'VACACIONES')->firstOrFail();

        Livewire::actingAs($user)->test(MyAbsences::class)
            ->set('typeId', (string) $vacation->id)->set('startsOn', '2026-08-31')->set('endsOn', '2026-09-01')
            ->set('reason', 'Vacaciones familiares')->call('submit')->assertHasNoErrors();

        $request = AbsenceRequest::query()->firstOrFail();
        $this->assertSame('2.00', $request->requested_days);
        Livewire::actingAs($admin)->test(AbsencesAdmin::class)
            ->call('openReview', $request->id, 'approved')->set('reviewComment', 'Aprobadas')
            ->call('review')->assertHasNoErrors();
        $this->assertSame('approved', $request->fresh()->status);

        $row = app(AttendanceReport::class)->rows('2026-08-31', '2026-08-31', $employee->id)->first();
        $this->assertContains('excused', $row['incidents']);
        $this->assertNotContains('missing', $row['incidents']);
        $this->assertSame(0, $row['planned_minutes']);
    }

    public function test_overlapping_requests_and_approval_without_balance_are_rejected(): void
    {
        Carbon::setTestNow('2026-08-28 10:00:00');
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('staff');
        $employee = Employee::query()->create(['user_id' => $user->id, 'employee_number' => 'EMP-301', 'name' => 'Mario', 'is_active' => true]);
        $employee->workPeriods()->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $vacation = AbsenceType::query()->where('code', 'VACACIONES')->firstOrFail();

        Livewire::actingAs($user)->test(MyAbsences::class)
            ->set('typeId', (string) $vacation->id)->set('startsOn', '2026-08-31')->set('endsOn', '2026-08-31')
            ->set('reason', 'Vacaciones solicitadas')->call('submit')->assertHasNoErrors();
        $request = AbsenceRequest::query()->firstOrFail();

        Livewire::actingAs($admin)->test(AbsencesAdmin::class)
            ->call('openReview', $request->id, 'approved')->call('review')->assertHasErrors('review');

        Livewire::actingAs($user)->test(MyAbsences::class)
            ->set('typeId', (string) $vacation->id)->set('startsOn', '2026-08-31')->set('endsOn', '2026-08-31')
            ->set('reason', 'Solicitud duplicada')->call('submit')->assertHasErrors('startsOn');
    }

    public function test_staff_cannot_open_absence_administration(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $this->actingAs($staff)->get(route('people.absences.admin'))->assertForbidden();
    }

    public function test_admin_can_explicitly_approve_vacation_in_advance_and_future_balance_offsets_it(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = Employee::query()->create(['employee_number' => 'EMP-302', 'name' => 'Nuria', 'is_active' => true]);
        EmployeeAbsenceBalance::query()->create(['employee_id' => $employee->id, 'year' => 2026, 'entitled_days' => 8]);
        $vacation = AbsenceType::query()->where('code', 'VACACIONES')->firstOrFail();
        $request = AbsenceRequest::query()->create([
            'employee_id' => $employee->id, 'absence_type_id' => $vacation->id,
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-15',
            'requested_days' => 15, 'status' => 'pending', 'reason' => 'Viaje familiar',
        ]);

        Livewire::actingAs($admin)->test(AbsencesAdmin::class)
            ->call('openReview', $request->id, 'approved')
            ->set('flexibleApproval', true)->set('reviewComment', 'Siete días anticipados')
            ->call('review')->assertHasNoErrors();

        $request->refresh();
        $this->assertTrue($request->approved_in_advance);
        $this->assertSame('7.00', $request->balance_shortfall_days);
        EmployeeAbsenceBalance::query()->where('employee_id', $employee->id)->update(['entitled_days' => 15]);
        Livewire::actingAs($admin)->test(AbsencesAdmin::class)
            ->set('status', 'approved')->assertSee('Disponible 0,00');
    }
}
