# KKK OS V1 — Stage 8 Courier / Pickup Fulfilment Foundation

## Blueprint alignment

Master Blueprint flow:

Packing
→ Courier / Pickup
→ Completed

This stage keeps the business order status at PACKED while fulfilment is in progress,
then moves the business order directly to COMPLETED only when fulfilment is finished.

Internal fulfilment-job statuses are operational implementation details and do not add
new customer/business lifecycle stages.

For COURIER orders, COMPLETED means KKK operational responsibility is complete after
the packed parcel has been handed to the courier and the required proof and tracking
information have been recorded. It does not mean the courier has delivered the parcel
to the customer.

## Tables

- fulfilment_jobs
- fulfilment_job_events

## Core rules

- Only PACKED orders can initialize fulfilment.
- Exactly one fulfilment job per business order.
- Method is copied from approved order fulfilment data: COURIER or PICKUP.
- Courier requires recipient name, phone, and shipping address already stored.
- PICKUP: READY -> COLLECTED -> business order COMPLETED.
- COURIER: READY -> COMPLETED -> business order COMPLETED.
- Courier completion requires the packing job to already be PACKED.
- Courier completion requires a packing proof image.
- The proof image is taken after the parcel is packed and must show the packed parcel
  together with the attached shipping paper/label containing the tracking number.
- Courier provider and tracking number are required before courier completion.
- PACKING staff must explicitly confirm COMPLETE.
- Only the PACKING staff assigned to the packing job may perform operational courier
  completion.
- ADMIN may monitor and assign/reassign packing jobs but does not perform operational
  courier completion.
- `shipped_at` records the physical handoff time to the courier for the current V1
  courier completion contract.
- Courier completion does not require confirmation that the parcel was delivered to
  the customer.
- Initializing twice does not create duplicate fulfilment jobs.
- Completing an already completed courier fulfilment is idempotent and must not create
  duplicate completion events.
- No courier provider is hard-coded.
- Operational records are not permanently deleted.
- For a 2-package business order, both package sides remain under one packing job and
  one courier fulfilment. V1 records one shipment proof and one tracking number for
  that business order unless a future approved business rule introduces split shipment.

## Customer tracking contract

For a completed COURIER order:

- KKK OS stores the courier provider and tracking number.
- The tracking number is exposed through the Customer Dashboard backend contract.
- The customer obtains the tracking number from the KKK website / Customer Dashboard.
- The customer uses that tracking number on the courier's external parcel tracking
  website, such as Pos Laju.
- KKK OS V1 does not provide live courier tracking or courier tracking API integration.

## Relationships

App\Models\Order:

```php
public function fulfilmentJob()
{
    return $this->hasOne(\App\Models\FulfilmentJob::class);
}
```

App\Models\OrderFulfilment:

```php
public function fulfilmentJob()
{
    return $this->hasOne(\App\Models\FulfilmentJob::class);
}
```

App\Models\PackingJob:

```php
public function fulfilmentJob()
{
    return $this->hasOne(\App\Models\FulfilmentJob::class);
}
```

## Install

1. Merge app/, database/, tests/, docs/.
2. Ensure the relationships above exist.
3. Add the required local development routes.
4. Add local-only CSRF exceptions where required.
5. Run:

```powershell
php artisan migrate
php artisan test
```

## Expected tests

- Unpacked order is rejected.
- 1-package PICKUP works.
- Pickup collection completes the business order.
- 1-package COURIER completes through the packing workflow.
- 2-package COURIER remains one business fulfilment for one business order.
- Initialization is idempotent.
- Courier cannot complete without packing proof.
- Courier cannot complete without courier provider.
- Courier cannot complete without tracking number.
- Courier cannot complete without explicit COMPLETE confirmation.
- Courier completion changes fulfilment directly from READY to COMPLETED.
- Courier completion changes the business order to COMPLETED.
- Courier completion records `shipped_at` as the physical courier handoff timestamp.
- Courier completion does not require `delivered_at`.
- Unauthorized PACKING staff cannot complete another staff member's assigned job.
- ADMIN cannot perform operational courier completion.
- Repeating an already completed courier operation does not create duplicate
  `COURIER_COMPLETED` events.

## Known limitations

- KKK OS V1 validates that the required proof is an accepted image upload and applies
  the configured upload validation rules.
- KKK OS V1 does not automatically inspect the visual contents of the proof image to
  verify that the parcel and attached shipping label/tracking number are actually
  visible.
- PACKING staff is operationally responsible for ensuring that the uploaded proof
  shows the packed parcel together with the attached shipping paper/label containing
  the tracking number.
- There is no real courier API integration.
- There is no automatic airway bill creation.
- There is no courier tracking webhook.
- There is no live courier tracking inside KKK OS V1.
- Courier pricing/SLA policy is not automated.
- Mandatory pickup identity process is not included.

These items require provider, operational, or future scope decisions and must not be
invented as part of V1.

## Manual local test

Use a real local order already at PACKED.

### Initialize fulfilment

```powershell
$response = Invoke-RestMethod `
    -Uri "http://localhost:8000/dev/orders/ORDER-ID/fulfilment-job" `
    -Method Post `
    -Headers @{ Accept = "application/json" }

$response | ConvertTo-Json -Depth 5
```

### PICKUP

The local development collect route remains available for testing PICKUP:

```powershell
Invoke-RestMethod `
    -Uri "http://localhost:8000/dev/fulfilment-jobs/FULFILMENT-JOB-ID/collect" `
    -Method Post `
    -ContentType "application/json" `
    -Body (@{
        completion_reference = "TEST-PICKUP"
    } | ConvertTo-Json)
```

Final business order status must be COMPLETED.

### COURIER

The legacy local development courier endpoints:

```text
/dev/fulfilment-jobs/{id}/ship
/dev/fulfilment-jobs/{id}/deliver
```

have been retired.

Courier completion must be tested through the production packing workflow contract so
that packing proof, courier provider, tracking number, explicit COMPLETE confirmation,
staff role, and packing-job assignment rules are enforced.

Expected operational flow:

```text
Packing job PACKED
    ↓
Fulfilment READY
    ↓
Courier arrives / parcel is handed over
    ↓
PACKING staff captures proof image
    ↓
Courier provider + tracking number recorded
    ↓
PACKING staff explicitly confirms COMPLETE
    ↓
Fulfilment COMPLETED
    ↓
Business order COMPLETED
```

The customer then obtains the tracking number from the Customer Dashboard and uses it
on the external courier tracking website.