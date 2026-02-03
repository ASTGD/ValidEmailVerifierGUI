<?php

return [
    'enabled' => (bool) env('DEVTOOLS_ENABLED', false),
    'allowed_environments' => array_values(array_filter(array_map(
        static fn (string $env): string => trim($env),
        explode(',', (string) env('DEVTOOLS_ENVIRONMENTS', 'local,staging'))
    ))),
];
