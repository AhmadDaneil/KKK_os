<?php

return [
    'enabled' => env('CUSTOMER_EMAIL_REMINDERS_ENABLED', false),
    'mailer' => 'customer-reminders',
    'from_address' => env('CUSTOMER_EMAIL_FROM_ADDRESS', 'kingkadkahwinhq@gmail.com'),
    'from_name' => env('CUSTOMER_EMAIL_FROM_NAME', 'King Kad Kahwin'),
    'details_after_hours' => 24,
    'balance_after_hours' => 72,
];
