# Sales API - Quick Reference Guide

**Base URL**: `http://localhost:8000/api`

## Setup: Create Test Data First

### 1. Create a Customer
```bash
curl -X POST http://localhost:8000/api/customers \
  -H "Content-Type: application/json" \
  -d '{
    "name": "PT Maju Jaya",
    "email": "contact@majujaya.com",
    "phone": "021-555-1234"
  }'

# Response:
# {
#   "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
#   "message": "Customer created successfully"
# }

# Save this ID as: CUSTOMER_ID=01ARZ3NDEKTSV4RRFFQ69G5FAV
```

### 2. Create Products
```bash
# Product 1: Laptop (15M IDR)
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Laptop Dell XPS 15",
    "sku": "DEL-XPS15-2024",
    "price": 15000000,
    "currency": "IDR"
  }'

# Response: { "id": "01ARZ3NDEKTSV4RRFFQ69G5FAW", ... }
# Save: PRODUCT_ID_1=01ARZ3NDEKTSV4RRFFQ69G5FAW

# Product 2: Mouse (500K IDR)
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Logitech MX Master 3",
    "sku": "LGT-MXM3-2024",
    "price": 500000,
    "currency": "IDR"
  }'

# Response: { "id": "01ARZ3NDEKTSV4RRFFQ69G5FAX", ... }
# Save: PRODUCT_ID_2=01ARZ3NDEKTSV4RRFFQ69G5FAX
```

---

## Sales Order Lifecycle

### Scenario: Happy Path (PENDING → CONFIRMED → COMPLETED)

#### Step 1: Create Sale (status: PENDING)
```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "line_items": [
      {
        "product_id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
        "quantity": 1
      },
      {
        "product_id": "01ARZ3NDEKTSV4RRFFQ69G5FAX",
        "quantity": 5
      }
    ]
  }'

# Response:
# {
#   "id": "01ARZ3NDEKTSV4RRFFQ69G5FAY",
#   "message": "Sale created successfully"
# }

# Save: SALE_ID=01ARZ3NDEKTSV4RRFFQ69G5FAY

# Total Amount Calculated:
# = (1 × 15,000,000) + (5 × 500,000)
# = 15,000,000 + 2,500,000
# = 17,500,000 IDR ✅ (meets minimum 50,000)
# ✅ 2 line items (meets maximum 20)
```

#### Step 2: Confirm Sale (PENDING → CONFIRMED)
```bash
curl -X POST http://localhost:8000/api/sales/01ARZ3NDEKTSV4RRFFQ69G5FAY/confirm \
  -H "Content-Type: application/json" \
  -d '{}'

# Response:
# {
#   "id": "01ARZ3NDEKTSV4RRFFQ69G5FAY",
#   "message": "Sale confirmed successfully"
# }

# Business Logic:
# - Payment gateway processes payment
# - Status locked to CONFIRMED
# - Event: SaleConfirmedEvent published
```

#### Step 3: Complete Sale (CONFIRMED → COMPLETED)
```bash
curl -X POST http://localhost:8000/api/sales/01ARZ3NDEKTSV4RRFFQ69G5FAY/complete \
  -H "Content-Type: application/json" \
  -d '{}'

# Response:
# {
#   "id": "01ARZ3NDEKTSV4RRFFQ69G5FAY",
#   "message": "Sale completed successfully"
# }

# Commission Calculation:
# Total = 17,500,000 IDR
# Since 17,500,000 > 1,000,000:
#   Commission = 5% × 17,500,000 = 875,000 IDR
# Event: SaleCompletedEvent published
```

---

### Scenario: Cancellation (PENDING → CANCELLED)

#### Create Sale
```bash
# Same as Step 1 above...
# SALE_ID=01ARZ3NDEKTSV4RRFFQ69G5FAZ (new ID)
```

#### Cancel Sale (only from PENDING)
```bash
curl -X POST http://localhost:8000/api/sales/01ARZ3NDEKTSV4RRFFQ69G5FAZ/cancel \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Customer changed mind"
  }'

# Response:
# {
#   "id": "01ARZ3NDEKTSV4RRFFQ69G5FAZ",
#   "message": "Sale cancelled successfully"
# }

# Business Logic:
# - Sale marked as CANCELLED
# - No payment processed
# - Event: SaleCancelledEvent published
```

---

## Error Scenarios

### 1. Invalid State Transition
```bash
# Try to complete a PENDING sale (should be CONFIRMED first)
curl -X POST http://localhost:8000/api/sales/01ARZ3NDEKTSV4RRFFQ69G5FAY/complete

# Error: 400
# {
#   "message": "Cannot complete a non-confirmed sale"
# }
```

### 2. Minimum Amount Violation
```bash
# Try to create sale below minimum (50,000)
curl -X POST http://localhost:8000/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "line_items": [
      {
        "product_id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
        "quantity": 0
      }
    ]
  }'

# Error: 400
# {
#   "message": "Sale total amount must be at least 50,000"
# }
```

### 3. Customer Not Found
```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "INVALID_ID",
    "line_items": [...]
  }'

# Error: 404
# {
#   "message": "Customer does not exist."
# }
```

### 4. Product Not Found
```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "line_items": [
      {
        "product_id": "INVALID_PRODUCT",
        "quantity": 1
      }
    ]
  }'

# Error: 404
# {
#   "message": "Product does not exist: INVALID_PRODUCT"
# }
```

### 5. Too Many Line Items
```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "line_items": [
      {"product_id": "...", "quantity": 1},
      {"product_id": "...", "quantity": 1},
      ... (21+ items)
    ]
  }'

# Error: 400
# {
#   "message": "Sale cannot have more than 20 line items"
# }
```

---

## HTTP Status Codes

| Status | Meaning | Example |
|--------|---------|---------|
| 201 | Created | Sale created successfully |
| 200 | OK | Sale confirmed/completed/cancelled |
| 400 | Bad Request | Validation error, business rule violated |
| 404 | Not Found | Customer/Product/Sale doesn't exist |
| 503 | Service Unavailable | Payment gateway error |

---

## Status Flow Diagram

```
┌─────────────────────────────────────────────────┐
│               PENDING                           │
│  (Initial status after creation)                │
├─────────────────────────────────────────────────┤
│  Actions:                                       │
│  • POST /sales/:id/confirm → CONFIRMED         │
│  • POST /sales/:id/cancel  → CANCELLED         │
└─────────────────────────────────────────────────┘
        │                           │
        │ confirm                   │ cancel
        │                           │
        ↓                           ↓
┌─────────────────────────────────────────────────┐
│              CONFIRMED                          │
│  (Payment processed)                            │
├─────────────────────────────────────────────────┤
│  Actions:                                       │
│  • POST /sales/:id/complete → COMPLETED       │
└─────────────────────────────────────────────────┘
        │
        │ complete
        │
        ↓
┌─────────────────────────────────────────────────┐
│             COMPLETED                           │
│  (Commission calculated, finalized)             │
├─────────────────────────────────────────────────┤
│  Actions: None (terminal state)                 │
└─────────────────────────────────────────────────┘

                                   ┌─────────────────────────────────────────────────┐
                                   │            CANCELLED                            │
                                   │  (Cancelled from PENDING)                       │
                                   ├─────────────────────────────────────────────────┤
                                   │  Actions: None (terminal state)                 │
                                   └─────────────────────────────────────────────────┘
```

---

## Business Rules Summary

### Creation (PENDING)
- ✅ Customer must exist
- ✅ All products must exist
- ✅ Total amount ≥ Rp 50,000
- ✅ Max 20 line items
- ✅ Each line item quantity > 0
- ✅ Product prices fetched from database

### Confirmation (PENDING → CONFIRMED)
- ✅ Only from PENDING state
- ✅ Payment processed via gateway
- ✅ Cannot transition if payment fails

### Completion (CONFIRMED → COMPLETED)
- ✅ Only from CONFIRMED state
- ✅ Commission calculated:
  - Amount > Rp 1,000,000: 5%
  - Amount ≤ Rp 1,000,000: 3%

### Cancellation (PENDING → CANCELLED)
- ✅ Only from PENDING state
- ✅ No payment processed

---

## Testing with Postman/cURL

### Import Collection
1. Download `tests/Http/sales-api-postman-collection.json`
2. Import into Postman
3. Set variables:
   - `base_url`: `http://localhost:8000/api`
   - `customer_id`: Your customer ULID
   - `product_id_1`: Your product ULID
   - `sale_id`: Your sale ULID
4. Run requests in order

### cURL Tips
```bash
# Pretty print JSON response
curl ... | jq .

# Save response to file
curl ... > response.json

# Extract ID from response
ID=$(curl ... | jq -r .id)
echo "ID: $ID"

# Use saved ID in next request
curl -X POST http://localhost:8000/api/sales/$ID/confirm
```

---

## DDD Concepts in API

### Value Objects in Domain
- `SaleId`, `CustomerId`, `ProductId` (identifiers)
- `Money` (amount + currency)
- `LineItem` (product + quantity + price)
- `OrderStatus` (enum: PENDING, CONFIRMED, COMPLETED, CANCELLED)
- `Commission` (amount + rate)
- `PaymentRequest`, `PaymentResult` (port input/output)

### Aggregate Root in API
- `Sale` is the aggregate root
- All operations go through Sale
- Sale enforces business rules & state machine
- No direct line item manipulation

### Ports & Adapters in API
- `PaymentGatewayInterface` → hidden from API
  - Real: `LaravelPaymentGatewayAdapter`
  - Test: `MockPaymentGatewayAdapter`
- `CommissionCalculatorInterface` → hidden from API
  - Real: `DatabaseCommissionService`
  - Test: `MockCommissionService`

---

## References

- **Full Documentation**: `ai_docs/fr-002-hexagonal-review.md`
- **API Specification**: `tests/Http/sales-api-openapi.yaml`
- **Postman Collection**: `tests/Http/sales-api-postman-collection.json`
- **Domain Code**: `src/Sales/Domain/`
- **Tests**: `tests/Unit/Sales/`, `tests/Unit/DependencyInjection/`
