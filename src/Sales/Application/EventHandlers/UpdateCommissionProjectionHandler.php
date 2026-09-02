<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Events\CommissionCalculatedEvent;

/**
 * Updates commission reporting projection from explicit commission events.
 */
final readonly class UpdateCommissionProjectionHandler
{
    public function handle(CommissionCalculatedEvent $event): void
    {
        $date = substr($event->calculatedAt, 0, 10) ?: date('Y-m-d');
        $prefix = "sales.commission.{$date}";

        Cache::increment("{$prefix}.completed_sales_count");
        Cache::increment("{$prefix}.commission_total", $event->amount);
        Cache::put("{$prefix}.currency", $event->currency);

        Log::info('Commission projection updated', [
            'sale_id' => $event->saleId,
            'date' => $date,
            'commission_amount' => $event->amount,
            'commission_rate' => $event->percentage,
            'currency' => $event->currency,
        ]);
    }
}
