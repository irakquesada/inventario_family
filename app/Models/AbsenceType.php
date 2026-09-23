<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenceType extends Model
{
    protected $fillable = ['name', 'code', 'color', 'deducts_balance', 'requires_attachment', 'is_paid', 'is_active'];

    protected function casts(): array
    {
        return ['deducts_balance' => 'boolean', 'requires_attachment' => 'boolean', 'is_paid' => 'boolean', 'is_active' => 'boolean'];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }
}
