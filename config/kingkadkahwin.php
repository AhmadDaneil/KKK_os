<?php

return [
    'deposit' => [
        'amount' => env('KKK_DEPOSIT_AMOUNT', '0.00'),
        'qr_image' => env('KKK_DEPOSIT_QR_IMAGE', 'images/payment/deposit-qr.png'),
    ],

    'social' => [
        'instagram' => env('KKK_SOCIAL_INSTAGRAM', 'https://www.instagram.com/kingkadkahwin/'),
        'facebook' => env('KKK_SOCIAL_FACEBOOK', 'https://www.facebook.com/stickingbyking/'),
        'tiktok' => env('KKK_SOCIAL_TIKTOK'),
        'whatsapp' => env('KKK_SOCIAL_WHATSAPP', 'https://wa.me/60187716968'),
    ],
];
