# BUSINESS LOGIC DEEP DIVE Gap Tracker

Source: `Task.txt` section `BUSINESS LOGIC DEEP DIVE`.

Purpose: track each mismatch/gap one by one so the implementation can be adjusted and verified case by case.

## Summary

| Case | Gap | Priority | Status |
|---|---|---:|---|
| GAP-001 | Customer existence check on sale creation is not explicit in `CreateSaleHandler` | High | Resolved |
| GAP-002 | Commission tier rules do not fully match `Task.txt` | High | Resolved |
| GAP-003 | Confirmed cancellation policy conflicts with newer business decision | High | Resolved (kept Task.txt behavior) |
| GAP-004 | Customer notification on cancellation is missing | Medium | Resolved |
| GAP-005 | Agent ownership for commission is not implemented | Medium | Resolved |
| GAP-006 | Monthly commission withdrawal flow is not implemented | Low | Open |
| GAP-007 | Agent commission dashboard/read model naming is not fully aligned | Low | Open |
| GAP-008 | Stock check and external price lock are still hypothetical | Low | Deferred |

---

## GAP-001 — Customer existence check on sale creation

### Task expectation

`Task.txt` says `CreateSaleCommand(customerId, items[])` must validate that the customer exists.

### Current implementation evidence

- `Src/Sales/Application/Commands/Create/CreateSaleHandler.php` creates and stores `Sale` directly.
- `Src/Sales/Domain/Entities/Sale.php` validates line items and amount, but it should not query persistence.

### Gap

There is no explicit customer lookup in `CreateSaleHandler` before creating the sale.

### Suggested implementation

Add a customer lookup port/repository in application layer, for example:

- `CustomerRepositoryInterface` or `CustomerExistenceCheckerInterface`
- Throw `CustomerNotFoundException` if customer does not exist.

### Acceptance checks

- Creating sale with unknown customer throws `CustomerNotFoundException`.
- Creating sale with valid customer still creates pending sale.
- Unit test covers valid and invalid customer paths.

### Candidate files

- `Src/Sales/Application/Commands/Create/CreateSaleHandler.php`
- `Src/Sales/Domain/Exceptions/CustomerNotFoundException.php`
- `Tests/Unit/Sales/Handlers/CreateSaleHandlerTest.php`

---

## GAP-002 — Commission tier rules not fully matching `Task.txt`

### Task expectation

Commission rules in `Task.txt`:

- Total amount >= 1,000,000 => 5%
- Else total amount >= 500,000 => 3%
- Else => 1%

### Current implementation evidence

- `Src/Sales/Infrastructure/Commission/DatabaseCommissionService.php` currently uses:
  - amount > 1,000,000 => 5%
  - else => 3%
- `Src/Sales/Infrastructure/Commission/MockCommissionService.php` uses fixed 3%.

### Gap

The lower tier `else => 1%` is missing. Boundary condition should also be confirmed: task wording suggests `>=`, current code uses `>` for 1,000,000.

### Suggested implementation

Update commission rate calculation:

```php
if ($amount >= 1000000) {
    return 5.0;
}

if ($amount >= 500000) {
    return 3.0;
}

return 1.0;
```

Optionally make `MockCommissionService` configurable or keep fixed for tests only.

### Acceptance checks

- Sale total 1,000,000 returns 5%.
- Sale total 999,999 returns 3%.
- Sale total 500,000 returns 3%.
- Sale total 499,999 returns 1%.
- `CompleteSaleHandler` stores calculated commission in sale.

### Candidate files

- `Src/Sales/Infrastructure/Commission/DatabaseCommissionService.php`
- `Tests/Unit/Sales/Adapters/MockCommissionServiceTest.php`
- `Tests/Unit/Sales/Handlers/CompleteSaleHandlerTest.php`

---

## GAP-003 — Confirmed cancellation policy decision

### Task expectation

`Task.txt` deep dive says order can be cancelled from `PENDING` or `CONFIRMED`. If `CONFIRMED`, refund payment via payment gateway.

### Current implementation evidence

- `Src/Sales/Domain/Entities/Sale.php` allows `PENDING` and `CONFIRMED` in `isCancellable()`.
- `Src/Sales/Application/Commands/Cancel/CancelSaleHandler.php` refunds if sale status is `CONFIRMED`.

### Gap / decision needed

This currently matches `Task.txt`, but it conflicts with the newer business reasoning:

> Confirmed already means payment processed, so cancellation should not be allowed.

There are two possible policies:

1. Keep `Task.txt` behavior: confirmed cancellation allowed with refund.
2. Use stricter business policy: only pending can cancel; confirmed needs a separate refund/reversal flow.

### Suggested implementation if stricter policy is selected

- Change `Sale::isCancellable()` to allow only `PENDING`.
- Remove/refactor refund logic from `CancelSaleHandler`.
- Create separate `RefundSaleCommand` or `ReversePaymentCommand` if refunds are needed.
- Update tests that currently expect confirmed cancellation.

### Acceptance checks if stricter policy is selected

- Pending sale can be cancelled.
- Confirmed sale cannot be cancelled.
- Completed sale cannot be cancelled.
- Confirmed refund flow is handled by a separate command, not cancel.

### Candidate files

- `Src/Sales/Domain/Entities/Sale.php`
- `Src/Sales/Application/Commands/Cancel/CancelSaleHandler.php`
- `Tests/Unit/Sales/SaleTest.php`
- `Tests/Unit/Sales/Handlers/CancelSaleHandlerTest.php`

---

## GAP-004 — Customer notification on cancellation

### Task expectation

`Task.txt` says customer should be notified after cancellation.

### Current implementation evidence

- `App/Providers/EventServiceProvider.php` registers cancellation projection and audit log for `SaleCancelledEvent`.
- There is no cancellation notification handler equivalent to `SendConfirmationEmailHandler`.

### Gap

`SaleCancelledEvent` does not trigger a customer notification handler.

### Suggested implementation

Create a handler such as:

- `Src/Sales/Application/EventHandlers/SendCancellationEmailHandler.php`

Register it under `SaleCancelledEvent` in `App/Providers/EventServiceProvider.php`.

### Acceptance checks

- `SaleCancelledEvent` triggers cancellation email handler.
- Handler receives sale id, cancellation reason, and cancelled timestamp.
- Test verifies handler registration or handler execution.

### Candidate files

- `Src/Sales/Application/EventHandlers/SendCancellationEmailHandler.php`
- `App/Providers/EventServiceProvider.php`
- `Tests/Feature/Api/Sales/SalesDomainEventsTest.php`

---

## GAP-005 — Agent ownership for commission

### Task expectation

`Task.txt` says commission belongs to the agent who created the order.

### Current implementation evidence

- `ProjectSaleReportsOnSaleCompletedHandler.php` updates commission summary with `agentId: null`.
- `Sale` aggregate does not currently carry an agent id.

### Gap

There is no clear agent identity attached to a sale, so commission cannot be attributed to the creator/agent.

### Suggested implementation

Add agent attribution to sale creation flow:

- Add `AgentId` or `UserId` value object.
- Add `agentId` to `CreateSaleCommand`.
- Store `agentId` on `Sale` and persistence model.
- Include `agentId` in `SaleCreatedEvent` or `SaleCompletedEvent`.
- Use `agentId` in commission projection.

### Acceptance checks

- Sale creation stores agent id.
- Completion event contains or can resolve agent id.
- Commission summary increments for the correct agent.

### Candidate files

- `Src/Sales/Application/Commands/Create/CreateSaleCommand.php`
- `Src/Sales/Domain/Entities/Sale.php`
- `Src/Sales/Infrastructure/Persistence/SaleModel.php`
- `Src/Sales/Infrastructure/Persistence/SaleRepository.php`
- `Src/Sales/Application/EventHandlers/ProjectSaleReportsOnSaleCompletedHandler.php`
- `Database/migrations/*sales*`

---

## GAP-006 — Monthly commission withdrawal flow

### Task expectation

`Task.txt` says commission can be withdrawn monthly.

### Current implementation evidence

- Current commission implementation tracks calculated commission/projections.
- There is no command/use case for withdrawal.

### Gap

No monthly withdrawal lifecycle exists.

### Suggested implementation

Create a separate commission payout/withdrawal use case:

- `RequestCommissionWithdrawalCommand`
- `ApproveCommissionWithdrawalCommand`
- `CommissionWithdrawal` aggregate or read model depending scope
- Monthly period validation

### Acceptance checks

- Agent can request withdrawal only for eligible monthly period.
- Already withdrawn commission cannot be withdrawn again.
- Withdrawal status is auditable.

### Candidate files

- New `Src/Sales/Application/Commands/Commission/*`
- New `Src/Sales/Domain/Entities/CommissionWithdrawal.php`
- New migrations for commission withdrawals
- New tests under `Tests/Unit/Sales/Handlers`

---

## GAP-007 — Agent dashboard/read model naming alignment

### Task expectation

`Task.txt` mentions `CommissionReportRM` and agent dashboard text.

### Current implementation evidence

- Project has `CommissionSummaryRM`.
- Project updates commission summary/report projections, but not a full agent dashboard.

### Gap

Naming and scope do not fully match `Task.txt`. This may be acceptable if `CommissionSummaryRM` is the intended equivalent.

### Suggested implementation

Either:

1. Keep `CommissionSummaryRM` and document it as equivalent to `CommissionReportRM`, or
2. Rename/add `CommissionReportRM` if strict requirement matching is needed.

### Acceptance checks

- Query can show monthly commission total per agent.
- Naming decision documented.
- API endpoint/report returns expected dashboard data.

### Candidate files

- `Src/Sales/Domain/ReadModels/CommissionSummaryRM.php`
- `Src/Sales/Application/Queries/Reports/GetCommissionSummaryHandler.php`
- `Apps/Api/Sales/Reports/CommissionSummaryAction.php`

---

## GAP-008 — Stock check and external price lock

### Task expectation

`Task.txt` mentions stock check and price lock during confirmation.

### Current implementation evidence

- `ConfirmSaleHandler` processes payment and confirms sale.
- `LineItem` stores unit price at creation, so price is locked inside sale data.
- No inventory/stock service exists in current Sales domain.

### Gap

External inventory stock check is not implemented. Price lock is implemented only implicitly by storing unit price in the aggregate.

### Suggested implementation

Defer unless inventory domain is required. If needed:

- Add `InventoryAvailabilityCheckerInterface`.
- Check stock before payment processing.
- Throw `InsufficientStockException` if unavailable.

### Acceptance checks

- Confirmation fails before payment if stock unavailable.
- Confirmation proceeds if all items are available.
- Unit price remains unchanged after product price changes.

### Candidate files

- `Src/Sales/Application/Commands/Confirm/ConfirmSaleHandler.php`
- New `Src/Sales/Domain/Ports/InventoryAvailabilityCheckerInterface.php`
- New `Src/Sales/Domain/Exceptions/InsufficientStockException.php`

---

## Recommended execution order

1. Decide `GAP-003` first because cancellation policy affects tests and handler behavior.
2. Implement `GAP-002` because commission tier mismatch is clear and isolated.
3. Implement `GAP-001` if customer existence is required for strict task compliance.
4. Implement `GAP-004` as simple event handler side effect.
5. Implement `GAP-005` only if agent feature is in scope.
6. Keep `GAP-006`, `GAP-007`, and `GAP-008` as later enhancements unless task requires strict full completion.
