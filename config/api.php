<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Integration Tokens
    |--------------------------------------------------------------------------
    |
    | Pre-shared tokens for external ERP / Accounting / third-party systems.
    | Each entry requires:
    |   - token: the raw secret (store in .env, never commit real tokens)
    |   - scope: 'read'  — may only query inventory data
    |             'write' — may also create transactions via external-sync
    |   - name: human-readable label for auditing / logging purposes
    |
    | Tokens are compared using hash_equals() to prevent timing attacks.
    |
    */

    'tokens' => array_filter([
        env('API_TOKEN_ERP') ? [
            'token' => env('API_TOKEN_ERP'),
            'scope' => 'write',
            'name' => 'ERP Integration',
        ] : null,
        env('API_TOKEN_READONLY') ? [
            'token' => env('API_TOKEN_READONLY'),
            'scope' => 'read',
            'name' => 'Read-Only Analytics',
        ] : null,
    ]),

];
