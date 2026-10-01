<?php

namespace Tests\Feature\Payroll;

use App\Livewire\Payroll\ContractsIndex;
use App\Livewire\Payroll\PayrollRunShow;
use App\Livewire\Payroll\PayrollRunsIndex;
use App\Models\AppModule;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PayrollModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_contract_with_private_document_and_exports_summary(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = Employee::query()->create(['employee_number' => 'EMP-900', 'name' => 'Elena Contrato', 'is_active' => true]);
        $employee->update(['tax_id' => '12345678Z', 'social_security_number' => 'SS-123']);
        $this->assertNotSame('12345678Z', (string) $employee->getRawOriginal('tax_id'));
        $this->assertSame('12345678Z', $employee->fresh()->tax_id);

        Livewire::actingAs($admin)->test(ContractsIndex::class)
            ->set('employeeId', (string) $employee->id)->set('reference', 'CTR-TEST-001')
            ->set('contractType', 'indefinite')->set('status', 'active')->set('startsOn', '2026-01-01')
            ->set('weeklyHours', '40')->set('annualGrossSalary', '24000')->set('salaryPayments', '12')
            ->set('document', UploadedFile::fake()->create('contrato-firmado.pdf', 100, 'application/pdf'))
            ->call('save')->assertHasNoErrors();

        $contract = EmploymentContract::query()->firstOrFail();
        Storage::disk('local')->assertExists($contract->document_path);
        $this->actingAs($admin)->get(route('payroll.contracts.pdf', $contract))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_payroll_period_generates_entries_calculates_items_and_locks_when_finalized(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = Employee::query()->create(['employee_number' => 'EMP-901', 'name' => 'Mario Nómina', 'is_active' => true]);
        EmploymentContract::query()->create(['employee_id' => $employee->id, 'reference' => 'CTR-PAY-001', 'contract_type' => 'indefinite', 'status' => 'active', 'starts_on' => '2026-01-01', 'annual_gross_salary' => 24000, 'salary_payments' => 12]);

        Livewire::actingAs($admin)->test(PayrollRunsIndex::class)
            ->set('name', 'Nómina septiembre')->set('periodStartsOn', '2026-09-01')->set('periodEndsOn', '2026-09-30')
            ->call('create')->assertRedirect();
        $run = PayrollRun::query()->firstOrFail();
        $entry = PayrollEntry::query()->firstOrFail();
        $this->assertSame('2000.00', $entry->net_total);

        Livewire::actingAs($admin)->test(PayrollRunShow::class, ['payrollRun' => $run])
            ->call('editEntry', $entry->id)->set('itemType', 'deduction')->set('itemDescription', 'IRPF')->set('itemAmount', '300')
            ->call('addItem')->assertHasNoErrors()->call('finalize')->assertHasNoErrors();
        $this->assertSame('1700.00', $entry->fresh()->net_total);
        $this->assertSame('finalized', $run->fresh()->status);
        $this->actingAs($admin)->get(route('payroll.entries.payslip', $entry))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_employee_only_sees_own_published_documents_and_module_can_be_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('staff');
        $employee = Employee::query()->create(['user_id' => $user->id, 'employee_number' => 'EMP-902', 'name' => 'Sara', 'is_active' => true]);
        $other = Employee::query()->create(['employee_number' => 'EMP-903', 'name' => 'Otra', 'is_active' => true]);
        $run = PayrollRun::query()->create(['reference' => 'NOM-TEST', 'name' => 'Septiembre', 'period_starts_on' => '2026-09-01', 'period_ends_on' => '2026-09-30', 'status' => 'finalized']);
        $own = PayrollEntry::query()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);
        $foreign = PayrollEntry::query()->create(['payroll_run_id' => $run->id, 'employee_id' => $other->id]);
        $this->actingAs($user)->get(route('payroll.mine'))->assertOk()->assertSee('Septiembre');
        $this->actingAs($user)->get(route('payroll.entries.payslip', $own))->assertOk();
        $this->actingAs($user)->get(route('payroll.entries.payslip', $foreign))->assertForbidden();
        AppModule::query()->where('key', 'payroll')->update(['enabled' => false]);
        $this->actingAs($user)->get(route('payroll.mine'))->assertNotFound();
    }
}
