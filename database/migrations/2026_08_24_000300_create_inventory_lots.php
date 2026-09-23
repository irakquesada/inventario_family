<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('tracks_lots')->default(false)->index()->after('notify_low_stock');
            $table->boolean('requires_expiry')->default(false)->after('tracks_lots');
            $table->unsignedSmallInteger('expiry_warning_days')->default(7)->after('requires_expiry');
        });

        Schema::create('inventory_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('lot_number', 100);
            $table->date('expiry_date')->nullable()->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id', 'lot_number']);
            $table->index(['product_id', 'warehouse_id', 'expiry_date']);
        });

        Schema::create('stock_movement_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unique(['stock_movement_id', 'inventory_lot_id']);
        });

        Schema::table('inventory_count_items', function (Blueprint $table): void {
            $table->string('reconciliation_lot_number', 100)->nullable()->after('notes');
            $table->date('reconciliation_expiry_date')->nullable()->after('reconciliation_lot_number');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_items', function (Blueprint $table): void {
            $table->dropColumn(['reconciliation_lot_number', 'reconciliation_expiry_date']);
        });
        Schema::dropIfExists('stock_movement_lots');
        Schema::dropIfExists('inventory_lots');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['tracks_lots', 'requires_expiry', 'expiry_warning_days']);
        });
    }
};
