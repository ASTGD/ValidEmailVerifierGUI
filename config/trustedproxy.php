<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | When running behind a reverse proxy (e.g. load balancer, ngrok), Laravel
    | must trust the proxy's forwarded headers to correctly determine the
    | request scheme/host. This is required for features like signed URLs.
    |
    | Set TRUSTED_PROXIES in your .env, for example:
    | - "*" (trust the calling proxy)
    | - "10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"
    |
    */
    'proxies' => env('TRUSTED_PROXIES'),
];

