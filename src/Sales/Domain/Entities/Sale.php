<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Events\SaleCancelledEvent;
use Src\Sales\Domain\Events\SaleCompletedEvent;
use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Sales\Domain\Events\SaleCreatedEvent;
use Src\Sales\Domain\Exceptions\MinimumOrderAmountException;
use Src\Sales\Domain\Exceptions\SaleCannotBeCancelledException;
use Src\Sales\Domain\Exceptions\SaleCannotBeCompletedException;
use Src\Sales\Domain\Exceptions\SaleCannotBeConfirmedException;
use Src\Sales\Domain\ValueObjects\AgentId;
use Src\Sales\Domain\ValueObjects\Commission;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Domain\Entities\BaseEntity;

final class Sale extends BaseEntity
{
    private const MINIMUM_ORDER_AMOUNT = 50000;

    /**
     * @param list<LineItem> $lineItems
     */
    private function __construct(
        private readonly SaleId $id,
        private readonly CustomerId $customerId,
        private readonly array $lineItems,
        private Money $totalAmount,
        private OrderStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $confirmedAt = null,
        private ?DateTimeImmutable $completedAt = null,
        private ?DateTimeImmutable $cancelledAt = null,
        private ?string $cancellationReason = null,
        private ?PaymentMethod $paymentMethod = null,
        private ?string $transactionId = null,
        private ?Commission $commission = null,
        private readonly ?AgentId $agentId = null,
    ) {
    }

    /**
     * @param list<LineItem> $lineItems
     */
    public static function create(
        SaleId $id,
        CustomerId $customerId,
        array $lineItems,
        ?AgentId $agentId = null,
    ): self {
        if ($lineItems === []) {
            throw new InvalidArgumentException('Sale must have at least one line item');
        }

        if (count($lineItems) > 20) {
            throw new InvalidArgumentException('Sale cannot have more than 20 line items');
        }

        $totalAmount = Money::zero();

        foreach ($lineItems as $lineItem) {
            if (! $lineItem instanceof LineItem) {
                throw new InvalidArgumentException('Line items must be LineItem instances');
            }

            $totalAmount = $totalAmount->add($lineItem->getTotal());
        }

        if ($totalAmount->getValue() < self::MINIMUM_ORDER_AMOUNT) {
            throw MinimumOrderAmountException::belowMinimum(
                $totalAmount->getValue(),
                self::MINIMUM_ORDER_AMOUNT,
            );
        }

        $sale = new self(
            id: $id,
            customerId: $customerId,
            lineItems: $lineItems,
            totalAmount: $totalAmount,
            status: OrderStatus::PENDING,
            createdAt: new DateTimeImmutable(),
            agentId: $agentId,
        );

        $sale->recordLast(SaleCreatedEvent::fromEntity($sale));

        return $sale;
    }

    /**
     * @param list<LineItem> $lineItems
     */
    public static function reconstitute(
        SaleId $id,
        CustomerId $customerId,
        array $lineItems,
        Money $totalAmount,
        OrderStatus $status,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $confirmedAt = null,
        ?DateTimeImmutable $completedAt = null,
        ?DateTimeImmutable $cancelledAt = null,
        ?string $cancellationReason = null,
        ?PaymentMethod $paymentMethod = null,
        ?string $transactionId = null,
        ?Commission $commission = null,
        ?AgentId $agentId = null,
    ): self {
        return new self(
            id: $id,
            customerId: $customerId,
            lineItems: $lineItems,
            totalAmount: $totalAmount,
            status: $status,
            createdAt: $createdAt,
            confirmedAt: $confirmedAt,
            completedAt: $completedAt,
            cancelledAt: $cancelledAt,
            cancellationReason: $cancellationReason,
            paymentMethod: $paymentMethod,
            transactionId: $transactionId,
            commission: $commission,
            agentId: $agentId,
        );
    }

    public function confirm(PaymentMethod $paymentMethod, string $transactionId): void
    {
        if ($this->status !== OrderStatus::PENDING) {
            throw SaleCannotBeConfirmedException::notPending($this->status->value);
        }

        $this->status = OrderStatus::CONFIRMED;
        $this->confirmedAt = new DateTimeImmutable();
        $this->paymentMethod = $paymentMethod;
        $this->transactionId = $transactionId;

        $this->recordLast(SaleConfirmedEvent::fromEntity($this));
    }

    public function complete(Commission $commission): void
    {
        if ($this->status !== OrderStatus::CONFIRMED) {
            throw SaleCannotBeCompletedException::notConfirmed($this->status->value);
        }

        $this->status = OrderStatus::COMPLETED;
        $this->completedAt = new DateTimeImmutable();
        $this->commission = $commission;

        $this->recordLast(SaleCompletedEvent::fromEntity($this));
    }

    public function cancel(string $reason): void
    {
        if ($this->status === OrderStatus::CANCELLED) {
            throw SaleCannotBeCancelledException::alreadyCancelled();
        }

        if (! $this->isCancellable()) {
            throw SaleCannotBeCancelledException::invalidStatus($this->status->value);
        }

        $this->status = OrderStatus::CANCELLED;
        $this->cancelledAt = new DateTimeImmutable();
        $this->cancellationReason = $reason;

        $this->recordLast(SaleCancelledEvent::fromEntity($this));
    }

    public function getId(): SaleId
    {
        return $this->id;
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getAgentId(): ?AgentId
    {
        return $this->agentId;
    }

    /**
     * @return list<LineItem>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    public function getTotalAmount(): Money
    {
        return $this->totalAmount;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isCompleted(): bool
    {
        return $this->status === OrderStatus::COMPLETED;
    }

    public function isCancellable(): bool
    {
        return in_array(
            $this->status,
            [OrderStatus::PENDING, OrderStatus::CONFIRMED],
            true,
        );
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getCommission(): ?Commission
    {
        return $this->commission;
    }
}
