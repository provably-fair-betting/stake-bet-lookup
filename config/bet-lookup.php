<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stake API URL
    |--------------------------------------------------------------------------
    | The GraphQL endpoint for Stake.games.
    */
    'stake_api_url' => env('STAKE_API_URL', 'https://stake.games/_api/graphql'),

    /*
    |--------------------------------------------------------------------------
    | Stake Access Token (Optional)
    |--------------------------------------------------------------------------
    | The x-access-token used to authenticate with the Stake.games API.
    | Not required if clearance cookie and user agent are provided.
    | Store this securely in your .env file — never expose it to the frontend.
    */
    'stake_access_token' => env('STAKE_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | Maximum number of bet lookup requests allowed per minute per IP.
    */
    'rate_limit' => env('BET_LOOKUP_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    | Timeout in seconds for outbound requests to the Stake.games API.
    */
    'timeout' => env('BET_LOOKUP_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Clearance Alert Settings
    |--------------------------------------------------------------------------
    | Configuration for clearance expiry alerts and notifications.
    */
    'clearance_alert_email' => env('STAKE_CLEARANCE_ALERT_EMAIL'),
    'clearance_alert_slack_webhook' => env('STAKE_CLEARANCE_ALERT_SLACK_WEBHOOK'),
    'clearance_warning_threshold' => env('STAKE_CLEARANCE_WARNING_THRESHOLD', 3600), // Alert 1 hour before expiry

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    | The database connection name to use for the stake_clearance table.
    | Defaults to the application's default connection (typically 'mysql').
    | Override this if the consuming app uses multiple connections.
    */
    'db_connection' => env('BET_LOOKUP_DB_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Admin Token
    |--------------------------------------------------------------------------
    | Token used to authenticate admin API endpoints.
    | Generate with: openssl rand -hex 32
    */
    'admin_token' => env('STAKE_ADMIN_TOKEN'),
];
