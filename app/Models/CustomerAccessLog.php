<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAccessLog extends Model
{
    protected $fillable = [
        'order_id',
        'attempted_reference',
        'phone_fingerprint',
        'result',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
