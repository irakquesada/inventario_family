<?php

namespace Database\Seeders;

use App\Models\AbsenceType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect([
            'inventory.view',
            'inventory.manage',
            'inventory.adjust',
            'inventory.count',
            'inventory.count.approve',
            'audit.view',
            'users.manage',
            'settings.manage',
            'reservations.view',
            'reservations.manage',
            'reservations.configure',
            'content.manage',
            'people.view',
            'people.manage',
            'people.clock',
            'sales.view',
            'sales.create',
            'sales.manage',
            'sales.refund',
            'payroll.view',
            'payroll.manage',
            'payroll.self',
        ])->mapWithKeys(fn (string $name) => [$name => Permission::findOrCreate($name)]);

        $admin = Role::findOrCreate('admin');
        $admin->syncPermissions($permissions->values());

        $staff = Role::findOrCreate('staff');
        $staff->syncPermissions($permissions->only([
            'inventory.view', 'inventory.manage', 'inventory.adjust', 'inventory.count',
            'reservations.view', 'reservations.manage', 'people.clock', 'sales.view', 'sales.create', 'payroll.self',
        ])->values());

        foreach ([
            ['name' => 'Vacaciones', 'code' => 'VACACIONES', 'deducts_balance' => true, 'requires_attachment' => false, 'is_paid' => true],
            ['name' => 'Baja médica', 'code' => 'BAJA_MEDICA', 'deducts_balance' => false, 'requires_attachment' => true, 'is_paid' => true],
            ['name' => 'Permiso personal', 'code' => 'PERMISO', 'deducts_balance' => false, 'requires_attachment' => false, 'is_paid' => false],
            ['name' => 'Descanso compensatorio', 'code' => 'DESCANSO_COMP', 'deducts_balance' => false, 'requires_attachment' => false, 'is_paid' => true],
        ] as $type) {
            AbsenceType::query()->firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
