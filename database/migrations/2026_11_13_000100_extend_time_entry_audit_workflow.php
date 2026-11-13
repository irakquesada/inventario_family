<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->softDeletes();
        });
        Schema::table('time_entry_adjustments', function (Blueprint $table): void {
            $table->string('action', 24)->default('updated')->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('time_entry_adjustments', fn (Blueprint $table) => $table->dropColumn('action'));
        Schema::table('time_entries', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
