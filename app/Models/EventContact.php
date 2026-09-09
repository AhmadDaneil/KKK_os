<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventContact extends Model
{
    protected $fillable = ['order_event_id', 'contact_number', 'contact_name', 'contact_phone'];
    public function event() { return $this->belongsTo(OrderEvent::class, 'order_event_id'); }
}
