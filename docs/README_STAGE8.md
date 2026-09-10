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

## Tables
- fulfilment_jobs
- fulfilment_job_events

## Core rules
- Only PACKED orders can initialize fulfilment.
- Exactly one fulfilment job per business order.
- Method is copied from approved order fulfilment data: COURIER or PICKUP.
- Courier requires recipient name, phone, and shipping address already stored.
- PICKUP: READY -> COLLECTED -> business order COMPLETED.
- COURIER: READY -> SHIPPED -> DELIVERED -> business order COMPLETED.
- Initializing twice does not create duplicate fulfilment jobs.
- No courier provider is hard-coded.
- Courier provider/tracking and completion references are optional foundation fields.
- Operational records are not permanently deleted.

## Relationships to add

App\Models\Order:

public function fulfilmentJob()
{
    return $this->hasOne(\App\Models\FulfilmentJob::class);
}

App\Models\OrderFulfilment:

public function fulfilmentJob()
{
    return $this->hasOne(\App\Models\FulfilmentJob::class);
}

App\Models\PackingJob:

public function fulfilmentJob()
{
    return $this->hasOne(\App\Models\FulfilmentJob::class);
}

## Install
1. Merge app/, database/, tests/, docs/.
2. Add the relationships above.
3. Add the local dev routes.
4. Add local-only CSRF exceptions.
5. Run:
   php artisan migrate
   php artisan test

## Expected tests
- Unpacked order is rejected.
- 1-package PICKUP works.
- 2-package COURIER works as one fulfilment job for one business order.
- Initialization is idempotent.
- Pickup collection completes order.
- Courier cannot deliver before shipping.
- Courier shipping does not complete order.
- Courier delivery completes order.

## Not included yet
- real courier API
- automatic airway bill creation
- webhook tracking
- courier pricing/SLA policy
- mandatory pickup identity process

Those require provider/operational verification and must not be invented.

## Manual local test
Use a real local order already at PACKED.

Initialize:
$response = Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/dev/orders/ORDER-ID/fulfilment-job" `
    -Method Post `
    -Headers @{ Accept = "application/json" }

$response | ConvertTo-Json -Depth 5

For PICKUP:
Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/dev/fulfilment-jobs/FULFILMENT-JOB-ID/collect" `
    -Method Post `
    -ContentType "application/json" `
    -Body (@{ completion_reference = "TEST-PICKUP" } | ConvertTo-Json)

For COURIER:
Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/dev/fulfilment-jobs/FULFILMENT-JOB-ID/ship" `
    -Method Post `
    -ContentType "application/json" `
    -Body (@{
        courier_provider = "TEST_COURIER"
        tracking_number = "TRACK-001"
    } | ConvertTo-Json)

Then:
Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/dev/fulfilment-jobs/FULFILMENT-JOB-ID/deliver" `
    -Method Post `
    -ContentType "application/json" `
    -Body (@{ completion_reference = "TEST-DELIVERED" } | ConvertTo-Json)

Final business order status must be COMPLETED.
