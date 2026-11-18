<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalInvitation extends Model
{
    protected $fillable = ['token_hash', 'label', 'created_by', 'max_submissions', 'submissions_count', 'expires_at', 'revoked_at', 'last_used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture() && $this->submissions_count < $this->max_submissions;
    }
}
