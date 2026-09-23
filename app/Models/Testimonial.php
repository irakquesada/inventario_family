<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    protected $fillable = ['review_invitation_id', 'customer_name', 'customer_email', 'rating', 'comment', 'profile_photo_path', 'source', 'moderation_status', 'privacy_consented_at', 'marketing_consented_at', 'submission_ip', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer', 'privacy_consented_at' => 'datetime', 'marketing_consented_at' => 'datetime'];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ReviewInvitation::class, 'review_invitation_id');
    }
}
