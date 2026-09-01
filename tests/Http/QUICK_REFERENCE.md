# Sales API — Quick Reference

**Base URL:** `http://localhost:8000/api`
**Swagger UI:** `http://localhost:8000/api/documentation`
**Spec:** [`sales-api-openapi.yaml`](./sales-api-openapi.yaml) · [Postman](./sales-api-postman-collection.json)

---

## Architecture Cheat Sheet

| Concern | Where it lives |
|--------|----------------|
| Business rules & state machine | `Sale` aggregate (`src/Sales/Domain/Entities/Sale.php`) |
| Payment orchestration          | `ConfirmSaleHandler` + `PaymentGatewayInterface` (port) |
| Commission calculation         | `CompleteSaleHandler` + `CommissionCalculatorInterface` (port) |
| Write persistence              | `SaleRepositoryInterface` → `SaleRepository` (Eloquent adapter) |
| Read persistence               | `SaleReadModelRepositoryInterface` → `SaleReadModelRepository` |
| HTTP → Command dispatch        | `CommandBusInterface` (`SimpleCommandBus`) |
| HTTP → Query dispatch          | `QueryBusInterface` (`QueryBus`) |
| Domain events                  | `EventBusInterface` (`SimpleEventBus`) |
| Adapter binding                | `App\Providers\AppServiceProvider::registerSalesAdapters()` (env-aware: mock in `testing`, real elsewhere) |

## HTTP Status Codes

| Status | When |
|-------:|------|
| `200`  | Query succeeded / command succeeded (`{ "message": "..." }`) |
| `201`  | Resource created (Customer, Product, Sale) |
| `402`  | Payment gateway rejected the payment (sale stays `PENDING`) |
| `404`  | Sale not found |
| `409`  | Invalid state transition (e.g. confirm an already-confirmed sale) |
| `422`  | Request payload semantically invalid (missing field, unknown enum, bad ULID, …) |
| `502`  | Unexpected payment validation adapter error |

Error envelope: `{ "error": "<slug>", "message": "<human message>" }`

---

## Setup — Create Test Data

### 1. Create a Customer

```bash
curl -X POST http://localhost:8000/api/customers \
  -H "Content-Type: application/json" \
  -d '{
    "name": "PT Maju Jaya",
    "email": "contact@majujaya.com",
    "phone": "021-555-1234"
  }'

# → { "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV", "message": "Customer created successfully" }
# CUSTOMER_ID=01ARZ3NDEKTSV4RRFFQ69G5FAV
```

### 2. Create Products

```bash
# Product 1: Laptop (Rp 15.000.000)
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{ "name": "Laptop Dell XPS 15", "sku": "DEL-XPS15-2024", "price": 15000000, "currency": "IDR" }'
# PRODUCT_ID_1=01ARZ3NDEKTSV4RRFFQ69G5FAW

# Product 2: Mouse (Rp 500.000)
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{ "name": "Logitech MX Master 3", "sku": "LGT-MXM3-2024", "price": 500000, "currency": "IDR" }'
# PRODUCT_ID_2=01ARZ3NDEKTSV4RRFFQ69G5FAX
```

---

## Sales Order Lifecycle — Happy Path

State machine:

```
PENDING ── confirm ──▶ CONFIRMED ── complete ──▶ COMPLETED
   │                       │
   └── cancel ──▶ CANCELLED ◀── cancel ──┘
```

### Step 1 · Create Sale (→ PENDING)

```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "line_items": [
      { "product_id": "01ARZ3NDEKTSV4RRFFQ69G5FAW", "quantity": 1 },
      { "product_id": "01ARZ3NDEKTSV4RRFFQ69G5FAX", "quantity": 5 }
    ]
  }'

# → 201 { "id": "01ARZ3NDEKTSV4RRFFQ69G5FAY", "message": "Sale created successfully" }
# SALE_ID=01ARZ3NDEKTSV4RRFFQ69G5FAY
```

### Step 2 · Confirm Sale (→ CONFIRMED)

`payment_method` **must** be one of the whitelisted `PaymentMethod` enum values.

```bash
curl -X POST http://localhost:8000/api/sales/$SALE_ID/confirm \
  -H "Content-Type: application/json" \
  -d '{ "payment_method": "credit_card" }'

# → 200 { "message": "Sale confirmed successfully" }
```

Under the hood: `ConfirmSaleHandler` calls `PaymentGatewayInterface::process()`. There is no real external payment gateway in this sample project. In `testing`, the port is implemented by `MockPaymentGatewayAdapter`; in other environments it uses `LaravelPaymentGatewayAdapter`, which only validates the request locally and returns a local transaction id. The returned `transactionId` is locked into the aggregate.

### Step 3 · Complete Sale (→ COMPLETED, commission calculated)

```bash
curl -X POST http://localhost:8000/api/sales/$SALE_ID/complete \
  -H "Content-Type: application/json" \
  -d '{}'

# → 200 { "message": "Sale completed successfully" }
```

`CompleteSaleHandler` calls `CommissionCalculatorInterface::calculate($sale)`. The default
`DatabaseCommissionService` applies **5%** if total > 1.000.000, otherwise **3%**.
The resulting `Commission` value object is locked into the aggregate.

---

## PaymentMethod Enum

Allowed values (backed by `Src\Sales\Domain\Enums\PaymentMethod`):

| Value           | Description                     |
|-----------------|---------------------------------|
| `credit_card`   | Credit / debit card             |
| `bank_transfer` | Manual or virtual account       |
| `e_wallet`      | GoPay, OVO, Dana, ShopeePay, …  |
| `cash`          | Cash on delivery / in-store     |

Input is case-insensitive (`"CREDIT_CARD"` and `" cash "` are accepted).
Unknown values return `422`:

```json
{
  "error": "validation_failed",
  "message": "Invalid payment method 'bitcoin'. Allowed: credit_card, bank_transfer, e_wallet, cash."
}
```

---

## Query Endpoints (CQRS Read Side)

### Get Sale Detail

```bash
curl http://localhost:8000/api/sales/$SALE_ID
```

Returns `SaleDetailRM` — flat DTO with `paymentMethod`, `transactionId`, `commissionAmount`,
`commissionRate`, timestamps, and line items.

### List Sales (filter + pagination)

```bash
# All sales
curl "http://localhost:8000/api/sales"

# Only CONFIRMED sales for one customer, page size 10
curl "http://localhost:8000/api/sales?customer_id=$CUSTOMER_ID&status=confirmed&limit=10&offset=0"

# All sales in August 2026
curl "http://localhost:8000/api/sales?date_from=2026-08-01%2000:00:00&date_to=2026-08-31%2023:59:59"
```

Query params:

| Param         | Notes                                                       |
|---------------|-------------------------------------------------------------|
| `customer_id` | ULID                                                        |
| `status`      | `pending` \| `confirmed` \| `completed` \| `cancelled`      |
| `date_from`   | `YYYY-MM-DD HH:MM:SS` (matched against `created_at ≥`)      |
| `date_to`     | `YYYY-MM-DD HH:MM:SS` (matched against `created_at ≤`)      |
| `limit`       | default `20`, max `200`                                     |
| `offset`      | default `0`                                                 |

Response envelope:

```json
{
  "items":      [ { "id": "...", "status": "confirmed", "totalAmount": 30500000, ... } ],
  "pageSize":   20,
  "page":       1,
  "totalCount": 87
}
```

### Daily Sales Report

```bash
curl "http://localhost:8000/api/sales/reports/sales?from=2026-08-01%2000:00:00&to=2026-08-31%2023:59:59"
```

Returns an array of `SalesReportRM` — one entry per day with `salesCount` and `revenueTotal`
(only `COMPLETED` sales are aggregated).

### Daily Commission Summary

```bash
curl "http://localhost:8000/api/sales/reports/commissions?from=2026-08-01%2000:00:00&to=2026-08-31%2023:59:59"
```

Returns `CommissionSummaryRM[]` — per-day `completedSalesCount` + `totalCommission`.

---

## Cancel Path

```bash
curl -X POST http://localhost:8000/api/sales/$SALE_ID/cancel \
  -H "Content-Type: application/json" \
  -d '{ "reason": "Customer requested cancellation" }'
```

Allowed only from `PENDING`. Cancelling a `CONFIRMED`, `COMPLETED`, or `CANCELLED` sale
returns `409 Conflict`.

---

## Error Examples

### 402 — Payment declined (sale stays `PENDING`)

```json
{ "error": "payment_failed", "message": "Insufficient funds" }
```

### 409 — Invalid state transition

```json
{ "error": "invalid_sale_state", "message": "Sale cannot be confirmed: status is 'completed'" }
```

### 422 — Missing / invalid payload

```json
{ "error": "validation_failed", "message": "payment_method is required" }
```

### 502 — Payment gateway unavailable

```json
{ "error": "payment_gateway_error", "message": "Payment validation error: unexpected adapter failure" }
```

---

## Testing Tips

- **Testing environment** wires `MockPaymentGatewayAdapter` and `MockCommissionService` in
  `AppServiceProvider::registerSalesAdapters()`, so tests stay deterministic and do not require
  any external service.
- To simulate payment failure in a feature test:
  ```php
  $mock = app(PaymentGatewayInterface::class); // MockPaymentGatewayAdapter
  $mock->setShouldSucceed(false);
  $mock->setFailureMessage('Card declined');
  ```
- To pin the commission rate:
  ```php
  $mock = app(CommissionCalculatorInterface::class); // MockCommissionService
  $mock->setFixedRate(7.5);
  ```
