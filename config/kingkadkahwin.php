<?php

return [
    'deposit' => [
        'amount' => env('KKK_DEPOSIT_AMOUNT', '0.00'),
        'qr_image' => env('KKK_DEPOSIT_QR_IMAGE', 'images/payment/maybank-qr.jpg'),
    ],

    'balance' => [
        'amount' => env('KKK_BALANCE_AMOUNT', '0.00'),
        'qr_image' => env('KKK_BALANCE_QR_IMAGE', env('KKK_DEPOSIT_QR_IMAGE', 'images/payment/maybank-qr.jpg')),
    ],

    'social' => [
        'instagram' => env('KKK_SOCIAL_INSTAGRAM', 'https://www.instagram.com/kingkadkahwin/'),
        'facebook' => env('KKK_SOCIAL_FACEBOOK', 'https://www.facebook.com/stickingbyking/'),
        'tiktok' => env('KKK_SOCIAL_TIKTOK'),
        'whatsapp' => env('KKK_SOCIAL_WHATSAPP', 'https://wa.me/60187716968'),
    ],

    'photoshop' => [
        'executable' => env(
            'KKK_PHOTOSHOP_EXECUTABLE',
            'C:\\Program Files\\Adobe\\Adobe Photoshop 2026\\Photoshop.exe'
        ),
        'script' => env(
            'KKK_PHOTOSHOP_SCRIPT',
            base_path('photoshop/auto_kad_full_qr_patched_v11_side_aware.jsx')
        ),
        'script_sha256' => 'e78bf94ae791ab5affb3b273b97714e3da2c0ff0987962a90fee3bf2a8372d39',
    ],
];
