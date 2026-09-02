<?php

declare(strict_types=1);

namespace Src\Shared\Framework\Infrastructure\Bus\EventBus;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Shared\Framework\Domain\Events\DomainEvent;
use Throwable;

final class DomainEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /**
     * @var int|array<int>
     */
    public int|array $backoff;

    public int $maxExceptions;

    public function __construct(
        public DomainEvent $event,
    ) {
        $this->tries = (int) config('sales.events.retry.tries', 3);
        $this->backoff = $this->configuredBackoff();
        $this->maxExceptions = (int) config('sales.events.retry.max_exceptions', 3);
    }

    public function handle(): void
    {
        event($this->event);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Domain event job failed permanently', [
            'event_name' => $this->event->getName(),
            'event_type' => $this->event::class,
            'occurred_on' => $this->event->occurredOn,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * @return int|array<int>
     */
    private function configuredBackoff(): int|array
    {
        $backoff = config('sales.events.retry.backoff', [60, 120, 300]);

        if (is_array($backoff)) {
            return array_map('intval', $backoff);
        }

        if (is_string($backoff) && str_contains($backoff, ',')) {
            return array_map('intval', explode(',', $backoff));
        }

        return (int) $backoff;
    }
}
