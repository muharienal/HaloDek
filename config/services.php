<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * Notifikasi WhatsApp laporan baru untuk manager.
     * driver: off | log | fonnte | wablas. Nomor tujuan dipisah koma (format 628xxxx).
     */
    'whatsapp' => [
        'driver' => env('WA_DRIVER', 'off'),
        'to' => env('WA_TO', ''),
        'token' => env('WA_TOKEN', ''),
        'secret' => env('WA_SECRET', ''), // khusus Wablas bila akun memakai secret key
        'wablas_url' => env('WABLAS_URL', 'https://pati.wablas.com'), // server sesuai akun Wablas Anda
        'timeout' => 8,
        // Manajer (WA_TO): "harian" = satu ringkasan per hari kerja; "setiap" = tiap laporan baru; "off" = tidak dikirimi.
        'manager_mode' => env('WA_MANAJER', 'harian'),
        'digest_at' => env('WA_RINGKASAN_JAM', '16:00'),
        // Laporan baru dengan prioritas ini langsung dikirim ke WA Kepala Regu regu tujuannya.
        'head_priorities' => array_values(array_filter(array_map('trim', explode(',', (string) env('WA_KEPALA_PRIORITAS', 'tinggi,mendesak'))))),
    ],

    /*
     * Inventaris Umum & IT: lokasi dan barang diambil dari sini lewat API.
     * Bila tidak tersedia, HaloDek tetap berjalan (pilihan lokasi/barang kosong).
     */
    'inventaris' => [
        'url' => rtrim((string) env('INVENTARIS_URL', ''), '/'),
        'token' => env('INVENTARIS_API_TOKEN', ''),
        'timeout' => 4,
    ],

];
