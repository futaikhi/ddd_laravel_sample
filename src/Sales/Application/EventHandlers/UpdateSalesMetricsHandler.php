<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Events\SaleCompletedEvent;

/**
 * Updates lightweight sales metrics projection after completion events.
 */
final readonly class UpdateSalesMetricsHandler
{
    public function handle(SaleCompletedEvent $event): void
    {
        $date = substr($event->completedAt, 0, 10) ?: date('Y-m-d');
        $prefix = "sales.metrics.{$date}";

        Cache::increment("{$prefix}.completed_count");
        Cache::increment("{$prefix}.revenue_total", $event->totalAmount);

        Log::info('Sales metrics projection updated', [
            'sale_id' => $event->saleId,
            'date' => $date,
            'revenue_total_delta' => $event->totalAmount,
        ]);
    }
}
