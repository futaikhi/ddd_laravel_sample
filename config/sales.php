<?php

return [
    'events' => [
        /*
        |--------------------------------------------------------------------------
        | Sales Domain Event Dispatch Mode
        |--------------------------------------------------------------------------
        |
        | sync  : execute Laravel event listeners immediately.
        | async : dispatch one queued job per domain event.
        |
        */
        'dispatch_mode' => env('SALES_EVENT_DISPATCH_MODE', 'sync'),
        'queue' => env('SALES_EVENT_QUEUE', 'domain-events'),

        /*
        |--------------------------------------------------------------------------
        | Sales Domain Event Retry Policy
        |--------------------------------------------------------------------------
        |
        | Applies when dispatch_mode is async. Laravel queue will retry failed event
        | jobs using these values, then call DomainEventJob::failed() permanently.
        |
        */
        'retry' => [
            'tries' => (int) env('SALES_EVENT_RETRY_TRIES', 3),
            'backoff' => array_map('intval', explode(',', env('SALES_EVENT_RETRY_BACKOFF', '60,120,300'))),
            'max_exceptions' => (int) env('SALES_EVENT_RETRY_MAX_EXCEPTIONS', 3),
        ],
    ],
];
