# Sales API Architecture Flow

Dokumen ini menjelaskan arsitektur Sales API yang sedang dibuat: apa yang terjadi ketika user hit API, alurnya dari route, controller, request DTO, action, command/query bus, application handler, domain, infrastructure, sampai response kembali ke user.

## Gambaran besar

Arsitektur yang dipakai adalah gabungan dari Domain-Driven Design, Hexagonal Architecture, dan CQRS.

```text
User / Client
    |
    v
HTTP API Route
    |
    v
Controller
    |
    v
Request Object -> DTO
    |
    v
Action
    |
    v
Command Bus / Query Bus
    |
    v
Application Handler
    |
    v
Domain Aggregate / Domain Service / Value Object
    |
    v
Repository / Port Interface
    |
    v
Infrastructure Adapter
    |
    v
Database / Queue / External Service
```

Aturan layer:

| Layer | Tanggung jawab | Boleh tahu | Tidak boleh tahu |
| --- | --- | --- | --- |
| HTTP/API | Terima request, validasi request, mapping DTO, return response | Request, DTO, Action | Business rules detail, Eloquent query langsung untuk use case kompleks |
| Application | Orkestrasi use case | Command, Query, Repository interface, Port interface, Domain | Detail Eloquent table/query |
| Domain | Business rules, invariant, state transition, event | Entity, Value Object, Domain Event, Domain Exception | Laravel, Eloquent, HTTP, database |
| Infrastructure | Implementasi teknis | Eloquent, DB, Queue, external services | Mengubah business rule domain |

## Entry point Sales API

Semua endpoint Sales didaftarkan di [Routes/api.php](../Routes/api.php).

```text
POST /api/sales
POST /api/sales/{id}/confirm
POST /api/sales/{id}/cancel
POST /api/sales/{id}/complete
GET  /api/sales
GET  /api/sales/{id}
GET  /api/sales/reports/sales
GET  /api/sales/reports/commissions
```

Endpoint tersebut diarahkan ke [SalesController](../Apps/Api/Sales/SalesController.php).

## Flow command side: create sale

Command side adalah flow untuk mengubah state sistem. Contoh utama: create sale.

### 1. User hit API

User/client mengirim request:

```http
POST /api/sales
Content-Type: application/json
```

Payload contoh:

```json
{
  "customer_id": "01H8M6KJ5NQ8XX4P0N2VYJ4K5A",
  "line_items": [
    {"product_id": "01H8M6KJ5NQ8XX4P0N2VYJ4K5B", "quantity": 2}
  ]
}
```

### 2. Route mengarah ke controller

File: [Routes/api.php](../Routes/api.php)

```php
Route::prefix('sales')->group(function () {
    Route::post('/', [SalesController::class, 'create']);
});
```

Route memanggil method create di [SalesController](../Apps/Api/Sales/SalesController.php).

### 3. Controller menerima request dan action

File: [SalesController.php](../Apps/Api/Sales/SalesController.php)

```php
public function create(
    CreateSaleRequest $request,
    CreateSaleAction $action,
): JsonResponse {
    $resource = $action($request->getDto());

    return response()->json($resource, 201);
}
```

Controller hanya melakukan 3 hal:

1. Menerima request object.
2. Mengubah request menjadi DTO melalui request object.
3. Memanggil action dan mengembalikan JSON response.

Controller tidak menjalankan business rule dan tidak query database langsung.

### 4. Request object validasi dan mapping ke DTO

File: [CreateSaleRequest.php](../Apps/Api/Sales/Create/CreateSaleRequest.php)

Tanggung jawab request object:

- Validasi bentuk input HTTP.
- Ambil field seperti customer_id dan line_items.
- Mapping ke DTO.

DTO yang dipakai:

- [CreateSaleDto](../Apps/Api/Sales/Create/CreateSaleDto.php)
- [LineItemInputDto](../Apps/Api/Sales/Create/LineItemInputDto.php)

DTO ini masih berada di layer API/HTTP.

### 5. Action mapping DTO ke command

File: [CreateSaleAction.php](../Apps/Api/Sales/Create/CreateSaleAction.php)

Action adalah adapter tipis antara HTTP layer dan application layer.

Tanggung jawab action:

- Menerima DTO dari HTTP request.
- Mapping DTO API menjadi command application.
- Dispatch command ke command bus.
- Membuat response resource.

Action tidak boleh:

- Query Eloquent langsung.
- Menghitung harga produk.
- Menjalankan business rule aggregate.
- Persist data langsung.

Flow di action:

```text
CreateSaleDto
    |
    v
CreateSaleLineItem[]
    |
    v
CreateSaleCommand
    |
    v
CommandBus.dispatch()
    |
    v
SaleCreatedRes
```

File terkait:

- [CreateSaleAction.php](../Apps/Api/Sales/Create/CreateSaleAction.php)
- [CreateSaleCommand.php](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php)
- [CreateSaleLineItem.php](../Src/Sales/Application/Commands/Create/CreateSaleLineItem.php)
- [SaleCreatedRes.php](../Apps/Api/Sales/Shared/SaleCreatedRes.php)

### 6. Command bus mencari handler

File: [SimpleCommandBus.php](../Src/Shared/Framework/Infrastructure/Bus/CommandBus/SimpleCommandBus.php)

Command bus menerima [CreateSaleCommand](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php), lalu menentukan handler berdasarkan naming convention:

```text
CreateSaleCommand -> CreateSaleHandler
```

Command bus mengambil handler dari container dan memanggil method invoke handler.

### 7. Application handler mengorkestrasi use case

File: [CreateSaleHandler.php](../Src/Sales/Application/Commands/Create/CreateSaleHandler.php)

Handler adalah pusat orkestrasi create sale.

Dependency handler:

- [SaleRepositoryInterface](../Src/Sales/Domain/Repositories/SaleRepositoryInterface.php)
- [CustomerExistenceCheckerInterface](../Src/Sales/Domain/Ports/CustomerExistenceCheckerInterface.php)
- [ProductCatalogInterface](../Src/Sales/Domain/Ports/ProductCatalogInterface.php)

Flow handler:

```text
CreateSaleCommand
    |
    v
Check customer exists via CustomerExistenceCheckerInterface
    |
    v
Resolve product price and create LineItem via ProductCatalogInterface
    |
    v
Call Sale::create()
    |
    v
Persist Sale via SaleRepositoryInterface
```

Handler tidak bergantung pada read model repository. Ini penting karena create sale adalah command/write side.

### 8. Customer lookup lewat port

Port: [CustomerExistenceCheckerInterface](../Src/Sales/Domain/Ports/CustomerExistenceCheckerInterface.php)

Adapter: [EloquentCustomerExistenceChecker](../Src/Sales/Infrastructure/Customer/EloquentCustomerExistenceChecker.php)

Application handler hanya tahu interface. Detail bahwa customer dicek via Eloquent ada di infrastructure adapter.

```text
CreateSaleHandler
    |
    v
CustomerExistenceCheckerInterface
    |
    v
EloquentCustomerExistenceChecker
    |
    v
customers table
```

### 9. Product price lookup lewat port

Port: [ProductCatalogInterface](../Src/Sales/Domain/Ports/ProductCatalogInterface.php)

Adapter: [EloquentProductCatalog](../Src/Sales/Infrastructure/Product/EloquentProductCatalog.php)

Tujuannya agar HTTP/action layer dan application layer tidak query model Product langsung.

```text
CreateSaleHandler
    |
    v
ProductCatalogInterface.lineItemFor(productId, quantity)
    |
    v
EloquentProductCatalog
    |
    v
Product model / products table
    |
    v
LineItem domain value object
```

File model produk: [Product.php](../App/Models/Product.php)

### 10. Domain aggregate membuat sale

File: [Sale.php](../Src/Sales/Domain/Entities/Sale.php)

Handler memanggil domain aggregate:

```text
Sale::create(id, customerId, lineItems, agentId)
```

Di domain inilah business rules dijalankan, seperti:

- Minimum order.
- Maximum line items.
- Quantity harus valid.
- Unit price harus valid.
- Status awal sale adalah pending.
- Domain event sale created dicatat.

Value object terkait:

- [SaleId](../Src/Sales/Domain/ValueObjects/SaleId.php)
- [CustomerId](../Src/Sales/Domain/ValueObjects/CustomerId.php)
- [AgentId](../Src/Sales/Domain/ValueObjects/AgentId.php)
- [ProductId](../Src/Sales/Domain/ValueObjects/ProductId.php)
- [LineItem](../Src/Sales/Domain/ValueObjects/LineItem.php)
- [Money](../Src/Sales/Domain/ValueObjects/Money.php)
- [OrderStatus](../Src/Sales/Domain/Enums/OrderStatus.php)

### 11. Repository menyimpan aggregate

Interface: [SaleRepositoryInterface](../Src/Sales/Domain/Repositories/SaleRepositoryInterface.php)

Implementation: [SaleRepository](../Src/Sales/Infrastructure/Persistence/SaleRepository.php)

Handler hanya memanggil interface:

```text
SaleRepositoryInterface.store(sale)
```

Implementation repository melakukan:

- Simpan sale ke tabel sales.
- Simpan line items ke tabel sale line items.
- Publish domain events yang tercatat di aggregate.

### 12. Domain events dipublish

Event bus: [SimpleEventBus](../Src/Shared/Framework/Infrastructure/Bus/EventBus/SimpleEventBus.php)

Event listener mapping: [EventServiceProvider](../App/Providers/EventServiceProvider.php)

Contoh event handlers:

- [LogAuditTrailHandler](../Src/Sales/Application/EventHandlers/LogAuditTrailHandler.php)
- [ProjectSaleReportsOnSaleCompletedHandler](../Src/Sales/Application/EventHandlers/ProjectSaleReportsOnSaleCompletedHandler.php)
- [CalculateCommissionHandler](../Src/Sales/Application/EventHandlers/CalculateCommissionHandler.php)
- [UpdateCommissionProjectionHandler](../Src/Sales/Application/EventHandlers/UpdateCommissionProjectionHandler.php)

Pada create sale, aggregate mencatat event. Repository/infrastructure bertugas publish event tersebut.

### 13. Response dikembalikan ke user

Action mengembalikan [SaleCreatedRes](../Apps/Api/Sales/Shared/SaleCreatedRes.php), lalu controller membungkusnya menjadi JSON response.

```text
SaleCreatedRes
    |
    v
response()->json(resource, 201)
    |
    v
HTTP 201 Created
```

## Flow command side lainnya

### Confirm sale

Endpoint:

```text
POST /api/sales/{id}/confirm
```

File alur:

1. [Routes/api.php](../Routes/api.php)
2. [SalesController.php](../Apps/Api/Sales/SalesController.php)
3. [ConfirmSaleRequest.php](../Apps/Api/Sales/Confirm/ConfirmSaleRequest.php)
4. [ConfirmSaleDto.php](../Apps/Api/Sales/Confirm/ConfirmSaleDto.php)
5. [ConfirmSaleAction.php](../Apps/Api/Sales/Confirm/ConfirmSaleAction.php)
6. Confirm sale command di [Src/Sales/Application/Commands/Confirm](../Src/Sales/Application/Commands/Confirm)
7. [ConfirmSaleHandler.php](../Src/Sales/Application/Commands/Confirm/ConfirmSaleHandler.php)
8. [SaleRepositoryInterface](../Src/Sales/Domain/Repositories/SaleRepositoryInterface.php)
9. [Sale.php](../Src/Sales/Domain/Entities/Sale.php)
10. [SaleRepository.php](../Src/Sales/Infrastructure/Persistence/SaleRepository.php)

Flow ringkas:

```text
Request confirm
    -> DTO
    -> ConfirmSaleCommand
    -> ConfirmSaleHandler
    -> SaleRepository.getById()
    -> Sale.confirm()
    -> SaleRepository.store()
    -> response
```

### Cancel sale

Endpoint:

```text
POST /api/sales/{id}/cancel
```

File alur:

1. [CancelSaleRequest.php](../Apps/Api/Sales/Cancel/CancelSaleRequest.php)
2. [CancelSaleDto.php](../Apps/Api/Sales/Cancel/CancelSaleDto.php)
3. [CancelSaleAction.php](../Apps/Api/Sales/Cancel/CancelSaleAction.php)
4. Cancel sale command di [Src/Sales/Application/Commands/Cancel](../Src/Sales/Application/Commands/Cancel)
5. [CancelSaleHandler.php](../Src/Sales/Application/Commands/Cancel/CancelSaleHandler.php)
6. [Sale.php](../Src/Sales/Domain/Entities/Sale.php)
7. [SaleRepository.php](../Src/Sales/Infrastructure/Persistence/SaleRepository.php)

Flow ringkas:

```text
Request cancel
    -> DTO
    -> CancelSaleCommand
    -> CancelSaleHandler
    -> SaleRepository.getById()
    -> Sale.cancel()
    -> SaleRepository.store()
    -> response
```

### Complete sale

Endpoint:

```text
POST /api/sales/{id}/complete
```

File alur:

1. [CompleteSaleRequest.php](../Apps/Api/Sales/Complete/CompleteSaleRequest.php)
2. [CompleteSaleDto.php](../Apps/Api/Sales/Complete/CompleteSaleDto.php)
3. [CompleteSaleAction.php](../Apps/Api/Sales/Complete/CompleteSaleAction.php)
4. Complete sale command di [Src/Sales/Application/Commands/Complete](../Src/Sales/Application/Commands/Complete)
5. [CompleteSaleHandler.php](../Src/Sales/Application/Commands/Complete/CompleteSaleHandler.php)
6. [Sale.php](../Src/Sales/Domain/Entities/Sale.php)
7. [SaleRepository.php](../Src/Sales/Infrastructure/Persistence/SaleRepository.php)

Flow ringkas:

```text
Request complete
    -> DTO
    -> CompleteSaleCommand
    -> CompleteSaleHandler
    -> SaleRepository.getById()
    -> commission/payment ports if needed
    -> Sale.complete()
    -> SaleRepository.store()
    -> domain events
    -> response
```

## Flow query side: read data/reporting

Query side dipakai untuk mengambil data, bukan mengubah state.

Endpoint query:

```text
GET /api/sales
GET /api/sales/{id}
GET /api/sales/reports/sales
GET /api/sales/reports/commissions
```

Flow query side:

```text
HTTP request
    |
    v
SalesController
    |
    v
Request object -> DTO
    |
    v
Action
    |
    v
QueryBus
    |
    v
QueryHandler
    |
    v
ReadModelRepository
    |
    v
Read database/projection
    |
    v
Response resource
```

File query/reporting terkait:

- [IndexSalesAction.php](../Apps/Api/Sales/Index/IndexSalesAction.php)
- [IndexSalesRequest.php](../Apps/Api/Sales/Index/IndexSalesRequest.php)
- [IndexSalesDto.php](../Apps/Api/Sales/Index/IndexSalesDto.php)
- [ShowSaleAction.php](../Apps/Api/Sales/Show/ShowSaleAction.php)
- [ShowSaleRequest.php](../Apps/Api/Sales/Show/ShowSaleRequest.php)
- [ShowSaleDto.php](../Apps/Api/Sales/Show/ShowSaleDto.php)
- [SalesReportAction.php](../Apps/Api/Sales/Reports/SalesReportAction.php)
- [CommissionSummaryAction.php](../Apps/Api/Sales/Reports/CommissionSummaryAction.php)
- [SaleReadModelRepositoryInterface](../Src/Sales/Domain/Repositories/SaleReadModelRepositoryInterface.php)
- [SaleReadModelRepository](../Src/Sales/Infrastructure/Persistence/SaleReadModelRepository.php)

Perbedaan penting:

| Command side | Query side |
| --- | --- |
| Mengubah state | Membaca data |
| Pakai command bus | Pakai query bus |
| Handler memuat aggregate | Handler membaca read model/projection |
| Pakai repository write side | Pakai read model repository |
| Domain invariant dijalankan | Tidak menjalankan state transition |

## Dependency injection dan binding

File binding utama: [AppServiceProvider.php](../App/Providers/AppServiceProvider.php)

Binding penting:

```text
CommandBusInterface -> SimpleCommandBus
QueryBusInterface -> QueryBus
EventBusInterface -> SimpleEventBus
SaleRepositoryInterface -> SaleRepository
SaleReadModelRepositoryInterface -> SaleReadModelRepository
CustomerExistenceCheckerInterface -> EloquentCustomerExistenceChecker
ProductCatalogInterface -> EloquentProductCatalog
PaymentGatewayInterface -> Payment adapter
CommissionCalculatorInterface -> Commission adapter
```

Dengan binding ini, application handler cukup request interface. Laravel container akan memberikan implementation yang sesuai.

## Kenapa flow ini dibuat seperti ini?

### 1. Business rules aman di domain

Business rules tidak tersebar di controller/action/repository. Semua invariant penting tetap ada di [Sale.php](../Src/Sales/Domain/Entities/Sale.php) dan value object.

### 2. HTTP layer tetap thin

HTTP layer hanya mapping input dan output. Ini membuat API adapter mudah diganti tanpa mengubah domain.

### 3. Infrastructure bisa diganti

Karena application bergantung pada interface, implementation infrastructure bisa diganti tanpa mengubah use case. Contoh: [EloquentProductCatalog](../Src/Sales/Infrastructure/Product/EloquentProductCatalog.php) bisa diganti dengan adapter ke external product service.

### 4. CQRS lebih jelas

Command side fokus mutation dan aggregate. Query side fokus read model/projection/reporting.

### 5. Testing lebih mudah

Handler bisa dites dengan fake repository/port tanpa database. Contoh test:

- [CreateSaleActionTest.php](../Tests/Unit/Sales/CreateSaleActionTest.php)
- [CreateSaleHandlerTest.php](../Tests/Unit/Sales/Handlers/CreateSaleHandlerTest.php)

## Checklist ketika membuat endpoint baru

Jika nanti membuat endpoint Sales baru, ikuti urutan ini:

1. Tambahkan route di [Routes/api.php](../Routes/api.php).
2. Tambahkan method di [SalesController.php](../Apps/Api/Sales/SalesController.php).
3. Buat request object di folder API endpoint terkait.
4. Buat DTO untuk input API.
5. Buat action sebagai adapter tipis.
6. Buat command/query di application layer.
7. Buat handler di application layer.
8. Jika perlu data dari luar domain, buat port interface.
9. Implementasikan port di infrastructure adapter.
10. Register binding di [AppServiceProvider.php](../App/Providers/AppServiceProvider.php).
11. Pastikan domain business rule ada di aggregate/value object, bukan controller/action.
12. Tambahkan unit/feature test sesuai flow.

## Ringkasan file penting

| Fungsi | File |
| --- | --- |
| API routes | [Routes/api.php](../Routes/api.php) |
| Sales controller | [SalesController.php](../Apps/Api/Sales/SalesController.php) |
| Create request | [CreateSaleRequest.php](../Apps/Api/Sales/Create/CreateSaleRequest.php) |
| Create API DTO | [CreateSaleDto.php](../Apps/Api/Sales/Create/CreateSaleDto.php) |
| Create action | [CreateSaleAction.php](../Apps/Api/Sales/Create/CreateSaleAction.php) |
| Create command | [CreateSaleCommand.php](../Src/Sales/Application/Commands/Create/CreateSaleCommand.php) |
| Create command item | [CreateSaleLineItem.php](../Src/Sales/Application/Commands/Create/CreateSaleLineItem.php) |
| Create handler | [CreateSaleHandler.php](../Src/Sales/Application/Commands/Create/CreateSaleHandler.php) |
| Sale aggregate | [Sale.php](../Src/Sales/Domain/Entities/Sale.php) |
| Product catalog port | [ProductCatalogInterface.php](../Src/Sales/Domain/Ports/ProductCatalogInterface.php) |
| Product catalog adapter | [EloquentProductCatalog.php](../Src/Sales/Infrastructure/Product/EloquentProductCatalog.php) |
| Customer checker port | [CustomerExistenceCheckerInterface.php](../Src/Sales/Domain/Ports/CustomerExistenceCheckerInterface.php) |
| Customer checker adapter | [EloquentCustomerExistenceChecker.php](../Src/Sales/Infrastructure/Customer/EloquentCustomerExistenceChecker.php) |
| Sale repository interface | [SaleRepositoryInterface.php](../Src/Sales/Domain/Repositories/SaleRepositoryInterface.php) |
| Sale repository implementation | [SaleRepository.php](../Src/Sales/Infrastructure/Persistence/SaleRepository.php) |
| Read model repository interface | [SaleReadModelRepositoryInterface.php](../Src/Sales/Domain/Repositories/SaleReadModelRepositoryInterface.php) |
| Read model repository implementation | [SaleReadModelRepository.php](../Src/Sales/Infrastructure/Persistence/SaleReadModelRepository.php) |
| Command bus | [SimpleCommandBus.php](../Src/Shared/Framework/Infrastructure/Bus/CommandBus/SimpleCommandBus.php) |
| Query bus | [QueryBus.php](../Src/Shared/Framework/Infrastructure/Bus/QueryBus/QueryBus.php) |
| Event bus | [SimpleEventBus.php](../Src/Shared/Framework/Infrastructure/Bus/EventBus/SimpleEventBus.php) |
| DI binding | [AppServiceProvider.php](../App/Providers/AppServiceProvider.php) |
| Event listener mapping | [EventServiceProvider.php](../App/Providers/EventServiceProvider.php) |
