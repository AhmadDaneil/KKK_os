# Stage 10G — Billplz Sandbox (Balance Payments)

## Scope and decision

Billplz is enabled only as an optional checkout for the outstanding balance after artwork approval. The booking deposit remains on the existing manual QR and receipt-review flow. The existing manual QR balance flow remains available when no Billplz transaction is pending.

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

1. Customer reaches the order progress page after artwork approval.
2. The service reads the outstanding balance from the immutable order pricing snapshot and sends the amount in sen to Billplz.
3. A pending Billplz transaction stores the bill reference and checkout URL; repeated checkout requests reuse that URL.
4. The webhook verifies X Signature, collection, state, and amount before updating the payment ledger and synchronizing order financials.
5. A pending manual QR balance transaction prevents creation of a competing Billplz charge.

## Verification and limits

Automated coverage: `tests/Feature/BillplzBalancePaymentTest.php` (one-package and two-package amounts, idempotent checkout, manual QR conflict, provider failure, and signed paid callback). The adjacent manual payment suites must also remain green.

These tests use HTTP fakes and do not contact Billplz. Live Sandbox checkout, redirect, and callback delivery still need to be exercised on staging after credentials are configured. No production credentials or production endpoint are supported by this implementation.
