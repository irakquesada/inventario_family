<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppModule extends Model
{
    protected $fillable = ['key', 'enabled', 'changed_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
