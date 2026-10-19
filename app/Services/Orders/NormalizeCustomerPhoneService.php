<?php

namespace App\Services\Orders;

use Illuminate\Validation\ValidationException;

class NormalizeCustomerPhoneService
{
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '01')) {
            $digits = '6'.$digits;
        }

        if (! preg_match('/^601\d{8,9}$/', $digits)) {
            throw ValidationException::withMessages([
                'customer_phone' => 'Invalid Malaysian phone number.',
            ]);
        }

        return $digits;
    }
}
