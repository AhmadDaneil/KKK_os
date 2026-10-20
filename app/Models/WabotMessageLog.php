<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WabotMessageLog extends Model
{
    protected $fillable = [
        'order_id', 'payment_transaction_id', 'phone', 'message_type', 'status',
        'attempts', 'http_status', 'provider_response', 'error_code', 'requested_at', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payment()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
