<?php

return [
    'enabled' => (bool) env('QUEUE_RECOVERY_ENABLED', true),

    'max_replay_per_run' => (int) env('QUEUE_RECOVERY_MAX_REPLAY_PER_RUN', 100),

    'default_window_hours' => (int) env('QUEUE_RECOVERY_DEFAULT_WINDOW_HOURS', 24),

    'allow_strategies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QUEUE_RECOVERY_ALLOW_STRATEGIES', 'requeue_failed'))
    ))),

    'allow_lanes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QUEUE_RECOVERY_ALLOW_LANES', 'default,prepare,parse,smtp_probe,finalize,imports,cache_writeback'))
    ))),
];
