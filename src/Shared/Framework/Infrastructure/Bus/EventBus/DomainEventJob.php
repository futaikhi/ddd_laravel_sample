<?php

declare(strict_types=1);

namespace Src\Shared\Framework\Infrastructure\Bus\EventBus;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Shared\Framework\Domain\Events\DomainEvent;

final class DomainEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var int|array<int>
     */
    public int|array $backoff = [60, 120];

    public function __construct(
        public DomainEvent $event,
    ) {
    }

    public function handle(): void
    {
        event($this->event);
    }
}
