<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingJobItem extends Model
{
    protected $fillable = [
        'packing_job_id',
        'print_job_id',
        'order_package_side_id',
        'side',
        'verified_present',
        'verified_at',
    ];

    protected $casts = [
        'verified_present' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function packingJob()
    {
        return $this->belongsTo(PackingJob::class);
    }

    public function printJob()
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function packageSide()
    {
        return $this->belongsTo(OrderPackageSide::class, 'order_package_side_id');
    }
}
