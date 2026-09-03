<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Confirm;

use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Exceptions\SaleCannotBeConfirmedException;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandHandlerInterface;

final readonly class ConfirmSaleHandler implements CommandHandlerInterface
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private PaymentGatewayInterface $paymentGateway,
    ) {
    }

    public function __invoke(ConfirmSaleCommand $command): void
    {
        $sale = $this->repository->getById($command->id);

        if ($sale->getStatus() !== OrderStatus::PENDING) {
            throw SaleCannotBeConfirmedException::notPending($sale->getStatus()->value);
        }

        $paymentResult = $this->paymentGateway->process(new PaymentRequest(
            saleId: $sale->getId()->getValue(),
            amount: $sale->getTotalAmount(),
            currency: $sale->getTotalAmount()->currency,
            description: "Payment sale {$sale->getId()->getValue()}",
        ));

        if (! $paymentResult->isSuccess()) {
            throw PaymentFailedException::withMessage($paymentResult->getMessage());
        }

        $sale->confirm(
            paymentMethod: $command->paymentMethod,
            transactionId: $paymentResult->getTransactionId(),
        );

        $this->repository->store($sale);
    }
}
