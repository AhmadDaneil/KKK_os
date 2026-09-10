<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = [
        'order_id',
        'design_job_id',
        'order_package_side_id',
        'artwork_version_id',
        'side',
        'status',
        'quantity',
        'assigned_user_id',
        'assigned_at',
        'started_at',
        'printed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function designJob()
    {
        return $this->belongsTo(DesignJob::class);
    }

    public function packageSide()
    {
        return $this->belongsTo(OrderPackageSide::class, 'order_package_side_id');
    }

    public function artworkVersion()
    {
        return $this->belongsTo(ArtworkVersion::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function events()
    {
        return $this->hasMany(PrintJobEvent::class);
    }
}
