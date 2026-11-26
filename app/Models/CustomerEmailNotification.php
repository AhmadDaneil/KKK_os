<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerEmailNotification extends Model
{
    public const TYPE_DETAILS_INCOMPLETE = 'DETAILS_INCOMPLETE';

    public const TYPE_BALANCE_DUE = 'BALANCE_DUE';

    public const TYPE_ORDER_STATUS = 'ORDER_STATUS';

    public const TYPE_PROGRESS_UPDATE = 'PROGRESS_UPDATE';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_SENT = 'SENT';

    public const STATUS_SKIPPED = 'SKIPPED';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'order_id',
        'order_progress_update_id',
        'event_type',
        'event_key',
        'status_to',
        'status',
        'attempts',
        'sent_at',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function progressUpdate(): BelongsTo
    {
        return $this->belongsTo(OrderProgressUpdate::class, 'order_progress_update_id');
    }
}
