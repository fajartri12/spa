<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Kredensial tidak boleh ditulis di source code (AGENTS.MD bagian 9).
    | Seluruh nilai sensitif diambil dari environment. Bila kredensial kosong,
    | aplikasi tetap berjalan dengan verifikasi manual seperti sebelumnya.
    |
    */

    'default' => env('PAYMENT_GATEWAY_DEFAULT', ''),

    'providers' => [

        'tripay' => [
            'enabled' => (bool) env('TRIPAY_ENABLED', false),
            'mode' => env('TRIPAY_MODE', 'sandbox'),
            'base_url' => env('TRIPAY_BASE_URL', 'https://tripay.co.id/api-sandbox'),
            'api_key' => env('TRIPAY_API_KEY'),
            'private_key' => env('TRIPAY_PRIVATE_KEY'),
            'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
            'callback_url' => env('TRIPAY_CALLBACK_URL'),
            // Kanal yang ditawarkan: VA bank dan QRIS.
            'channels' => [
                'BRIVA' => 'BRI Virtual Account',
                'BNIVA' => 'BNI Virtual Account',
                'BSIVA' => 'BSI Virtual Account',
                'QRIS' => 'QRIS',
            ],
        ],

        'midtrans' => [
            'enabled' => (bool) env('MIDTRANS_ENABLED', false),
            'mode' => env('MIDTRANS_MODE', 'sandbox'),
            'base_url' => env('MIDTRANS_BASE_URL', 'https://api.sandbox.midtrans.com'),
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
            'finish_url' => env('MIDTRANS_FINISH_URL'),
            'channels' => [
                'bri_va' => 'BRI Virtual Account',
                'bni_va' => 'BNI Virtual Account',
                'bsi_va' => 'BSI Virtual Account',
                'qris' => 'QRIS',
            ],
        ],

        // Driver pengujian: tidak melakukan panggilan keluar, memakai pola
        // yang sama dengan penyedia asli sehingga alur webhook tetap teruji.
        'fake' => [
            'enabled' => (bool) env('PAYMENT_GATEWAY_FAKE_ENABLED', false),
            'channels' => [
                'va' => 'Virtual Account (simulator)',
                'qris' => 'QRIS (simulator)',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Masa Berlaku Tagihan Digital
    |--------------------------------------------------------------------------
    |
    | Durasi (jam) VA/QRIS sebelum kedaluwarsa. Gateway umumnya membatasi
    | maksimal 24 jam untuk Tripay dan 24 jam untuk Midtrans VA.
    |
    */

    'expiry_hours' => (int) env('PAYMENT_GATEWAY_EXPIRY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Toleransi Nominal Unik
    |--------------------------------------------------------------------------
    |
    | Untuk rekonsiliasi tanpa kode referensi, sistem menambahkan nominal unik
    | tiga digit terakhir pada tagihan agar mutasi dapat dicocokkan. Aktifkan
    | hanya bila bank tujuan mendukung transfer dengan nominal tidak bulat.
    |
    */

    'reconciliation' => [
        'unique_amount_enabled' => (bool) env('RECONCILIATION_UNIQUE_AMOUNT', false),
        'date_tolerance_days' => (int) env('RECONCILIATION_DATE_TOLERANCE_DAYS', 7),
    ],

];
