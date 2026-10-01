<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('reference', 50)->unique();
            $table->string('contract_type', 40)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('probation_ends_on')->nullable();
            $table->decimal('weekly_hours', 6, 2)->nullable();
            $table->decimal('annual_gross_salary', 12, 2)->nullable();
            $table->unsignedTinyInteger('salary_payments')->default(12);
            $table->string('collective_agreement')->nullable();
            $table->string('workplace')->nullable();
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'starts_on', 'ends_on']);
        });
        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('name');
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->date('payment_date')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payroll_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employment_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('earnings_total', 12, 2)->default(0);
            $table->decimal('deductions_total', 12, 2)->default(0);
            $table->decimal('net_total', 12, 2)->default(0);
            $table->decimal('employer_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });
        Schema::create('payroll_entry_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('code', 50)->nullable();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('rate', 12, 4)->nullable();
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('seo_site_name')->default('Inventario Pro');
            $table->string('seo_title')->default('Inventario Pro | Gestión, productos y reservas');
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('seo_canonical_url')->nullable();
            $table->string('seo_og_image')->nullable();
            $table->string('seo_business_type')->default('LocalBusiness');
            $table->boolean('seo_indexing_enabled')->default(true);
        });
        DB::table('app_modules')->updateOrInsert(['key' => 'payroll'], ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['payroll.view', 'payroll.manage', 'payroll.self'] as $name) {
            Permission::findOrCreate($name);
        }
        Role::findOrCreate('admin')->givePermissionTo(['payroll.view', 'payroll.manage', 'payroll.self']);
        Role::findOrCreate('staff')->givePermissionTo('payroll.self');
    }

    public function down(): void
    {
        Schema::table('site_settings', fn (Blueprint $table) => $table->dropColumn(['seo_site_name', 'seo_title', 'seo_description', 'seo_keywords', 'seo_canonical_url', 'seo_og_image', 'seo_business_type', 'seo_indexing_enabled']));
        Schema::dropIfExists('payroll_entry_items');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employment_contracts');
        DB::table('app_modules')->where('key', 'payroll')->delete();
        Permission::query()->whereIn('name', ['payroll.view', 'payroll.manage', 'payroll.self'])->delete();
    }
};
