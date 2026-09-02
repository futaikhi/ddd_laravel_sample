<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Illuminate\Support\Facades\Log;
use Src\Shared\Framework\Domain\Events\DomainEvent;
use Src\Shared\Framework\Domain\Events\DomainEventStoreInterface;

/**
 * Persists every Sales domain event as an append-only audit trail.
 */
final readonly class LogAuditTrailHandler
{
    public function __construct(
        private DomainEventStoreInterface $eventStore,
    ) {
    }

    public function handle(DomainEvent $event): void
    {
        $this->eventStore->append($event);

        Log::info('Sales domain event audited', [
            'event_name' => $event->getName(),
            'event_type' => $event::class,
            'occurred_on' => $event->occurredOn,
        ]);
    }
}
