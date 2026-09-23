<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = ['reference', 'reservation_resource_id', 'created_by', 'cancelled_by', 'customer_name', 'customer_email', 'customer_phone', 'party_size', 'starts_at', 'ends_at', 'status', 'source', 'notes', 'cancelled_at'];

    protected function casts(): array
    {
        return ['party_size' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ReservationResource::class, 'reservation_resource_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
