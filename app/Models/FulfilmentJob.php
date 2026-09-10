<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfilmentJob extends Model
{
    protected $fillable = [
        'order_id',
        'order_fulfilment_id',
        'packing_job_id',
        'method',
        'status',
        'courier_provider',
        'tracking_number',
        'completion_reference',
        'internal_note',
        'shipped_at',
        'delivered_at',
        'collected_at',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderFulfilment()
    {
        return $this->belongsTo(OrderFulfilment::class);
    }

    public function packingJob()
    {
        return $this->belongsTo(PackingJob::class);
    }

    public function events()
    {
        return $this->hasMany(FulfilmentJobEvent::class);
    }
}
