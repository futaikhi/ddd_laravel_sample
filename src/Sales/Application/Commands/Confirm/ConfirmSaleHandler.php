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
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

final readonly class ConfirmSaleHandler implements CommandHandlerInterface
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private PaymentGatewayInterface $paymentGateway,
        private EventBusInterface $eventBus,
    ) {
    }

    /**
     * Orchestrates:
     * 1. Load sale aggregate
     * 2. Call payment gateway (hexagonal port)
     * 3. If payment succeeded, transition aggregate to CONFIRMED
     * 4. Persist + publish events
     *
     * On payment failure the aggregate stays PENDING and the exception
     * propagates so the HTTP layer can return a 402 (Payment Required).
     */
    public function __invoke(ConfirmSaleCommand $command): void
    {
        $sale = $this->repository->getById($command->id);

        // Guard before payment processing: never validate/process payment
        // if the sale is already confirmed/completed/cancelled. The aggregate
        // still owns the invariant in confirm(); this is a safety check to
        // avoid duplicate payment records on retries.
        if ($sale->getStatus() !== OrderStatus::PENDING) {
            throw SaleCannotBeConfirmedException::notPending($sale->getStatus()->value);
        }

        $paymentResult = $this->paymentGateway->process(new PaymentRequest(
            saleId: $sale->getId()->getValue(),
            amount: $sale->getTotalAmount(),
            currency: $sale->getTotalAmount()->currency,
            description: "Payment for sale {$sale->getId()->getValue()}",
        ));

        if (!$paymentResult->isSuccess()) {
            throw PaymentFailedException::withMessage($paymentResult->getMessage());
        }

        $sale->confirm(
            paymentMethod: $command->paymentMethod, // already a PaymentMethod enum
            transactionId: $paymentResult->getTransactionId(),
        );

        $this->repository->store($sale);

        $this->eventBus->publishEvents($sale->releaseEvents());
    }
}
