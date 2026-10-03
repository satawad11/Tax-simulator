<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllowanceType extends Model
{
    protected $table = 'allowance_types';

    protected $fillable = ['code', 'name', 'category', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function allowanceRules(): HasMany
    {
        return $this->hasMany(AllowanceRule::class, 'allowance_type_id');
    }

    public function returnAllowances(): HasMany
    {
        return $this->hasMany(TaxReturnAllowance::class, 'allowance_type_id');
    }
}
