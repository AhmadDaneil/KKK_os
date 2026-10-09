<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPricingSnapshot extends Model
{
    protected $fillable = [
        'order_id',
        'pricing_profile_id',
        'currency',
        'subtotal',
        'postage_amount',
        'discount_amount',
        'total_amount',
        'booking_amount',
        'paid_amount',
        'outstanding_amount',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'postage_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'booking_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'calculation_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pricingProfile(): BelongsTo
    {
        return $this->belongsTo(OrderPricingProfile::class);
    }
}
