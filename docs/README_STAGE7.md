# KKK OS V1 — Stage 7 Packing Foundation

## Scope
Adds packing workflow after printing.

## Tables
- packing_jobs
- packing_job_items
- packing_job_events

## Core flow
PRINTED
→ READY_FOR_PACKING
→ PACKING
→ PACKED

## Core rules
- Packing is order-level: exactly one packing job per business order.
- 1-package => one packing job + one side item.
- 2-package => one packing job + two side items (LELAKI + PEREMPUAN).
- All print jobs must already be PRINTED.
- Every packing item must be verified present before PACKED.
- Initialization is idempotent.
- Operational records are never permanently deleted here.
- Important packing actions are recorded in packing_job_events.

## Add relationships

App\Models\Order:

public function packingJob()
{
    return $this->hasOne(\App\Models\PackingJob::class);
}

App\Models\PrintJob:

public function packingItem()
{
    return $this->hasOne(\App\Models\PackingJobItem::class);
}

App\Models\OrderPackageSide:

public function packingItem()
{
    return $this->hasOne(\App\Models\PackingJobItem::class);
}

## Install
1. Merge app/, database/, tests/, docs/.
2. Add relationships above.
3. Add local dev route.
4. Add local-only CSRF exception.
5. Run:
   php artisan migrate
   php artisan test

## Manual local test
Use an order that is already PRINTED:

$response = Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/dev/orders/ORDER-ID/packing-job" `
    -Method Post `
    -Headers @{ Accept = "application/json" }

$response | ConvertTo-Json -Depth 5

Expected:
1-package => item_count = 1
2-package => item_count = 2

Running the endpoint again must keep one packing_job only.

## Not included yet
- packaging material policy
- parcel weight/dimensions
- courier consignment generation
- pickup collection verification
- completed order transition

Those belong to the next fulfilment stage and/or require operational verification.
