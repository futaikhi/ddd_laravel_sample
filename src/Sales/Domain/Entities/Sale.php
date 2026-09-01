<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Entities;

use DateTimeImmutable;
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
use Src\Sales\Domain\ValueObjects\Commission;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Domain\Entities\BaseEntity;

final class Sale extends BaseEntity
{
    private const MINIMUM_ORDER_AMOUNT = 50000;
    private const MAX_LINE_ITEMS = 20;

    /**
     * @param list<LineItem> $lineItems
     */
    private function __construct(
        private readonly SaleId $id,
        private readonly CustomerId $customerId,
        private array $lineItems,
        private OrderStatus $status,
        private Money $totalAmount,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $confirmedAt = null,
        private ?DateTimeImmutable $cancelledAt = null,
        private ?string $cancellationReason = null,
        private ?DateTimeImmutable $completedAt = null,
        private ?PaymentMethod $paymentMethod = null,
        private ?string $transactionId = null,
        private ?Commission $commission = null,
    ) {
    }

    /**
     * @param list<LineItem> $lineItems
     */
    public static function create(SaleId $id, CustomerId $customerId, array $lineItems): self
    {
        if ($lineItems === []) {
            throw new \InvalidArgumentException('A sale must contain at least one line item.');
        }

        if (count($lineItems) > self::MAX_LINE_ITEMS) {
            throw new \InvalidArgumentException('A sale cannot contain more than 20 line items.');
        }

        $total = Money::zero();
        foreach ($lineItems as $lineItem) {
            $total = $total->add($lineItem->getTotal());
        }

        if ($total->getValue() < self::MINIMUM_ORDER_AMOUNT) {
            throw MinimumOrderAmountException::belowMinimum($total->getValue(), self::MINIMUM_ORDER_AMOUNT);
        }

        $sale = new self(
            id: $id,
            customerId: $customerId,
            lineItems: $lineItems,
            status: OrderStatus::PENDING,
            totalAmount: $total,
            createdAt: new DateTimeImmutable(),
            paymentMethod: null,
            transactionId: null,
            commission: null,
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
        OrderStatus $status,
        Money $totalAmount,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $confirmedAt = null,
        ?DateTimeImmutable $cancelledAt = null,
        ?string $cancellationReason = null,
        ?DateTimeImmutable $completedAt = null,
        ?PaymentMethod $paymentMethod = null,
        ?string $transactionId = null,
        ?Commission $commission = null,
    ): self {
        $sale = new self(
            id: $id,
            customerId: $customerId,
            lineItems: $lineItems,
            status: $status,
            totalAmount: $totalAmount,
            createdAt: $createdAt ?? new DateTimeImmutable(),
            confirmedAt: $confirmedAt,
            cancelledAt: $cancelledAt,
            cancellationReason: $cancellationReason,
            completedAt: $completedAt,
            paymentMethod: $paymentMethod,
            transactionId: $transactionId,
            commission: $commission,
        );

        return $sale;
    }

    /**
     * Confirm the sale after payment has been captured.
     *
     * Orchestration of the payment call lives in the Application layer
     * (see ConfirmSaleHandler + PaymentGatewayInterface). The gateway
     * result (transactionId + paymentMethod) is handed to this aggregate
     * so the domain stays the single source of truth for the sale state.
     */
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

    /**
     * Complete the sale and lock the commission calculated by the
     * CommissionCalculatorInterface port (called from CompleteSaleHandler).
     */
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

        if (! in_array($this->status, [OrderStatus::PENDING, OrderStatus::CONFIRMED], true)) {
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
        return in_array($this->status, [OrderStatus::PENDING, OrderStatus::CONFIRMED], true);
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
