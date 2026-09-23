<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(true);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('app_modules')->insert(collect(['inventory', 'landing', 'reservations', 'people'])->map(fn (string $key) => [
            'key' => $key, 'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('app_modules');
    }
};
