# AC-003 CQRS Command Query Separation Compliance Status

Status: Compliant
Date: 2026-09-03
Scope: Sales Order Processing / FR-003 CQRS Separate Commands Queries

## Acceptance Criteria Mapping

| AC-003 requirement | Implementation evidence | Status |
| --- | --- | --- |
| Commands are used for write operations | `Src/Sales/Application/Commands/Create/CreateSaleCommand.php`, `Src/Sales/Application/Commands/Confirm/ConfirmSaleCommand.php`, `Src/Sales/Application/Commands/Complete/CompleteSaleCommand.php`, `Src/Sales/Application/Commands/Cancel/CancelSaleCommand.php` | Passed |
| Command handlers orchestrate state changes | `CreateSaleHandler`, `ConfirmSaleHandler`, `CompleteSaleHandler`, and `CancelSaleHandler` load/create the aggregate, call domain behavior, persist through write repository, and publish domain events | Passed |
| Command handlers do not return read data | Dedicated tests assert command handlers return `void`/`null` and only persist/publish events | Passed |
| Queries are separated from commands | Query classes exist under `Src/Sales/Application/Queries` for sale detail, list sales, sales report, and commission summary | Passed |
| Query handlers return read models, not entities | Query handlers depend on `SaleReadModelRepositoryInterface` and return read-model DTOs such as `SaleDetailRM`, `SaleListItemRM`, `SalesReportRM`, and `CommissionSummaryRM` | Passed |
| Read models are denormalized/optimized | Read model tables `sale_list_items`, `sales_reports`, and `commission_reports` are created by dedicated migration | Passed |
| Frequently queried read-model fields are indexed | Migration defines indexes for status/date, customer/date, created_at/id, report date/currency, commission period, and agent/period access patterns | Passed |
| Event projections keep read models synchronized | Projection handlers update read models for created, confirmed, completed, and cancelled sales events | Passed |
| List queries meet the 100ms target | Performance-oriented feature test seeds 1,000 projected sale list rows and asserts the measured `SaleReadModelRepository::paginate()` execution is under 100ms | Passed |
| Regression suite passes | `php run-ac-003-tests.php` passes with 27 tests and 172 assertions | Passed |

## Implemented Command Side

- `CreateSaleCommand` and `CreateSaleHandler` create the `Sale` aggregate, persist it through the write repository, and publish `SaleCreatedEvent`.
- `ConfirmSaleCommand` and `ConfirmSaleHandler` retrieve the aggregate, process payment, confirm the sale, persist it, and publish `SaleConfirmedEvent`.
- `CompleteSaleCommand` and `CompleteSaleHandler` retrieve the aggregate, calculate commission, complete the sale, persist it, and publish `SaleCompletedEvent`.
- `CancelSaleCommand` and `CancelSaleHandler` retrieve the aggregate, execute refund logic when required, cancel the sale, persist it, and publish `SaleCancelledEvent`.

Command handler tests verify that the command path is fire-and-forget and does not leak read-model data:

- `Tests/Unit/Sales/Handlers/CreateSaleHandlerTest.php`
- `Tests/Unit/Sales/Handlers/CompleteSaleHandlerTest.php`
- `Tests/Unit/Sales/Handlers/CancelSaleHandlerTest.php`

## Implemented Query Side

Query classes and handlers are separated under `Src/Sales/Application/Queries`:

- `GetSaleByIdQuery` / `GetSaleByIdHandler`
- `ListSalesQuery` / `ListSalesHandler`
- `GetSalesReportQuery` / `GetSalesReportHandler`
- `GetCommissionSummaryQuery` / `GetCommissionSummaryHandler`

The query side depends on `SaleReadModelRepositoryInterface`, not the write-side `SaleRepositoryInterface`. Query responses are read-model DTOs, not domain entities:

- `SaleDetailRM`
- `SaleListItemRM`
- `SalesReportRM`
- `CommissionSummaryRM`

## Read Model Tables and Indexes

The migration `Database/migrations/2026_09_03_000001_create_sales_read_model_tables.php` creates:

- `sale_list_items`
- `sales_reports`
- `commission_reports`

Important indexes include:

- `sale_list_items_status_created_at_idx`
- `sale_list_items_customer_created_at_idx`
- `sale_list_items_created_at_id_idx`
- `sales_reports_report_date_unique`
- `sales_reports_date_currency_idx`
- `commission_reports_period_idx`
- `commission_reports_agent_period_start_idx`
- `commission_reports_agent_period_unique`

## Projection Coverage

Registered AC-003 read-model projection handlers:

- `ProjectSaleListItemOnSaleCreatedHandler`
- `ProjectSaleListItemOnSaleConfirmedHandler`
- `ProjectSaleReportsOnSaleCompletedHandler`
- `ProjectSaleListItemOnSaleCancelledHandler`

These are registered in `App/Providers/EventServiceProvider.php` before non-read-model side-effect handlers where relevant, so read models are updated promptly after domain events are dispatched.

Projection tests verify:

- `SaleCreatedEvent` creates a pending `sale_list_items` row.
- `SaleConfirmedEvent` updates `sale_list_items.status` to confirmed.
- `SaleCompletedEvent` updates list status, increments `sales_reports`, and increments `commission_reports`.
- Repeated completion events accumulate report and commission counters.
- `SaleCancelledEvent` marks cancellation fields without touching aggregate reporting counters.

## Performance Coverage

`Tests/Feature/Sales/ReadModel/SalesReadModelListPerformanceTest.php` validates the AC-003 list-query performance path by:

1. Seeding 1,000 rows directly into the denormalized `sale_list_items` read model.
2. Measuring only `SaleReadModelRepository::paginate()` using `hrtime(true)`.
3. Asserting elapsed query execution time is less than 100ms.
4. Capturing SQL and asserting it uses `sale_list_items`.
5. Asserting it does not query write-side `sales` or `sale_line_items` tables for the list path.
6. Asserting required read-model indexes exist.

## Validation Command

A single runner is available:

```bash
php run-ac-003-tests.php
```

Latest validation result:

```text
OK (27 tests, 172 assertions)
```

## Final Compliance Conclusion

AC-003 is implemented and verified. The Sales module now separates write-side commands from read-side queries, returns read-model DTOs from query handlers, keeps denormalized read models synchronized through domain-event projections, uses indexed read-model tables for optimized reads, and includes regression/performance tests proving the list-query path stays under the stated 100ms acceptance target.
