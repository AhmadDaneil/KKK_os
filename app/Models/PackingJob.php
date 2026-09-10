<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingJob extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'assigned_user_id',
        'assigned_at',
        'started_at',
        'packed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'packed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(PackingJobItem::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function events()
    {
        return $this->hasMany(PackingJobEvent::class);
    }
    public function fulfilmentJob()
    {
        return $this->hasOne(\App\Models\FulfilmentJob::class);
    }
}
