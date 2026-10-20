<?php

namespace App\Services\Orders;

class DetectWabotBookingIntentService
{
    private const KEYWORDS = [
        'book',
        'booking',
        'reserve',
        'tempah',
        'tempahan',
        'nak order',
        'want to order',
        'pay deposit',
        'bayar deposit',
        'confirm order',
        'sahkan tempahan',
    ];

    public function detects(string $message): bool
    {
        foreach (self::KEYWORDS as $keyword) {
            if (preg_match('/(?<![\pL\pN])'.preg_quote($keyword, '/').'(?![\pL\pN])/iu', $message) === 1) {
                return true;
            }
        }

        return false;
    }
}
