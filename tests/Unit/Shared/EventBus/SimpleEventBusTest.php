<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\EventBus;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\DomainEventJob;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\SimpleEventBus;
use Tests\TestCase;

final class SimpleEventBusTest extends TestCase
{
    public function test_it_dispatches_domain_events_synchronously_by_default(): void
    {
        Event::fake();
        config()->set('sales.events.dispatch_mode', 'sync');

        $event = new SaleConfirmedEvent('sale-1', '2026-09-02 10:00:00');

        (new SimpleEventBus())->publishEvents([$event]);

        Event::assertDispatched(SaleConfirmedEvent::class);
    }

    public function test_it_dispatches_domain_events_asynchronously_when_configured(): void
    {
        Bus::fake();
        Event::fake();
        config()->set('sales.events.dispatch_mode', 'async');
        config()->set('sales.events.queue', 'domain-events');

        $event = new SaleConfirmedEvent('sale-1', '2026-09-02 10:00:00');

        (new SimpleEventBus())->publishEvents([$event]);

        Bus::assertDispatched(DomainEventJob::class, static function (DomainEventJob $job): bool {
            return $job->event instanceof SaleConfirmedEvent
                && $job->queue === 'domain-events';
        });

        Event::assertNotDispatched(SaleConfirmedEvent::class);
    }
}
