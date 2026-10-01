<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_requests', function (Blueprint $table): void {
            $table->boolean('approved_in_advance')->default(false)->after('review_comment');
            $table->decimal('balance_shortfall_days', 8, 2)->default(0)->after('approved_in_advance');
        });
    }

    public function down(): void
    {
        Schema::table('absence_requests', function (Blueprint $table): void {
            $table->dropColumn(['approved_in_advance', 'balance_shortfall_days']);
        });
    }
};
