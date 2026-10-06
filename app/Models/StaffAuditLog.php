<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAuditLog extends Model
{
    protected $fillable = [
        'actor_user_id',
        'target_user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
