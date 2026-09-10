<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingJobEvent extends Model
{
    protected $fillable = [
        'packing_job_id',
        'event_type',
        'from_status',
        'to_status',
        'actor_user_id',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function packingJob()
    {
        return $this->belongsTo(PackingJob::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
