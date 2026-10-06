<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPackageSide extends Model
{
    protected $fillable = ['order_id', 'side', 'additional_products'];

    protected $casts = [
        'additional_products' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function design()
    {
        return $this->hasOne(OrderDesign::class);
    }

    public function parents()
    {
        return $this->hasOne(OrderParent::class);
    }

    public function event()
    {
        return $this->hasOne(OrderEvent::class);
    }

    public function mergeJob()
    {
        return $this->hasOne(MergeJob::class);
    }

    public function designJob()
    {
        return $this->hasOne(DesignJob::class);
    }

    public function packingItem()
    {
        return $this->hasOne(PackingJobItem::class);
    }
}
