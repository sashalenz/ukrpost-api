<?php

declare(strict_types=1);

return [
    'sandbox' => (bool) env('UKRPOST_SANDBOX', false),
    'urls' => [
        'production' => [
            'ecom' => 'https://www.ukrposhta.ua/ecom/0.0.1/',
            'forms' => 'https://www.ukrposhta.ua/forms/ecom/0.0.1/',
            'status_tracking' => 'https://www.ukrposhta.ua/status-tracking/0.0.1/',
            'classifier' => 'https://www.ukrposhta.ua/address-classifier-ws/',
        ],
        'sandbox' => [
            'ecom' => 'https://dev.ukrposhta.ua/ecom/0.0.1/',
            'forms' => 'https://dev.ukrposhta.ua/forms/ecom/0.0.1/',
            'status_tracking' => 'https://dev.ukrposhta.ua/status-tracking/0.0.1/',
            'classifier' => 'https://www.ukrposhta.ua/address-classifier-ws/',
        ],
    ],
    'credentials' => [
        'bearer_ecom' => env('UKRPOST_BEARER_ECOM', ''),
        'counterparty_token' => env('UKRPOST_COUNTERPARTY_TOKEN', ''),
        'bearer_status_tracking' => env('UKRPOST_BEARER_STATUS_TRACKING'),
        'counterparty_uuid' => env('UKRPOST_COUNTERPARTY_UUID'),
    ],
    'timeout' => (int) env('UKRPOST_TIMEOUT', 15),
    'retry_times' => (int) env('UKRPOST_RETRY_TIMES', 3),
    'retry_sleep' => (int) env('UKRPOST_RETRY_SLEEP', 200),
    'cache_ttl' => (int) env('UKRPOST_CACHE_TTL', 3600),
    'event_map' => [],
];
