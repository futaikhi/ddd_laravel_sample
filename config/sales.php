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
    ],
];
