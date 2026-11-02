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
    ],

];
