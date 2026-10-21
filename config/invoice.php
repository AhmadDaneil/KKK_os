<?php

return [
    'business_unit_prefix' => env('INVOICE_BUSINESS_UNIT_PREFIX', 'KKK'),
    'business_name' => env('INVOICE_BUSINESS_NAME', 'MASSA EMPIRE'),
    'registration_number' => env('INVOICE_REGISTRATION_NUMBER') ?: 'TR0271374-H',
    'business_address' => env('INVOICE_BUSINESS_ADDRESS') ?: '3 & 3A, Jalan Malinja 3, Taman Malinja Bukit Baru, 75150 Melaka',
    'business_email' => env('INVOICE_BUSINESS_EMAIL') ?: 'massaempirehq@gmail.com',
    'business_phone' => env('INVOICE_BUSINESS_PHONE'),
    'payment_bank' => env('INVOICE_PAYMENT_BANK') ?: 'MAYBANK',
    'payment_account_name' => env('INVOICE_PAYMENT_ACCOUNT_NAME') ?: 'MASSA EMPIRE',
    'payment_account_number' => env('INVOICE_PAYMENT_ACCOUNT_NUMBER') ?: '569963039295',
    'payment_instruction' => env('INVOICE_PAYMENT_INSTRUCTION') ?: 'Please send proof of payment to your PIC.',
];
