<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_hours', function (Blueprint $table): void {
            $table->dropUnique(['weekday']);
            $table->index(['weekday', 'opens_at']);
        });
    }

    public function down(): void
    {
        Schema::table('business_hours', function (Blueprint $table): void {
            $table->dropIndex(['weekday', 'opens_at']);
            $table->unique('weekday');
        });
    }
};
