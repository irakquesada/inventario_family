<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entry_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('previous_clocked_in_at');
            $table->timestamp('previous_clocked_out_at')->nullable();
            $table->timestamp('new_clocked_in_at');
            $table->timestamp('new_clocked_out_at')->nullable();
            $table->text('reason');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entry_adjustments');
    }
};
