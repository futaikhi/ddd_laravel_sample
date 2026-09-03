<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Src\Sales\Application\EventHandlers\CalculateCommissionHandler;
use Src\Sales\Application\EventHandlers\LogAuditTrailHandler;
use Src\Sales\Application\EventHandlers\ProjectSaleListItemOnSaleCancelledHandler;
use Src\Sales\Application\EventHandlers\ProjectSaleListItemOnSaleConfirmedHandler;
use Src\Sales\Application\EventHandlers\ProjectSaleListItemOnSaleCreatedHandler;
use Src\Sales\Application\EventHandlers\ProjectSaleReportsOnSaleCompletedHandler;
use Src\Sales\Application\EventHandlers\SendConfirmationEmailHandler;
use Src\Sales\Application\EventHandlers\UpdateCommissionProjectionHandler;
use Src\Sales\Application\EventHandlers\UpdateSalesMetricsHandler;
use Src\Sales\Domain\Events\CommissionCalculatedEvent;
use Src\Sales\Domain\Events\SaleCancelledEvent;
use Src\Sales\Domain\Events\SaleCompletedEvent;
use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Sales\Domain\Events\SaleCreatedEvent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     *
     * Ordering convention: AC-003 read-model projection handlers are registered
     * FIRST for every event so the denormalized read tables (sale_list_items,
     * sales_reports, commission_reports) are always up-to-date before any
     * side-effect handler (audit log, email, metrics, commission calculation)
     * that may consult or rely on those read models runs.
     */
    protected $listen = [
        SaleCreatedEvent::class => [
            // AC-003 read-model projection
            ProjectSaleListItemOnSaleCreatedHandler::class,
            // Side effects
            LogAuditTrailHandler::class,
        ],
        SaleConfirmedEvent::class => [
            // AC-003 read-model projection
            ProjectSaleListItemOnSaleConfirmedHandler::class,
            // Side effects
            SendConfirmationEmailHandler::class,
            LogAuditTrailHandler::class,
        ],
        SaleCompletedEvent::class => [
            // AC-003 read-model projection (updates sale_list_items status,
            // sales_reports counters and commission_reports counters)
            ProjectSaleReportsOnSaleCompletedHandler::class,
            // Side effects
            UpdateSalesMetricsHandler::class,
            CalculateCommissionHandler::class,
            LogAuditTrailHandler::class,
        ],
        CommissionCalculatedEvent::class => [
            UpdateCommissionProjectionHandler::class,
            LogAuditTrailHandler::class,
        ],
        SaleCancelledEvent::class => [
            // AC-003 read-model projection (updates sale_list_items status
            // + cancellation fields only; no aggregation rollback because
            // domain rule Sale::isCancellable() forbids cancelling a
            // completed sale).
            ProjectSaleListItemOnSaleCancelledHandler::class,
            // Side effects
            LogAuditTrailHandler::class,
        ],
    ];
}
