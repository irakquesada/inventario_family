<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('online_store_enabled')->default(false)->after('carousel_enabled');
            $table->foreignId('online_pickup_warehouse_id')->nullable()->after('online_store_enabled')->constrained('warehouses')->nullOnDelete();
            $table->text('online_order_instructions')->nullable()->after('online_pickup_warehouse_id');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_online_sellable')->default(false)->after('is_sellable')->index();
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('channel', 24)->default('pos')->after('status')->index();
            $table->string('fulfilment_method', 24)->nullable()->after('channel');
            $table->date('pickup_date')->nullable()->after('fulfilment_method');
            $table->string('pickup_time', 5)->nullable()->after('pickup_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn(['channel', 'fulfilment_method', 'pickup_date', 'pickup_time']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('is_online_sellable'));
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('online_pickup_warehouse_id');
            $table->dropColumn(['online_store_enabled', 'online_order_instructions']);
        });
    }
};
