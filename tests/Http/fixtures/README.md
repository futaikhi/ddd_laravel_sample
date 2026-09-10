# Sales CSV Import — Test Fixtures

Sample CSV files for exercising the `POST /api/sales/import-csv` endpoint.

## Files

### [`sales-import-sample.csv`](sales-import-sample.csv)

A minimal happy-path sample containing **3 sales** across **6 line-item rows**.

| import_ref | line items | customer_id                  |
| ---------- | ---------- | ---------------------------- |
| `sale-001` | 3          | `01H8M6KJ5NQ8XX4P0N2VYJ4K5A` |
| `sale-002` | 2          | `01H8M6KJ5NQ8XX4P0N2VYJ4K5B` |
| `sale-003` | 1          | `01H8M6KJ5NQ8XX4P0N2VYJ4K5C` |

## CSV Format

Required header row (exact column names, order does not matter):

```
import_ref,customer_id,product_id,quantity
```

| Column        | Type    | Notes                                                                             |
| ------------- | ------- | --------------------------------------------------------------------------------- |
| `import_ref`  | string  | Temporary grouping key (e.g. `sale-001`). Not stored. All rows with the same value are combined into one sale. |
| `customer_id` | ULID    | Must be the same across every row sharing the same `import_ref`.                  |
| `product_id`  | ULID    | Must be unique within a single `import_ref` group.                                |
| `quantity`    | integer | Must be `>= 1`.                                                                   |

## Usage

### cURL

```bash
curl -X POST http://localhost:8000/api/sales/import-csv \
  -H "Accept: application/json" \
  -F "file=@Tests/Http/fixtures/sales-import-sample.csv"
```

### Expected Response (201 Created)

```json
{
  "total_rows": 6,
  "created_sales": 3,
  "failed_rows": 0,
  "sales": [
    { "import_ref": "sale-001", "sale_id": "01J...", "invoice_number": "INV-20260910-0001" },
    { "import_ref": "sale-002", "sale_id": "01J...", "invoice_number": "INV-20260910-0002" },
    { "import_ref": "sale-003", "sale_id": "01J...", "invoice_number": "INV-20260910-0003" }
  ],
  "errors": []
}
```

## Important

Before importing, ensure the referenced `customer_id` and `product_id` values exist in the database. Replace the ULIDs in the sample CSV with real IDs from your `customers` and `products` tables, otherwise the import will fail with domain errors (customer-not-found / product-not-found).

You can obtain valid IDs by seeding via the existing customer/product endpoints or from your database:

```sql
SELECT id FROM customers LIMIT 3;
SELECT id FROM products LIMIT 5;
```

## Failure Mode

The endpoint uses a **fail-entire-import** strategy: if any row is invalid (bad ULID, duplicate product_id inside a group, mismatched customer_id for the same `import_ref`, missing customer, unknown product, etc.), the endpoint returns **422 Unprocessable Entity** with per-row errors and **no sales are created**.
