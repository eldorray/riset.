<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // Pencarian referensi (API resmi). Isi email agar masuk "polite pool" Crossref/OpenAlex.
    'crossref' => [
        'mailto' => env('CROSSREF_MAILTO'),
    ],

    'openalex' => [
        'mailto' => env('OPENALEX_MAILTO'),
        'api_key' => env('OPENALEX_API_KEY'),
    ],

    // Tanpa kunci, batas permintaan Semantic Scholar dibagi bersama dan sering penuh.
    'semantic_scholar' => [
        'api_key' => env('SEMANTIC_SCHOLAR_API_KEY'),
    ],

    'scopus' => [
        'api_key' => env('SCOPUS_API_KEY'),
        'insttoken' => env('SCOPUS_INSTTOKEN'),
    ],

    // Layanan AI berformat OpenAI-compatible: POST {base_url}/chat/completions.
    'ai' => [
        'base_url' => env('AI_BASE_URL'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL'),
        'timeout' => (int) env('AI_TIMEOUT', 120),
        'json_mode' => (bool) env('AI_JSON_MODE', true),
    ],

];
