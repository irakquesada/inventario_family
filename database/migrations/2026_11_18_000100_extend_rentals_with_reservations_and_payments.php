<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->softDeletes();
            $table->timestamp('confirmed_at')->nullable()->after('returned_at');
            $table->foreignId('confirmed_by')->nullable()->after('returned_by')->constrained('users')->nullOnDelete();
            $table->timestamp('confirmation_sent_at')->nullable()->after('confirmed_at');
            $table->string('source', 24)->default('admin')->after('status')->index();
        });

        Schema::create('rental_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('max_submissions')->default(1);
            $table->unsignedInteger('submissions_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('kind', 24)->default('payment');
            $table->timestamps();
            $table->index(['rental_id', 'created_at']);
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('rental_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('rental_id'));
        Schema::dropIfExists('rental_payments');
        Schema::dropIfExists('rental_invitations');
        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['deleted_at', 'confirmed_at', 'confirmation_sent_at', 'source']);
        });
    }
};
