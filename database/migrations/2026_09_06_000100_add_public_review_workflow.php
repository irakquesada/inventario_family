<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('max_submissions')->default(1);
            $table->unsignedSmallInteger('submissions_count')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('testimonials', function (Blueprint $table): void {
            $table->foreignId('review_invitation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('profile_photo_path')->nullable()->after('comment');
            $table->string('source', 30)->default('manual')->after('profile_photo_path');
            $table->string('moderation_status', 20)->default('published')->after('source')->index();
            $table->timestamp('privacy_consented_at')->nullable();
            $table->timestamp('marketing_consented_at')->nullable();
            $table->string('submission_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('review_invitation_id');
            $table->dropColumn(['customer_email', 'profile_photo_path', 'source', 'moderation_status', 'privacy_consented_at', 'marketing_consented_at', 'submission_ip']);
        });
        Schema::dropIfExists('review_invitations');
    }
};
