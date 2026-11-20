<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Duitku Merchant Credentials
    |--------------------------------------------------------------------------
    |
    | Dapatkan Kode Merchant dan API Key dari Dashboard Duitku:
    | Sandbox: https://sandbox.duitku.com
    | Production: https://passport.duitku.com
    |
    */
    'merchant_code' => env('DUITKU_MERCHANT_CODE', ''),
    'api_key'       => env('DUITKU_API_KEY', env('DUITKU_MERCHANT_KEY', '')),

    /*
    |--------------------------------------------------------------------------
    | Environment Mode
    |--------------------------------------------------------------------------
    |
    | True = Sandbox Mode (testing), False = Production Mode (live).
    |
    */
    'sandbox' => env('DUITKU_SANDBOX', env('DUITKU_SANDBOX_MODE', true)),

    /*
    |--------------------------------------------------------------------------
    | URLs & Endpoints
    |--------------------------------------------------------------------------
    |
    | Callback URL: Duitku akan mengirim notifikasi webhook HTTP POST ke URL ini.
    | Return URL: User akan diarahkan kembali ke URL ini setelah membayar di Duitku.
    | Expiry Period: Masa kedaluwarsa invoice dalam hitungan menit (default 60 menit).
    |
    */
    'callback_url'  => env('DUITKU_CALLBACK_URL'),
    'return_url'    => env('DUITKU_RETURN_URL'),
    'expiry_period' => (int) env('DUITKU_EXPIRY_PERIOD', 60),

    /*
    | Base endpoints Duitku
    */
    'endpoints' => [
        'sandbox_pop'  => 'https://api-sandbox.duitku.com',
        'prod_pop'     => 'https://api-prod.duitku.com',
        'sandbox_api'  => 'https://sandbox.duitku.com',
        'prod_api'     => 'https://passport.duitku.com',
    ],
];
