<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderConfirmation extends Model
{
    protected $fillable = ['order_id', 'confirmed_at', 'confirmation_version', 'confirmed_snapshot'];
    protected $casts = ['confirmed_at' => 'datetime', 'confirmed_snapshot' => 'array'];
    public function order() { return $this->belongsTo(Order::class); }
}
