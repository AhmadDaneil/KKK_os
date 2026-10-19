# Paid Order to Design Transition

## Rule

An order advances from `DETAILS_CONFIRMED` to `READY_FOR_DESIGN` as soon as both conditions are true:

1. `booking_payment_status` is `PAID`.
2. Customer details have been confirmed.

The conditions may be completed in either order. Downloading or printing an invoice is read-only and never changes progress.

## Automated work

The transition generates one merge job and one design job per package side, assigns the active design lead, changes the order status, and records an `order_status_events` audit entry. Repeated callbacks and repeated reconciliation are idempotent because only `DETAILS_CONFIRMED` orders are eligible.

## Existing orders

Use the following command after deployment to reconcile orders that were paid before this rule was deployed:

```shell
php artisan orders:advance-paid-confirmed
```

To repair one known order only:

```shell
php artisan orders:advance-paid-confirmed --order=KKK-261019-0002
```

The command does not alter unpaid, incomplete, cancelled, archived, or already-advanced orders. An active Designer account is required for automatic assignment.

## Verification

Regression coverage includes Billplz callback after details confirmation, details confirmation after Billplz payment, manual payment review, duplicate callback safety, and both one-package and two-package job counts.
