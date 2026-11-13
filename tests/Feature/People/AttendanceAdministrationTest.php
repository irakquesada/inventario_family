<?php

namespace Tests\Feature\People;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\People\CorrectTimeEntry;
use App\Services\People\ManageTimeEntry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_modify_and_soft_delete_an_entry_with_full_history(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = Employee::query()->create(['employee_number' => 'EMP-AUDIT', 'name' => 'Empleado auditado', 'is_active' => true]);
        $this->actingAs($admin);

        $manager = app(ManageTimeEntry::class);
        $entry = $manager->create($employee, $admin, '2026-11-10 09:00', '2026-11-10 17:00', 'Olvidó fichar durante su jornada.', '127.0.0.1');
        app(CorrectTimeEntry::class)->handle($entry, $admin, '2026-11-10 09:15', '2026-11-10 17:05', 'Se ajusta con la hoja firmada.', '127.0.0.1');
        $manager->delete($entry->fresh(), $admin, 'Registro duplicado confirmado.', '127.0.0.1');

        $entry = TimeEntry::withTrashed()->with('adjustments')->findOrFail($entry->id);
        $this->assertTrue($entry->trashed());
        $this->assertEqualsCanonicalizing(['deleted', 'updated', 'created'], $entry->adjustments->pluck('action')->all());
        $this->assertDatabaseHas('time_entry_adjustments', ['time_entry_id' => $entry->id, 'action' => 'deleted', 'reason' => 'Registro duplicado confirmado.']);
        $this->assertTrue(ActivityLog::query()->where('subject_type', TimeEntry::class)->where('subject_id', (string) $entry->id)->exists());
    }
}
