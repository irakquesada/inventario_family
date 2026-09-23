<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('business_hours', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('business_closures', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->boolean('is_closed')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('reservation_resource_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->unsignedSmallInteger('party_size');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('status', 30)->default('confirmed')->index();
            $table->string('source', 30)->default('admin');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['reservation_resource_id', 'starts_at', 'ends_at'], 'reservations_resource_period_index');
        });

        foreach (['reservations.view', 'reservations.manage', 'reservations.configure'] as $permission) {
            Permission::findOrCreate($permission);
        }
        Role::findOrCreate('admin')->givePermissionTo(['reservations.view', 'reservations.manage', 'reservations.configure']);
        Role::findOrCreate('staff')->givePermissionTo(['reservations.view', 'reservations.manage']);
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('business_closures');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('reservation_resources');
        Permission::query()->whereIn('name', ['reservations.view', 'reservations.manage', 'reservations.configure'])->delete();
    }
};
