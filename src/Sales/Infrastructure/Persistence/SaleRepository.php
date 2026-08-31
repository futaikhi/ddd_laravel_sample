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

        $lineItems = array_map(
            fn (SaleLineItemModel $item): LineItem => new LineItem(
                productId: ProductId::fromString($item->product_id),
                quantity: (int) $item->quantity,
                unitPrice: new Money((int) $item->unit_price, (string) $item->currency),
            ),
            $model->lineItems->all()
        );

        return Sale::reconstitute(
            id: SaleId::fromString($model->id),
            customerId: CustomerId::fromString($model->customer_id),
            lineItems: $lineItems,
            status: OrderStatus::from($model->status),
            totalAmount: new Money((int) $model->total_amount, 'IDR'),
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : new \DateTimeImmutable(),
            confirmedAt: $model->confirmed_at ? new \DateTimeImmutable($model->confirmed_at) : null,
            cancelledAt: $model->cancelled_at ? new \DateTimeImmutable($model->cancelled_at) : null,
            cancellationReason: $model->cancellation_reason,
            completedAt: $model->completed_at ? new \DateTimeImmutable($model->completed_at) : null,
        );
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
