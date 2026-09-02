<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Src\Sales\Application\EventHandlers\CalculateCommissionHandler;
use Src\Sales\Application\EventHandlers\LogAuditTrailHandler;
use Src\Sales\Application\EventHandlers\SendConfirmationEmailHandler;
use Src\Sales\Application\EventHandlers\UpdateSalesMetricsHandler;
use Src\Sales\Domain\Events\SaleCancelledEvent;
use Src\Sales\Domain\Events\SaleCompletedEvent;
use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Sales\Domain\Events\SaleCreatedEvent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        SaleCreatedEvent::class => [
            LogAuditTrailHandler::class,
        ],
        SaleConfirmedEvent::class => [
            SendConfirmationEmailHandler::class,
            LogAuditTrailHandler::class,
        ],
        SaleCompletedEvent::class => [
            UpdateSalesMetricsHandler::class,
            CalculateCommissionHandler::class,
            LogAuditTrailHandler::class,
        ],
        SaleCancelledEvent::class => [
            LogAuditTrailHandler::class,
        ],
    ];
}
