<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingJob extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'assigned_user_id',
        'assigned_at',
        'started_at',
        'packed_at',
        'proof_storage_path',
        'proof_original_name',
        'proof_mime_type',
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

    public function proofs(): HasMany
    {
        return $this->hasMany(PackingJobProof::class)->oldest('id');
    }

    public function fulfilmentJob()
    {
        return $this->hasOne(FulfilmentJob::class);
    }
}
