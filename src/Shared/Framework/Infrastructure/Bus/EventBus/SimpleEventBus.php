<?php

declare(strict_types=1);

namespace Src\Shared\Framework\Infrastructure\Bus\EventBus;

use Src\Shared\Framework\Domain\Events\DomainEvent;

/**
 * Event bus dispatches domain events through Laravel event system.
 *
 * Dispatch mode is configurable with `sales.events.dispatch_mode`:
 * - `sync`: listeners execute in current request/job.
 * - `async`: one queue job is dispatched per domain event.
 */
final class SimpleEventBus implements EventBusInterface
{
    /**
     * @param  DomainEvent[]  $events
     */
    public function publishEvents(array $events): void
    {
        $async = config('sales.events.dispatch_mode', 'sync') === 'async';
        $queue = (string) config('sales.events.queue', 'domain-events');

        foreach ($events as $event) {
            if ($async) {
                DomainEventJob::dispatch($event)->onQueue($queue);

                continue;
            }

            event($event);
        }
    }
}
