<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->string('tax_id', 40)->nullable()->unique();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_supplier', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_sku', 100)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('minimum_order_quantity', 14, 3)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false)->index();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'supplier_id']);
            $table->index(['supplier_id', 'is_preferred']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
        Schema::dropIfExists('suppliers');
    }
};
