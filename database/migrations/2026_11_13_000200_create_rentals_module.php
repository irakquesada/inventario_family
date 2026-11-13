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
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_rentable')->default(false)->after('is_online_sellable')->index();
        });
        Schema::create('rental_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('billing_unit', 20);
            $table->unsignedInteger('unit_minutes')->nullable();
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('minimum_units')->default(1);
            $table->unsignedInteger('grace_minutes')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('rentals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('number', 40)->unique()->nullable();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('rental_rate_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->decimal('quantity', 14, 3)->default(1);
            $table->timestamp('started_at')->index();
            $table->timestamp('planned_ends_at')->nullable()->index();
            $table->timestamp('returned_at')->nullable()->index();
            $table->decimal('rate_price', 14, 2);
            $table->string('billing_unit', 20);
            $table->unsignedInteger('unit_minutes')->nullable();
            $table->unsignedInteger('minimum_units')->default(1);
            $table->unsignedInteger('grace_minutes')->default(0);
            $table->unsignedInteger('billed_units')->nullable();
            $table->decimal('estimated_total', 14, 2)->nullable();
            $table->decimal('final_total', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamps();
        });
        DB::table('app_modules')->insertOrIgnore(['key' => 'rentals', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['rentals.view', 'rentals.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }
        Role::findOrCreate('admin')->givePermissionTo(['rentals.view', 'rentals.manage']);
        Role::findOrCreate('staff')->givePermissionTo('rentals.view');
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
        Schema::dropIfExists('rental_rates');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('is_rentable'));
        DB::table('app_modules')->where('key', 'rentals')->delete();
        Permission::query()->whereIn('name', ['rentals.view', 'rentals.manage'])->delete();
    }
};
