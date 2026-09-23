<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 40)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('job_position_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        DB::table('employees')->whereNotNull('position')->where('position', '!=', '')->pluck('position')->unique()->each(function (string $name): void {
            $id = DB::table('job_positions')->insertGetId([
                'name' => $name, 'code' => Str::upper(Str::slug($name, '-')), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('employees')->where('position', $name)->update(['job_position_id' => $id]);
        });

        DB::table('users')->orderBy('id')->get()->each(function ($user): void {
            if (DB::table('employees')->where('user_id', $user->id)->exists()) {
                return;
            }
            DB::table('employees')->insert([
                'user_id' => $user->id,
                'employee_number' => 'USR-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                'name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_position_id');
        });
        Schema::dropIfExists('job_positions');
    }
};
