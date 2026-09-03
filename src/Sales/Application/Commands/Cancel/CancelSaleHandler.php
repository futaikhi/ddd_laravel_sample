<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Cancel;

use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Exceptions\SaleCannotBeCancelledException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandHandlerInterface;

final readonly class CancelSaleHandler implements CommandHandlerInterface
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private PaymentGatewayInterface $paymentGateway,
    ) {
    }

    public function __invoke(CancelSaleCommand $command): void
    {
        $sale = $this->repository->getById($command->id);

        if ($sale->getStatus() === OrderStatus::CONFIRMED) {
            $transactionId = $sale->getTransactionId();

            if ($transactionId === null || $transactionId === '') {
                throw SaleCannotBeCancelledException::missingTransactionId();
            }

            $this->paymentGateway->refund($transactionId);
        }

        $sale->cancel($command->reason);

        $this->repository->store($sale);
    }
}
