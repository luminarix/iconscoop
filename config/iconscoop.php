<?php

declare(strict_types=1);

return [
    'timeout' => env('FAVICON_FETCHER_TIMEOUT', 10),
    'user_agent' => env('FAVICON_FETCHER_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),

    'default_icon' => env('FAVICON_FETCHER_DEFAULT_ICON', 'default.png'),

    'cache' => [
        'enabled' => env('FAVICON_FETCHER_CACHE_ENABLED', false),
        'ttl' => env('FAVICON_FETCHER_CACHE_TTL', 86_400),
    ],
];
