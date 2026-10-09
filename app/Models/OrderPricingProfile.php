<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPricingProfile extends Model
{
    protected $fillable = [
        'code',
        'name',
        'currency',
        'deposit_method',
        'deposit_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'deposit_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
