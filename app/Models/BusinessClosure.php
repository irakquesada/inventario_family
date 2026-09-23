<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessClosure extends Model
{
    protected $fillable = ['date', 'is_closed', 'opens_at', 'closes_at', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_closed' => 'boolean'];
    }
}
