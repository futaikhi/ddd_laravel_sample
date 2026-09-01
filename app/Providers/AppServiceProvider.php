<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Client\Domain\Repositories\ClientRepositoryInterface;
use Src\Client\Infrastructure\Persistence\ClientRepository;
use Src\Reservation\Domain\Repositories\BookingReadModelRepositoryInterface;
use Src\Reservation\Domain\Repositories\BookingRepositoryInterface;
use Src\Reservation\Infrastructure\Persistence\BookingReadModelRepository;
use Src\Reservation\Infrastructure\Persistence\BookingRepository;
use Src\Sales\Domain\Ports\CommissionCalculatorInterface;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Infrastructure\Commission\DatabaseCommissionService;
use Src\Sales\Infrastructure\Commission\MockCommissionService;
use Src\Sales\Infrastructure\Payment\LaravelPaymentGatewayAdapter;
use Src\Sales\Infrastructure\Payment\MockPaymentGatewayAdapter;
use Src\Sales\Infrastructure\Persistence\SaleRepository;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\SimpleCommandBus;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\SimpleEventBus;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBus;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bus bindings
        $this->app->bind(QueryBusInterface::class, QueryBus::class);
        $this->app->bind(CommandBusInterface::class, SimpleCommandBus::class);
        $this->app->bind(EventBusInterface::class, SimpleEventBus::class);

        // Reservation repositories
        $this->app->bind(BookingRepositoryInterface::class, BookingRepository::class);
        $this->app->bind(BookingReadModelRepositoryInterface::class, BookingReadModelRepository::class);

        // Sales repositories
        $this->app->bind(SaleRepositoryInterface::class, SaleRepository::class);

        // Sales ports - use mock adapters in testing, production in other environments
        $this->registerSalesAdapters();

        // Client repository
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
    }

    public function boot(): void
    {
        //
    }

    /**
     * Register Sales adapters (payment gateway and commission calculator)
     *
     * Uses mock implementations in testing environment for easy testing
     * Uses production implementations in other environments
     */
    private function registerSalesAdapters(): void
    {
        if ($this->app->environment('testing')) {
            // Testing environment: use mocks for easy test control
            $this->app->bind(PaymentGatewayInterface::class, MockPaymentGatewayAdapter::class);
            $this->app->bind(CommissionCalculatorInterface::class, MockCommissionService::class);
        } else {
            // Production environment: use real implementations
            $this->app->bind(PaymentGatewayInterface::class, LaravelPaymentGatewayAdapter::class);
            $this->app->bind(CommissionCalculatorInterface::class, DatabaseCommissionService::class);
        }
    }
}
