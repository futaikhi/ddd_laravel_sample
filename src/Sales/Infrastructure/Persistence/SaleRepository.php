<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\Commission;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Domain\ValueObjects\SalesFilter;

final class SaleRepository implements SaleRepositoryInterface
{
    public function store(Sale $sale): void
    {
        $commission = $sale->getCommission();

        SaleModel::updateOrCreate(
            ['id' => $sale->getId()->getValue()],
            [
                'customer_id' => $sale->getCustomerId()->getValue(),
                'status' => $sale->getStatus()->value,
                'total_amount' => $sale->getTotalAmount()->getValue(),
                'created_at' => $sale->getCreatedAt()->format('Y-m-d H:i:s'),
                'confirmed_at' => $sale->getConfirmedAt()?->format('Y-m-d H:i:s'),
                'cancelled_at' => $sale->getCancelledAt()?->format('Y-m-d H:i:s'),
                'cancellation_reason' => $sale->getCancellationReason(),
                'completed_at' => $sale->getCompletedAt()?->format('Y-m-d H:i:s'),
                'payment_method' => $sale->getPaymentMethod()?->value,
                'transaction_id' => $sale->getTransactionId(),
                'commission_amount' => $commission?->getAmount()->amount,
                'commission_rate' => $commission?->getRate(),
                'commission_currency' => $commission?->getAmount()->currency,
            ],
        );

        SaleLineItemModel::where('sale_id', $sale->getId()->getValue())->delete();

        foreach ($sale->getLineItems() as $lineItem) {
            SaleLineItemModel::create([
                'sale_id' => $sale->getId()->getValue(),
                'product_id' => $lineItem->productId->getValue(),
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unitPrice->getValue(),
                'currency' => $lineItem->unitPrice->currency,
            ]);
        }
    }

    public function findById(SaleId $id): ?Sale
    {
        $model = SaleModel::with('lineItems')->find($id->getValue());

        if ($model === null) {
            return null;
        }

        return $this->mapModelToSale($model);
    }

    public function getById(SaleId $id): Sale
    {
        $sale = $this->findById($id);

        if ($sale === null) {
            throw SaleNotFoundException::withId($id);
        }

        return $sale;
    }

    /**
     * @return list<Sale>
     */
    public function list(SalesFilter $filter): array
    {
        $query = SaleModel::query()->with('lineItems');

        if ($filter->customerId !== null) {
            $query->where('customer_id', $filter->customerId->getValue());
        }

        if ($filter->status !== null) {
            $query->where('status', $filter->status->value);
        }

        if ($filter->createdFrom !== null) {
            $query->where('created_at', '>=', $filter->createdFrom->format('Y-m-d H:i:s'));
        }

        if ($filter->createdTo !== null) {
            $query->where('created_at', '<=', $filter->createdTo->format('Y-m-d H:i:s'));
        }

        if ($filter->offset !== null) {
            $query->offset($filter->offset);
        }

        if ($filter->limit !== null) {
            $query->limit($filter->limit);
        }

        /** @var list<Sale> $sales */
        $sales = [];

        /** @var SaleModel $model */
        foreach ($query->orderByDesc('created_at')->get() as $model) {
            $sales[] = $this->mapModelToSale($model);
        }

        return $sales;
    }

    private function mapModelToSale(SaleModel $model): Sale
    {
        /** @var list<LineItem> $lineItems */
        $lineItems = [];

        /** @var SaleLineItemModel $item */
        foreach ($model->lineItems as $item) {
            $lineItems[] = new LineItem(
                productId: ProductId::fromString((string) $item->product_id),
                quantity: (int) $item->quantity,
                unitPrice: new Money((int) $item->unit_price, (string) $item->currency),
            );
        }

        $commission = null;
        if ($model->commission_amount !== null && $model->commission_rate !== null) {
            $commission = new Commission(
                amount: new Money(
                    (int) $model->commission_amount,
                    (string) ($model->commission_currency ?? 'IDR'),
                ),
                rate: (float) $model->commission_rate,
            );
        }

        return Sale::reconstitute(
            id: SaleId::fromString((string) $model->id),
            customerId: CustomerId::fromString((string) $model->customer_id),
            lineItems: $lineItems,
            status: OrderStatus::from((string) $model->status),
            totalAmount: new Money((int) $model->total_amount, 'IDR'),
            createdAt: $this->toDateTimeImmutable($model->created_at) ?? new DateTimeImmutable(),
            confirmedAt: $this->toDateTimeImmutable($model->confirmed_at),
            completedAt: $this->toDateTimeImmutable($model->completed_at),
            cancelledAt: $this->toDateTimeImmutable($model->cancelled_at),
            cancellationReason: $model->cancellation_reason !== null ? (string) $model->cancellation_reason : null,
            paymentMethod: $model->payment_method !== null
                ? PaymentMethod::from((string) $model->payment_method)
                : null,
            transactionId: $model->transaction_id !== null ? (string) $model->transaction_id : null,
            commission: $commission,
        );
    }

    private function toDateTimeImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        return null;
    }
}
