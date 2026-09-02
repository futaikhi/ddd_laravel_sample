<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Events\SaleCompletedEvent;

/**
 * Updates commission reporting projection after a sale completion.
 *
 * The aggregate already locks the commission on completion. This handler reacts
 * to the completion event and prepares reporting data as a side effect.
 */
final readonly class CalculateCommissionHandler
{
    public function handle(SaleCompletedEvent $event): void
    {
        $date = substr($event->completedAt, 0, 10) ?: date('Y-m-d');
        $prefix = "sales.commission.{$date}";

        Cache::increment("{$prefix}.completed_sales_count");
        Cache::increment("{$prefix}.commission_total", $event->commissionAmount);
        Cache::put("{$prefix}.currency", $event->commissionCurrency);

        Log::info('Commission projection updated', [
            'sale_id' => $event->saleId,
            'date' => $date,
            'commission_amount' => $event->commissionAmount,
            'commission_rate' => $event->commissionRate,
            'currency' => $event->commissionCurrency,
        ]);
    }
}
