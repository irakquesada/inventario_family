<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 40)->unique();
            $table->string('color', 20)->default('orange');
            $table->boolean('deducts_balance')->default(false);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('employee_absence_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled_days', 7, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'year']);
        });

        Schema::create('absence_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('absence_type_id')->constrained()->restrictOnDelete();
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('is_full_day')->default(true);
            $table->decimal('requested_days', 7, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_requests');
        Schema::dropIfExists('employee_absence_balances');
        Schema::dropIfExists('absence_types');
    }
};
