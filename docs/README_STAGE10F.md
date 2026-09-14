# KKK OS V1 — Stage 10F / T12 Terminal Order Lifecycle

Decision locked by Project Owner: **Option A**.

- `CANCELLED` and `ARCHIVED` are terminal operational states in V1.
- Data remains stored, queryable and auditable.
- No Reactivate/Restore workflow is added in V1.
- No permanent delete is introduced.

## What this patch adds

1. `order_status_events` audit table.
2. `OrderStatusEvent` model.
3. `OrderLifecycleService` with `cancel()` and `archive()`.
4. `Order` terminal-state defense-in-depth guard.
5. Design and print status syncs return without overwriting terminal orders.
6. Balance payment callbacks may still record/update the payment transaction, but do not move a terminal order back to `PAID`.
7. Final packing/completion paths reject workflow continuation on terminal orders.
8. T12 tests for 1-package and 2-package paths, no deletion, audit trail, idempotency and terminal-state protection.

## Important scope note

This patch does **not** add an admin UI or customer UI for cancel/archive. That would be a separate approved operational interface task. The lifecycle service is the V1 business-rule foundation.

## Apply

From `C:\KKK-DEV\kkk-os`, copy the patch files over the matching project paths.

Then run:

```powershell
php artisan migrate
php artisan test --filter=OrderLifecycleTerminalStateTest
```

If targeted tests pass, run the payment/design/printing/packing/fulfilment regressions relevant to the modified services, then the full suite:

```powershell
php artisan test
```

## Expected new targeted result

`OrderLifecycleTerminalStateTest`: 6 tests should pass.

## Acceptance before Stage 10F is LOCKED

- Migration succeeds.
- New T12 tests pass.
- Existing payment callback tests pass.
- Existing design status tests pass.
- Existing print/packing/fulfilment tests pass.
- Full suite passes.
- Existing 1-package and 2-package E2E regressions still pass.

## Known implementation detail

`reason` is nullable in V1. Making cancellation/archive reasons mandatory would be an operational/customer-experience rule and was not introduced without separate approval.
