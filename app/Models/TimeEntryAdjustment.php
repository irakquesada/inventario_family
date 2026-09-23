<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntryAdjustment extends Model
{
    protected $fillable = ['time_entry_id', 'user_id', 'previous_clocked_in_at', 'previous_clocked_out_at', 'new_clocked_in_at', 'new_clocked_out_at', 'reason', 'ip_address'];

    protected function casts(): array
    {
        return [
            'previous_clocked_in_at' => 'datetime', 'previous_clocked_out_at' => 'datetime',
            'new_clocked_in_at' => 'datetime', 'new_clocked_out_at' => 'datetime',
        ];
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
