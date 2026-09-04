# Sales Order Processing DDD Sample

Project ini fokus pada sales order processing dengan pendekatan Domain-Driven Design, CQRS, dan Hexagonal Architecture.

## Tujuan project

Project ini membangun domain sales yang mencakup:

- Create sale
- Confirm sale
- Complete sale
- Cancel sale
- Validasi business rules pada aggregate
- Tipe aman dengan value object
- API layer yang thin dan command-driven
- Pemisahan jelas antara HTTP adapter, application use case, domain model, dan infrastructure adapter

## Architectural flow

Flow utama mengikuti boundary berikut:

```text
REST / HTTP Request
        |
        v
Controller / Action
        |
        | maps request DTO to command
        v
Application Command Handler
        |
        | coordinates repositories and ports
        v
Domain Aggregate / Value Objects / Domain Events
        |
        | persisted or published through interfaces
        v
Infrastructure Adapters
        |
        | Eloquent, event bus, projections, external services
        v
Database / Queue / External Systems
```

Prinsip penting:

- HTTP layer hanya bertugas menerima request, validasi request, mapping DTO, dan dispatch command/query.
- Application layer mengoordinasikan use case melalui repository dan port.
- Domain layer menyimpan business rules, invariant, state transition, value object, dan domain event.
- Infrastructure layer mengimplementasikan port/repository menggunakan Laravel, Eloquent, database, event bus, queue, atau service eksternal.
- Command-side tidak boleh bergantung pada read model repository.

## Domain yang sedang dibangun

### Sales aggregate

Domain utama adalah `Sale` yang berisi:

- `id` sebagai `SaleId`
- `customerId` sebagai `CustomerId`
- `agentId` sebagai nullable `AgentId`
- `lineItems` sebagai daftar `LineItem`
- `status` sebagai `OrderStatus`
- `totalAmount` sebagai `Money`
- timestamps seperti `createdAt`, `confirmedAt`, `completedAt`, dan `cancelledAt`
- optional payment dan commission data untuk lifecycle berikutnya

### Value objects

- `SaleId`
- `CustomerId`
- `AgentId`
- `ProductId`
- `Money`
- `LineItem`
- `SalesFilter`
- `PaymentMethod`
- `Commission`

### Business rules

- Minimum order: 50.000
- Maximum line items: 20
- Quantity harus lebih besar dari 0
- Unit price harus lebih besar dari 0
- Status transition hanya valid untuk rule tertentu:
  - `Pending -> Confirmed -> Completed`
  - `Pending/Confirmed -> Cancelled`
- Invalid transition akan throw domain exception.
- Aggregate mencatat domain events untuk dipublish oleh repository/infrastructure.

## Struktur utama

```text
Apps/
└── Api/
    └── Sales/
        ├── SalesController.php
        ├── Create/
        ├── Confirm/
        ├── Complete/
        ├── Cancel/
        ├── Show/
        ├── Index/
        ├── Reports/
        └── Shared/

Src/
├── Sales/
│   ├── Application/
│   │   ├── Commands/
│   │   ├── Queries/
│   │   └── EventHandlers/
│   ├── Domain/
│   │   ├── Entities/
│   │   ├── Enums/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Ports/
│   │   ├── Repositories/
│   │   └── ValueObjects/
│   └── Infrastructure/
│       ├── Commission/
│       ├── Customer/
│       ├── Payment/
│       ├── Persistence/
│       └── Product/
└── Shared/
    └── Framework/
```

## Request DTO Action Command flow

Create sale flow saat ini:

```php
final readonly class CreateSaleAction
{
    public function __invoke(CreateSaleDto $dto): SaleCreatedRes
    {
        $items = array_map(
            static fn (LineItemInputDto $item): CreateSaleLineItem => new CreateSaleLineItem(
                productId: $item->productId,
                quantity: $item->quantity,
            ),
            $dto->lineItems,
        );

        $this->commandBus->dispatch(new CreateSaleCommand(
            id: $dto->id,
            customerId: $dto->customerId,
            items: $items,
        ));

        return new SaleCreatedRes(id: $dto->id->getValue());
    }
}
```

HTTP action tidak melakukan Eloquent lookup. Product price resolution dilakukan oleh application handler melalui domain port `ProductCatalogInterface`, lalu adapter infrastructure `EloquentProductCatalog` yang membaca model `Product`.

## Application handler create sale

```php
final readonly class CreateSaleHandler
{
    public function __invoke(CreateSaleCommand $command): void
    {
        if (! $this->customers->exists($command->customerId)) {
            throw CustomerNotFoundException::withId($command->customerId);
        }

        $lineItems = [];
        foreach ($command->items as $item) {
            $lineItems[] = $this->products->lineItemFor($item->productId, $item->quantity);
        }

        $sale = Sale::create(
            id: $command->id,
            customerId: $command->customerId,
            lineItems: $lineItems,
            agentId: $command->agentId,
        );

        $this->repository->store($sale);
    }
}
```

## Ports dan adapters

Sales menggunakan port untuk menghindari dependency langsung domain/application terhadap detail infrastructure:

- `SaleRepositoryInterface` diimplementasikan oleh `SaleRepository`
- `SaleReadModelRepositoryInterface` diimplementasikan oleh `SaleReadModelRepository`
- `CustomerExistenceCheckerInterface` diimplementasikan oleh `EloquentCustomerExistenceChecker`
- `ProductCatalogInterface` diimplementasikan oleh `EloquentProductCatalog`
- `PaymentGatewayInterface` diimplementasikan oleh payment adapter
- `CommissionCalculatorInterface` diimplementasikan oleh commission adapter

Binding adapter dilakukan di `AppServiceProvider`.

## Domain logic contoh

```php
$sale = Sale::create(
    id: SaleId::random(),
    customerId: CustomerId::random(),
    lineItems: [
        new LineItem(
            productId: ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5B'),
            quantity: 2,
            unitPrice: Money::fromCents(30000, 'IDR'),
        ),
    ],
);

$sale->confirm($paymentMethod, $transactionId);
$sale->complete($commission);
```

## API endpoints

### Sales

- `POST /api/sales`
- `POST /api/sales/{id}/confirm`
- `POST /api/sales/{id}/cancel`
- `POST /api/sales/{id}/complete`
- `GET /api/sales/{id}`
- `GET /api/sales`
- `GET /api/sales/reports/sales`
- `GET /api/sales/reports/commission-summary`

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

Project ini memiliki unit dan feature test untuk domain sales, action-layer, handler, adapters, domain events, dan lifecycle API.

Contoh command:

```bash
php artisan test tests/Unit/Sales
php artisan test tests/Feature/Api/Sales/SalesLifecycleTest.php
vendor/bin/phpunit Tests/Unit/Sales/CreateSaleActionTest.php Tests/Unit/Sales/Handlers/CreateSaleHandlerTest.php
```

Targeted validation terakhir:

```text
OK (5 tests, 24 assertions)
```

## Catatan arsitektur

- Domain tidak boleh membawa Laravel/Eloquent dependency.
- HTTP/action layer tidak melakukan query Eloquent langsung untuk create sale.
- Validasi customer existence dilakukan melalui `CustomerExistenceCheckerInterface`.
- Product lookup dan price resolution dilakukan melalui `ProductCatalogInterface`.
- Application handler mengarahkan orchestration, lalu domain aggregate memegang invariant.
- Read model dipakai untuk query/reporting side, bukan untuk command handler.
- Infrastructure adapter boleh menggunakan Laravel/Eloquent karena berada di boundary paling luar.

## Status saat ini

Project sudah mencakup command-side sales flow dan sebagian CQRS/read-side flow:

- Sales domain model
- Value objects
- Business rules
- Confirm/complete/cancel lifecycle
- Command bus integration
- Domain events
- Read model projection untuk report/commission
- Hexagonal ports/adapters untuk customer, product catalog, payment, commission, dan persistence
- Thin HTTP action untuk create sale
