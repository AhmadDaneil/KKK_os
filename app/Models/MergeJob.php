<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MergeJob extends Model
{
    protected $fillable = [
        'job_id',
        'order_id',
        'order_package_side_id',
        'side',
        'status',
        'payload_schema_version',
        'canonical_payload',
        'generated_at',
        'exported_at',
    ];

    protected $casts = [
        'canonical_payload' => 'array',
        'generated_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function packageSide()
    {
        return $this->belongsTo(OrderPackageSide::class, 'order_package_side_id');
    }
}
