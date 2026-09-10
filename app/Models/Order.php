<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'package_count' => 'integer',
            'details_confirmed_at' => 'datetime',
        ];
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(OrderAccessToken::class, 'order_fk');
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
