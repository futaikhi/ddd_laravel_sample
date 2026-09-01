<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Persistence;

use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Domain\Enums\OrderStatus;

final class SaleRepository implements SaleRepositoryInterface
{
    public function store(Sale $sale): void
    {
        SaleModel::updateOrCreate(
            ['id' => $sale->getId()->getValue()],
            [
                'customer_id' => $sale->getCustomerId()->getValue(),
                'status' => $sale->getStatus()->value,
                'total_amount' => $sale->getTotalAmount()->getValue(),
                'confirmed_at' => $sale->getConfirmedAt()?->format('Y-m-d H:i:s'),
                'cancelled_at' => $sale->getCancelledAt()?->format('Y-m-d H:i:s'),
                'cancellation_reason' => $sale->getCancellationReason(),
                'completed_at' => $sale->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]
        );

        SaleLineItemModel::where('sale_id', $sale->getId()->getValue())->delete();

        foreach ($sale->getLineItems() as $lineItem) {
            SaleLineItemModel::create([
                'sale_id' => $sale->getId()->getValue(),
                'product_id' => $lineItem->productId,
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

        return Sale::reconstitute(
            id: SaleId::fromString((string) $model->id),
            customerId: CustomerId::fromString((string) $model->customer_id),
            lineItems: $lineItems,
            status: OrderStatus::from((string) $model->status),
            totalAmount: new Money((int) $model->total_amount, 'IDR'),
            createdAt: new \DateTimeImmutable(),
            confirmedAt: $this->toDateTimeImmutable($model->confirmed_at),
            cancelledAt: $this->toDateTimeImmutable($model->cancelled_at),
            cancellationReason: $model->cancellation_reason !== null ? (string) $model->cancellation_reason : null,
            completedAt: $this->toDateTimeImmutable($model->completed_at),
        );
    }

    private function toDateTimeImmutable(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new \DateTimeImmutable($value);
        }

        return null;
    }

    public function getById(SaleId $id): Sale
    {
        $sale = $this->findById($id);

        if ($sale === null) {
            throw SaleNotFoundException::withId($id);
        }

        return $sale;
    }
}
