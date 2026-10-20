# Stage 10G — Billplz Sandbox Payments

## Scope and decision

Billplz Sandbox supports the initial booking deposit and the optional outstanding-balance checkout after artwork approval. The existing manual QR balance flow remains available when no Billplz transaction is pending.

The integration is locked to `https://www.billplz-sandbox.com/api`; it rejects a non-Sandbox API host and non-HTTPS application URL. `BILLPLZ_ENABLED` defaults to `false`, so enabling the feature is an explicit staging configuration step. Do not put credentials in Git or this document.

## Staging configuration

Set these variables in the staging environment only, using the Sandbox credentials supplied by the Project Owner:

```dotenv
BILLPLZ_ENABLED=true
BILLPLZ_BASE_URL=https://www.billplz-sandbox.com/api
BILLPLZ_SECRET_KEY=<Sandbox Secret Key>
BILLPLZ_X_SIGNATURE_KEY=<Sandbox X Signature Key>
BILLPLZ_COLLECTION_ID=<Sandbox Collection ID>
```

Keep production disabled and use only credentials and a collection created in Billplz Sandbox. After changing environment configuration, rebuild Laravel's cached config using the normal staging release procedure. Confirm the configured `APP_URL` is HTTPS because Billplz callbacks and redirects use it.

## Flow

1. A new customer submits the package and contact form; the application creates a booking-deposit bill and redirects to Billplz.
2. A failed bill request displays a payment-specific message. An identical retry in the same browser session reuses the same order instead of creating a duplicate order.
3. The service accepts a Billplz Sandbox bill URL only from the configured Billplz host. A legacy `http` bill URL from that trusted host is upgraded to `https`; foreign hosts remain rejected.
4. After artwork approval, the service reads the outstanding balance from the immutable order pricing snapshot and sends the amount in sen to Billplz.
5. A pending Billplz transaction stores the bill reference and checkout URL; repeated checkout requests reuse that URL.
6. The webhook verifies X Signature, collection, state, and amount before updating the payment ledger and synchronizing order financials.
7. A pending manual QR balance transaction prevents creation of a competing Billplz charge.

## Scheduled reconciliation

When `BILLPLZ_ENABLED=true`, Laravel schedules `payments:reconcile-pending-billplz` every minute. It checks up to 25 pending Billplz booking-deposit and balance transactions with a saved Billplz bill ID, retrieves each bill through the authenticated Billplz API, and sends the verified result through the same payment callback handler used by signed webhooks. It never treats customer redirect parameters as payment confirmation.

The latest check time is stored with the Billplz payment metadata to avoid generating an event for every unchanged, unpaid bill. Provider status changes are passed through the normal payment handler and retained in the payment event history; API failures are logged and retried on a later cycle. Scheduler overlap and multi-server execution are guarded with Laravel's cache locks. The VPS must already run Laravel's scheduler every minute (`php artisan schedule:run`) for this fallback to operate. When Billplz is disabled, the scheduled command does not run.

## Verification and limits

Automated coverage: `tests/Feature/Payments/BookingPaymentJourneyTest.php`, `tests/Feature/Orders/PublicOrderJourneyTest.php`, and `tests/Feature/BillplzBalancePaymentTest.php` cover one-package and two-package amounts, trusted checkout URLs, retry deduplication, provider failure, manual QR conflict, and signed callback idempotency. The adjacent manual payment suites must also remain green.

These tests use HTTP fakes and do not contact Billplz. Live Sandbox checkout, redirect, and callback delivery still need to be exercised on staging after credentials are configured. No production credentials or production endpoint are supported by this implementation.
