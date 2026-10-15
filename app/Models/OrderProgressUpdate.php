<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderProgressUpdate extends Model
{
    public const VISIBILITY_INTERNAL = 'INTERNAL';

    public const VISIBILITY_CUSTOMER = 'CUSTOMER';

    public const CUSTOMER_PUBLISHER_ROLES = [
        User::ROLE_DESIGNER,
        User::ROLE_PRODUCTION,
        User::ROLE_OM,
    ];

    protected $fillable = [
        'order_id',
        'uploader_user_id',
        'uploader_role',
        'order_status',
        'message',
        'visibility',
        'published_at',
        'attachment_disk',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'attachment_size',
        'attachment_sha256',
        'deduplication_key',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'attachment_size' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function scopeCustomerVisible(Builder $query): Builder
    {
        return $query
            ->where('visibility', self::VISIBILITY_CUSTOMER)
            ->whereNotNull('published_at')
            ->whereIn('uploader_role', self::CUSTOMER_PUBLISHER_ROLES);
    }

    public function isCustomerVisible(): bool
    {
        return $this->visibility === self::VISIBILITY_CUSTOMER
            && $this->published_at !== null
            && in_array($this->uploader_role, self::CUSTOMER_PUBLISHER_ROLES, true);
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_disk) && filled($this->attachment_path);
    }
}
