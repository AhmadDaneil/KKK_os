<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Order extends Model
{
    use HasFactory;

    public const TERMINAL_OPERATIONAL_STATUSES = [
        'CANCELLED',
        'ARCHIVED',
    ];

    protected $fillable = [
        'order_id',
        'package_count',
        'customer_name',
        'customer_email',
        'customer_phone',
        'booking_payment_status',
        'status',
        'details_confirmed_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            if (! $order->isDirty('status')) {
                return;
            }

            $fromStatus = (string) $order->getOriginal('status');
            $toStatus = (string) $order->status;

            if (in_array($fromStatus, self::TERMINAL_OPERATIONAL_STATUSES, true) && $toStatus !== $fromStatus) {
                throw new RuntimeException(
                    "Order {$order->order_id} is {$fromStatus} and cannot transition to {$toStatus}."
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'package_count' => 'integer',
            'details_confirmed_at' => 'datetime',
            'card_quantity' => 'integer',
        ];
    }

    public function isTerminalOperationalStatus(): bool
    {
        return in_array($this->status, self::TERMINAL_OPERATIONAL_STATUSES, true);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(OrderAccessToken::class, 'order_fk');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class);
    }

    public function couples()
    {
        return $this->hasMany(\App\Models\OrderCouple::class);
    }

    public function packageSides()
    {
        return $this->hasMany(\App\Models\OrderPackageSide::class);
    }

    public function fulfilment()
    {
        return $this->hasOne(\App\Models\OrderFulfilment::class);
    }

    public function confirmation()
    {
        return $this->hasOne(\App\Models\OrderConfirmation::class);
    }

    public function mergeJobs()
    {
        return $this->hasMany(\App\Models\MergeJob::class);
    }

    public function designJobs()
    {
        return $this->hasMany(\App\Models\DesignJob::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\PaymentTransaction::class);
    }

    public function printJobs()
    {
        return $this->hasMany(\App\Models\PrintJob::class);
    }

    public function packingJob()
    {
        return $this->hasOne(\App\Models\PackingJob::class);
    }

    public function fulfilmentJob()
    {
        return $this->hasOne(\App\Models\FulfilmentJob::class);
    }
}
