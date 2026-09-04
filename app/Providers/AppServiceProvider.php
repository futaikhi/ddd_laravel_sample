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
use Src\Sales\Domain\Ports\CustomerExistenceCheckerInterface;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\Ports\ProductCatalogInterface;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Infrastructure\Commission\DatabaseCommissionService;
use Src\Sales\Infrastructure\Commission\MockCommissionService;
use Src\Sales\Infrastructure\Customer\EloquentCustomerExistenceChecker;
use Src\Sales\Infrastructure\Payment\LaravelPaymentGatewayAdapter;
use Src\Sales\Infrastructure\Payment\MockPaymentGatewayAdapter;
use Src\Sales\Infrastructure\Persistence\SaleReadModelRepository;
use Src\Sales\Infrastructure\Persistence\SaleRepository;
use Src\Sales\Infrastructure\Product\EloquentProductCatalog;
use Src\Shared\Framework\Domain\Events\DomainEventStoreInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\SimpleCommandBus;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\SimpleEventBus;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBus;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;
use Src\Shared\Framework\Infrastructure\Events\EloquentDomainEventStore;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QueryBusInterface::class, QueryBus::class);
        $this->app->bind(CommandBusInterface::class, SimpleCommandBus::class);
        $this->app->bind(EventBusInterface::class, SimpleEventBus::class);
        $this->app->bind(DomainEventStoreInterface::class, EloquentDomainEventStore::class);

        $this->app->bind(BookingRepositoryInterface::class, BookingRepository::class);
        $this->app->bind(BookingReadModelRepositoryInterface::class, BookingReadModelRepository::class);

        $this->app->bind(SaleRepositoryInterface::class, SaleRepository::class);
        $this->app->bind(SaleReadModelRepositoryInterface::class, SaleReadModelRepository::class);
        $this->app->bind(CustomerExistenceCheckerInterface::class, EloquentCustomerExistenceChecker::class);
        $this->app->bind(ProductCatalogInterface::class, EloquentProductCatalog::class);

        $this->registerSalesAdapters();

        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
    }

    public function boot(): void
    {
    }

    private function registerSalesAdapters(): void
    {
        if ($this->app->environment('testing')) {
            $this->app->bind(PaymentGatewayInterface::class, MockPaymentGatewayAdapter::class);
            $this->app->bind(CommissionCalculatorInterface::class, MockCommissionService::class);
        } else {
            $this->app->bind(PaymentGatewayInterface::class, LaravelPaymentGatewayAdapter::class);
            $this->app->bind(CommissionCalculatorInterface::class, DatabaseCommissionService::class);
        }
    }
}
