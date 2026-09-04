<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Events\SaleCancelledEvent;

/**
 * Notification side effect for cancelled sales.
 *
 * Mirrors SendConfirmationEmailHandler: the handler stays outside the
 * cancellation command handler so the aggregate remains focused on
 * state transitions. A real mail integration can replace this handler
 * without touching the Sales domain model.
 */
final readonly class SendCancellationEmailHandler
{
    public function handle(SaleCancelledEvent $event): void
    {
        Log::info('Sale cancellation email queued', [
            'sale_id' => $event->saleId,
            'reason' => $event->reason,
            'cancelled_at' => $event->cancelledAt,
        ]);
    }
}
