<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationResource extends Model
{
    protected $fillable = ['name', 'code', 'capacity', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean'];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
