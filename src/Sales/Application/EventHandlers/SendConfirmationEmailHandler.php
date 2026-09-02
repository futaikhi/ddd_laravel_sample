<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Events\SaleConfirmedEvent;

/**
 * Notification side effect for confirmed sales.
 *
 * Kept outside command handlers so confirmation orchestration remains focused
 * on changing aggregate state. Real mail integration can replace this handler
 * without changing the Sales domain model.
 */
final readonly class SendConfirmationEmailHandler
{
    public function handle(SaleConfirmedEvent $event): void
    {
        Log::info('Sale confirmation email queued', [
            'sale_id' => $event->saleId,
            'confirmed_at' => $event->confirmedAt,
        ]);
    }
}
