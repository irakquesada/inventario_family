<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('landing_image_path')->nullable()->after('featured_on_landing');
        });
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('carousel_enabled')->default(true)->after('testimonials_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('carousel_enabled');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('landing_image_path');
        });
    }
};
