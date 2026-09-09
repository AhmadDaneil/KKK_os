<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFulfilment extends Model
{
    protected $fillable = ['order_id', 'method', 'recipient_name', 'recipient_phone', 'shipping_address'];
    public function order() { return $this->belongsTo(Order::class); }
}
