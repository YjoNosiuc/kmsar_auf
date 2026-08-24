<?php

return [
    'completed_statuses' => [
        'completed_unpublished',
        'presented_internal',
        'presented_external',
        'published_non_indexed',
        'published_scopus',
        'patent_granted',
    ],
    'in_progress_statuses' => [
        'proposal',
        'ongoing',
    ],

    /*
    | Idle warning in the authenticated layout. Server session lifetime is
    | separate (SESSION_LIFETIME, default 480 minutes). The modal must appear
    | before logout — never silently expire a hidden/background tab.
    */
    'idle_timeout_minutes' => max(1, (int) env('KMSAR_IDLE_TIMEOUT_MINUTES', 2)),
    'idle_countdown_seconds' => max(10, (int) env('KMSAR_IDLE_COUNTDOWN_SECONDS', 30)),
];
