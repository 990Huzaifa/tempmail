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

    'namecheap' => [
        'user' => env('NAMECHEAP_API_USER'),
        'key' => env('NAMECHEAP_API_KEY'),
        'ip' => env('NAMECHEAP_CLIENT_IP'),
        'base_url' => env('NAMECHEAP_BASE_URL'),
    ],

    'modoboa' => [
        'token' => env('MODOBOA_TOKEN')
    ],

    'mail_hook' => [
        'api_key' => env('MAIL_HOOK_KEY')
    ],

    'google' => [
        'android_package_name' => env('GOOGLE_ANDROID_PACKAGE_NAME')
    ]

];
