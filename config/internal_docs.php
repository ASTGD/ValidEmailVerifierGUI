<?php

return [
    'title' => 'Operations Documentation',
    'sections' => [
        'platform' => [
            'label' => 'Platform Overview',
            'pages' => [
                'overview' => [
                    'title' => 'Platform Overview',
                    'path' => 'docs/OPS_PLATFORM_OVERVIEW.md',
                ],
            ],
        ],
        'laravel' => [
            'label' => 'Laravel Admin Operations',
            'pages' => [
                'admin-ops' => [
                    'title' => 'Laravel Admin Operations',
                    'path' => 'docs/LARAVEL_ADMIN_OPERATIONS.md',
                ],
            ],
        ],
        'horizon' => [
            'label' => 'Horizon Queue Operations',
            'pages' => [
                'queue-operations' => [
                    'title' => 'Horizon Queue Operations',
                    'path' => 'docs/HORIZON_QUEUE_OPERATIONS.md',
                ],
            ],
        ],
        'go' => [
            'label' => 'Go Control Plane Operations',
            'pages' => [
                'runtime-settings' => [
                    'title' => 'Go Runtime Settings Reference',
                    'path' => 'docs/GO_RUNTIME_SETTINGS_REFERENCE.md',
                ],
                'tuning-playbook' => [
                    'title' => 'Go Tuning Playbook',
                    'path' => 'docs/GO_TUNING_PLAYBOOK.md',
                ],
            ],
        ],
        'sg6' => [
            'label' => 'SG6 Operations',
            'pages' => [
                'seed-send-operations' => [
                    'title' => 'SG6 Seed-Send Operations',
                    'path' => 'docs/SG6_OPERATIONS.md',
                ],
            ],
        ],
        'runbooks' => [
            'label' => 'Runbooks and Drills',
            'pages' => [
                'ops-runbooks' => [
                    'title' => 'Ops Runbooks and Drills',
                    'path' => 'docs/OPS_RUNBOOKS_DRILLS.md',
                ],
            ],
        ],
        'releases' => [
            'label' => 'Release Notes (Ops)',
            'pages' => [
                'change-log' => [
                    'title' => 'Operational Change Log',
                    'path' => 'docs/OPS_CHANGELOG.md',
                ],
            ],
        ],
    ],
];
