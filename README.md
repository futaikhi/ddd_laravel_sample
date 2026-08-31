# Sales Order Processing - DDD Sample

Project ini fokus pada sales order processing dengan pendekatan Domain-Driven Design, CQRS, dan hexagonal architecture.

## Tujuan project

Project ini membangun domain sales yang mencakup:

- create sale
- confirm sale
- complete sale
- cancel sale
- validasi business rules di aggregate
- tipe aman dengan value object
- API layer yang thin dan command-driven

## Domain yang sedang dibangun

### Sales aggregate

Domain utama adalah `Sale` yang berisi:

- `id` sebagai `SaleId`
- `customerId` sebagai `CustomerId`
- `lineItems` sebagai daftar `LineItem`
- `status` sebagai `OrderStatus`
- `totalAmount` sebagai `Money`
- timestamps seperti `createdAt`, `confirmedAt`, `completedAt`, `cancelledAt`

### Value objects

- `SaleId`
- `CustomerId`
- `ProductId`
- `Money`
- `LineItem`
- `OrderStatus`

### Business rules

- minimum order: Rp 50.000
- max line items: 20
- quantity harus > 0
- unit price harus > 0
- status transition hanya valid untuk rule tertentu
- `Pending -> Confirmed -> Completed`
- `Pending/Confirmed -> Cancelled`
- invalid transition akan throw domain exception

## Struktur utama

```text
src/
├── Sales/
│   ├── Application/
│   │   ├── Commands/
│   │   │   ├── Create/
│   │   │   ├── Confirm/
│   │   │   ├── Complete/
│   │   │   └── Cancel/
│   │   └── Queries/
│   ├── Domain/
│   │   ├── Entities/
│   │   ├── Enums/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Repositories/
│   │   └── ValueObjects/
│   └── Infrastructure/
│       └── Persistence/
│
├── Shared/
│   └── Framework/
└── ...
```

## Request → DTO → Action → Command flow

```php
final class CreateSaleAction
{
    public function __invoke(CreateSaleDto $dto): SaleCreatedRes
    {
        $this->commandBus->dispatch(new CreateSaleCommand(
            id: $dto->id,
            customerId: $dto->customerId,
            lineItems: $dto->lineItems,
        ));

        return new SaleCreatedRes(id: $dto->id->getValue());
    }
}
```

## Domain logic contoh

```php
$sale = Sale::create(
    SaleId::random(),
    CustomerId::random(),
    [
        new LineItem(ProductId::fromString('...'), 2, Money::fromCents(30000, 'IDR')),
    ],
);

$sale->confirm();
$sale->complete();
```

## API endpoints

### Sales

- `POST /api/sales`
- `POST /api/sales/{id}/confirm`
- `POST /api/sales/{id}/cancel`
- `POST /api/sales/{id}/complete`

### Contoh create sale

```bash
curl -X POST http://localhost/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "01H8M6KJ5NQ8XX4P0N2VYJ4K5A",
    "line_items": [
      {"product_id": "01H8M6KJ5NQ8XX4P0N2VYJ4K5B", "quantity": 2},
      {"product_id": "01H8M6KJ5NQ8XX4P0N2VYJ4K5C", "quantity": 1}
    ]
  }'
```

## Testing

Project ini sudah memiliki unit test untuk domain sales dan action-layer, misalnya:

```bash
php artisan test tests/Unit/Sales
php artisan test tests/Feature/Api/Sales/SalesLifecycleTest.php
```

## Catatan arsitektur

- domain tidak boleh membawa Laravel/Eloquent dependency
- validation existence customer/product dilakukan di application/action layer
- domain hanya memegang business rule dan state transition
- use case mengarahkan orchestration, lalu domain aggregate memegang invariant

## Status saat ini

Project ini sedang fokus pada FR-001 sampai command-side sales flow:

- domain model sales
- value objects
- business rules
- confirm/complete/cancel flow
- action + controller + command bus integration

Berikutnya yang akan dikerjakan adalah bagian FR-002 dan FR-003 yang lebih ke hexagonal architecture dan CQRS read model.
