<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    protected $fillable = ['weekday', 'opens_at', 'closes_at', 'is_closed'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'is_closed' => 'boolean'];
    }
}
