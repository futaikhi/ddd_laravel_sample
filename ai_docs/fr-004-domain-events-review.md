# FR-004: Domain Events & Event Handlers - Completion Review

**Date**: 2026-09-02  
**Status**: COMPLETE  
**Validated Tests**: 5 focused tests passing (`SalesDomainEventsTest`, `SimpleEventBusTest`)  
**Manual Async Validation**: `CompleteSaleCommand` → database queue → `DomainEventJob` → event handlers → audit/projections

---

## Executive Summary

FR-004 implements event-driven side effects for the Sales bounded context. Sales aggregate methods record domain events, command handlers publish released events after persistence, and Laravel listeners execute decoupled side effects.

Implemented capabilities:

- Domain events for every Sales state change.
- Explicit `CommissionCalculatedEvent` for commission process visibility.
- Event handlers for confirmation email, sales metrics, commission projection, and audit trail.
- Append-only `domain_events` event store.
- Configurable sync/async dispatch.
- Queue-based retry policy for async domain events.

---

## Domain Events

Location: `src/Sales/Domain/Events/`

| Event | Purpose | Key Payload |
|---|---|---|
| `SaleCreatedEvent` | Sale intent recorded | `saleId`, `customerId`, `totalAmount`, `occurredOn` |
| `SaleConfirmedEvent` | Sale confirmed after payment | `saleId`, `confirmedAt`, `occurredOn` |
| `SaleCompletedEvent` | Sale completed with locked commission data | `saleId`, `completedAt`, `totalAmount`, `commissionAmount`, `commissionRate`, `commissionCurrency` |
| `SaleCancelledEvent` | Sale cancelled with reason | `saleId`, `reason`, `cancelledAt`, `occurredOn` |
| `CommissionCalculatedEvent` | Commission fact made explicit for projections/audit | `saleId`, `amount`, `percentage`, `currency`, `calculatedAt` |

All domain events extend `Src\Shared\Framework\Domain\Events\DomainEvent`, which provides `occurredOn` timestamp.

---

## Event Recording Flow

The aggregate records events inside domain methods:

- `Sale::create()` records `SaleCreatedEvent`
- `Sale::confirm()` records `SaleConfirmedEvent`
- `Sale::complete()` records `SaleCompletedEvent`
- `Sale::cancel()` records `SaleCancelledEvent`

Command handlers persist the aggregate, then publish released domain events:

- `CreateSaleHandler`
- `ConfirmSaleHandler`
- `CompleteSaleHandler`
- `CancelSaleHandler`

This keeps command handlers focused on application orchestration while side effects are handled by listeners.

---

## Event Handlers

Location: `src/Sales/Application/EventHandlers/`

| Handler | Listens To | Responsibility |
|---|---|---|
| `SendConfirmationEmailHandler` | `SaleConfirmedEvent` | Decoupled notification side effect; currently logs email intent |
| `UpdateSalesMetricsHandler` | `SaleCompletedEvent` | Updates sales metrics cache projection |
| `CalculateCommissionHandler` | `SaleCompletedEvent` | Publishes `CommissionCalculatedEvent` from completed sale commission payload |
| `UpdateCommissionProjectionHandler` | `CommissionCalculatedEvent` | Updates commission reporting cache projection |
| `LogAuditTrailHandler` | All Sales/commission events | Appends events to `domain_events` table |

Listener registration lives in `app/Providers/EventServiceProvider.php`.

---

## Dispatch Modes

The event bus is implemented in:

- `src/Shared/Framework/Infrastructure/Bus/EventBus/SimpleEventBus.php`
- `src/Shared/Framework/Infrastructure/Bus/EventBus/DomainEventJob.php`

Configuration lives in `config/sales.php`:

```env
SALES_EVENT_DISPATCH_MODE=sync|async
SALES_EVENT_QUEUE=domain-events
SALES_EVENT_RETRY_TRIES=3
SALES_EVENT_RETRY_BACKOFF=60,120,300
SALES_EVENT_RETRY_MAX_EXCEPTIONS=3
```

### Sync Mode

`SimpleEventBus` calls Laravel `event($event)` immediately. Listeners run in the current request/job.

### Async Mode

`SimpleEventBus` dispatches `DomainEventJob` to the configured queue. The queue worker later calls `event($event)` inside the job.

Manual validation was completed using database queue:

```text
CompleteSaleCommand
  → SaleCompletedEvent released
  → DomainEventJob inserted into jobs queue=domain-events
  → php artisan queue:work --once --queue=domain-events
  → event handlers executed
  → jobs=0, failed_jobs=0, domain_events contains sale.completed
```

---

## Retry Mechanism

`DomainEventJob` exposes Laravel queue retry properties:

- `$tries`
- `$backoff`
- `$maxExceptions`

Values are loaded from `config/sales.php`. When all retries are exhausted, `DomainEventJob::failed(Throwable $exception)` logs the permanent failure with event name, event type, occurrence timestamp, exception class, and message.

---

## Audit Trail

Port:

- `Src\Shared\Framework\Domain\Events\DomainEventStoreInterface`

Adapter:

- `Src\Shared\Framework\Infrastructure\Events\EloquentDomainEventStore`

Binding:

- `App\Providers\AppServiceProvider`

Table:

- `domain_events`

Stored fields:

- `id`
- `aggregate_id`
- `aggregate_type`
- `event_type`
- `event_name`
- `event_data`
- `occurred_at`
- `recorded_at`

---

## Tests

Focused validation:

- `tests/Feature/Api/Sales/SalesDomainEventsTest.php`
  - audit trail is recorded when sale is cancelled
  - sale completion records `sale.completed`
  - commission flow records `commission.calculated`
  - sales metrics projection is updated
  - commission projection is updated

- `tests/Unit/Shared/EventBus/SimpleEventBusTest.php`
  - sync dispatch uses Laravel event system immediately
  - async dispatch queues `DomainEventJob`
  - retry policy is loaded from configuration

Latest focused result:

```text
5 tests passed, 0 failed
```

---

## Acceptance Criteria Mapping

| AC-004 Requirement | Status |
|---|---:|
| Domain events defined in `Src/Sales/Domain/Events` | ✅ |
| Events recorded by aggregate methods | ✅ |
| Event subscribers registered | ✅ |
| Confirmation side effect decoupled | ✅ |
| Sales metrics projection decoupled | ✅ |
| Commission calculation event introduced | ✅ |
| Audit trail for all events | ✅ |
| Event store table implemented | ✅ |
| Sync/async dispatch configurable | ✅ |
| Retry mechanism for failed async handlers | ✅ |
| Feature tests validate event persistence/projections | ✅ |

---

## Notes

- `SendConfirmationEmailHandler` currently logs confirmation email intent instead of sending a real email. This is sufficient for this sample and keeps infrastructure replaceable.
- Command handlers publish events after repository persistence, consistent with existing project conventions.
- The Sales domain layer remains framework-agnostic; Laravel-specific event dispatch, queues, cache, and database event store live outside the domain.
