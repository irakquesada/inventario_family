<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 40)->index();
            $table->string('path', 500)->nullable()->index();
            $table->string('label', 255)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_hash', 64)->index();
            $table->string('session_hash', 64)->index();
            $table->string('referrer_host', 255)->nullable();
            $table->string('device_type', 20)->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['event_type', 'occurred_at']);
            $table->index(['product_id', 'occurred_at']);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('reservation_mail_from_name')->nullable();
            $table->string('reservation_mail_from_address')->nullable();
            $table->text('reservation_mail_received')->nullable();
            $table->text('reservation_mail_confirmed')->nullable();
            $table->text('reservation_mail_cancelled')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'reservation_mail_from_name', 'reservation_mail_from_address',
                'reservation_mail_received', 'reservation_mail_confirmed', 'reservation_mail_cancelled',
            ]);
        });
        Schema::dropIfExists('public_analytics_events');
    }
};
