<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceCatalogItem extends Model
{
    protected $fillable = [
        'code',
        'item_type',
        'name',
        'description',
        'calculation_method',
        'unit_price',
        'unit_label',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
