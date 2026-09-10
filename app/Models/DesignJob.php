<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignJob extends Model
{
    protected $fillable = [
        'merge_job_id','order_id','order_package_side_id','side','status',
        'assigned_user_id','assigned_at','started_at','design_ready_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'design_ready_at' => 'datetime',
    ];

    public function mergeJob() { return $this->belongsTo(MergeJob::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function packageSide() { return $this->belongsTo(OrderPackageSide::class, 'order_package_side_id'); }
    public function assignedUser() { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function artworkVersions() { return $this->hasMany(ArtworkVersion::class); }
    public function events() { return $this->hasMany(DesignJobEvent::class); }
    public function reviewActions()
    {
        return $this->hasMany(\App\Models\ArtworkReviewAction::class);
    }
    public function printJob()
    {
        return $this->hasOne(\App\Models\PrintJob::class);
    }
}
