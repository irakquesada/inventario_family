<?php

namespace Tests\Feature\People;

use App\Livewire\People\AttendanceIndex;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\People\AttendanceReport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_detects_late_and_incomplete_days_and_missing_attendance(): void
    {
        Carbon::setTestNow('2026-08-27 20:00:00');
        $employee = Employee::query()->create(['employee_number' => 'EMP-200', 'name' => 'Ana', 'is_active' => true]);
        $employee->workPeriods()->createMany([
            ['weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '17:00'],
            ['weekday' => 4, 'starts_at' => '09:00', 'ends_at' => '17:00'],
        ]);
        TimeEntry::query()->create([
            'employee_id' => $employee->id, 'clocked_in_at' => '2026-08-26 09:10:00', 'clocked_out_at' => '2026-08-26 16:00:00',
        ]);

        $rows = app(AttendanceReport::class)->rows('2026-08-26', '2026-08-27');
        $wednesday = $rows->first(fn ($row) => $row['date']->toDateString() === '2026-08-26');

        $this->assertContains('late', $wednesday['incidents']);
        $this->assertContains('incomplete', $wednesday['incidents']);
        $this->assertSame(480, $wednesday['planned_minutes']);
    }

    public function test_admin_correction_requires_reason_and_keeps_immutable_adjustment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = Employee::query()->create(['employee_number' => 'EMP-201', 'name' => 'Luis', 'is_active' => true]);
        $entry = TimeEntry::query()->create([
            'employee_id' => $employee->id, 'clocked_in_at' => '2026-08-27 09:15:00', 'clocked_out_at' => '2026-08-27 17:00:00',
        ]);

        Livewire::actingAs($admin)->test(AttendanceIndex::class)
            ->call('editEntry', $entry->id)
            ->set('correctionIn', '2026-08-27T09:00')->set('correctionOut', '2026-08-27T17:00')
            ->call('saveCorrection')->assertHasErrors('correctionReason')
            ->set('correctionReason', 'Olvido confirmado por el responsable')
            ->call('saveCorrection')->assertHasNoErrors();

        $this->assertSame('09:00', $entry->fresh()->clocked_in_at->format('H:i'));
        $this->assertDatabaseHas('time_entry_adjustments', [
            'time_entry_id' => $entry->id, 'user_id' => $admin->id, 'reason' => 'Olvido confirmado por el responsable',
        ]);
    }

    public function test_staff_cannot_open_admin_attendance_or_exports(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)->get(route('people.attendance.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('people.attendance.excel', ['from' => '2026-08-01', 'to' => '2026-08-02']))->assertForbidden();
    }
}
