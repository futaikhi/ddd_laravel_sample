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

    public function test_domain_event_job_uses_configurable_retry_policy(): void
    {
        config()->set('sales.events.retry.tries', 5);
        config()->set('sales.events.retry.backoff', [10, 30, 90]);
        config()->set('sales.events.retry.max_exceptions', 4);

        $job = new DomainEventJob(new SaleConfirmedEvent('sale-1', '2026-09-02 10:00:00'));

        $this->assertSame(5, $job->tries);
        $this->assertSame([10, 30, 90], $job->backoff);
        $this->assertSame(4, $job->maxExceptions);
    }
}
