<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable()->after('email');
            $table->string('phone', 40)->nullable()->after('avatar_path');
            $table->date('birthday')->nullable()->after('phone');
            $table->string('city')->nullable()->after('birthday');
            $table->text('bio')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_path', 'phone', 'birthday', 'city', 'bio']);
        });
    }
};
