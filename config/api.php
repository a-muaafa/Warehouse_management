<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Integration Tokens (SHA-256 Hashed Fallback)
    |--------------------------------------------------------------------------
    |
    | Primary authentication is handled via Laravel Sanctum personal access tokens
    | with explicit abilities ('read', 'write') and expiration.
    |
    | If static integration tokens are configured, store their SHA-256 hash in .env
    | (API_TOKEN_ERP_HASH / API_TOKEN_READONLY_HASH) and compare with hash_equals().
    |
    */

    'tokens' => array_filter([
        (env('API_TOKEN_ERP_HASH') || env('API_TOKEN_ERP')) ? [
            'hash' => env('API_TOKEN_ERP_HASH') ?: hash('sha256', (string) env('API_TOKEN_ERP')),
            'scope' => 'write',
            'name' => 'ERP Integration',
            'expires_at' => env('API_TOKEN_ERP_EXPIRES_AT'),
        ] : null,
        (env('API_TOKEN_READONLY_HASH') || env('API_TOKEN_READONLY')) ? [
            'hash' => env('API_TOKEN_READONLY_HASH') ?: hash('sha256', (string) env('API_TOKEN_READONLY')),
            'scope' => 'read',
            'name' => 'Read-Only Analytics',
            'expires_at' => env('API_TOKEN_READONLY_EXPIRES_AT'),
        ] : null,
    ]),

];
