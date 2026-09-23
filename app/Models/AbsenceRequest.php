<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceRequest extends Model
{
    protected $fillable = ['employee_id', 'absence_type_id', 'starts_on', 'ends_on', 'starts_at', 'ends_at', 'is_full_day', 'requested_days', 'status', 'reason', 'attachment_path', 'attachment_name', 'reviewed_by', 'reviewed_at', 'review_comment', 'cancelled_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_full_day' => 'boolean', 'requested_days' => 'decimal:2', 'reviewed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class, 'absence_type_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
