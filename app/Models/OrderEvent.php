<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    protected $fillable = [
        'order_package_side_id', 'day_name', 'event_date', 'hijri_date', 'meal_time', 'bersanding_time',
        'venue_name', 'full_address', 'google_maps_url',
    ];

    protected $casts = ['event_date' => 'date'];

    public function packageSide() { return $this->belongsTo(OrderPackageSide::class, 'order_package_side_id'); }
    public function contacts() { return $this->hasMany(EventContact::class); }
}
