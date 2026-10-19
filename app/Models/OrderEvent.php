<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    private const HIJRI_MONTH_NAMES = [
        'Muharram' => 'Muharam',
        'Rabi al-Awwal' => 'Rabiulawal',
        'Rabi al-Thani' => 'Rabiulakhir',
        'Jumada al-Awwal' => 'Jamadilawal',
        'Jumada al-Thani' => 'Jamadilakhir',
        'Rajab' => 'Rejab',
        'Shaban' => 'Syaaban',
        "Sha'ban" => 'Syaaban',
        'Shawwal' => 'Syawal',
        'Dhu al-Qadah' => 'Zulkaedah',
        "Dhu al-Qa'dah" => 'Zulkaedah',
        'Dhu al-Hijjah' => 'Zulhijjah',
    ];

    protected $fillable = [
        'order_package_side_id', 'day_name', 'event_date', 'hijri_date', 'meal_time', 'bersanding_time',
        'venue_name', 'full_address', 'google_maps_url',
    ];

    protected $casts = ['event_date' => 'date'];

    public function getHijriDateAttribute(?string $value): ?string
    {
        return $this->normalizeHijriMonthName($value);
    }

    public function setHijriDateAttribute(?string $value): void
    {
        $this->attributes['hijri_date'] = $this->normalizeHijriMonthName($value);
    }

    private function normalizeHijriMonthName(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_ireplace(
            array_keys(self::HIJRI_MONTH_NAMES),
            array_values(self::HIJRI_MONTH_NAMES),
            $value,
        );
    }

    public function packageSide()
    {
        return $this->belongsTo(OrderPackageSide::class, 'order_package_side_id');
    }

    public function contacts()
    {
        return $this->hasMany(EventContact::class);
    }
}
