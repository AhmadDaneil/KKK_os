<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCouple extends Model
{
    protected $fillable = [
        'order_id', 'couple_number', 'groom_name', 'groom_abbreviation', 'bride_name', 'bride_abbreviation',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
