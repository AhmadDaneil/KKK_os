# KKK OS V1 — Stage 6 Printing Foundation

## Scope
Adds the printing workflow foundation after balance payment.

## Tables
- print_jobs
- print_job_events

## Core flow
PAID
→ initialize print job(s)
→ READY_FOR_PRINT
→ PRINTING
→ PRINTED

## Core rules
- Only PAID orders may initialize print jobs.
- Each DESIGN_APPROVED design job becomes exactly one print job.
- 1-package => exactly 1 print job.
- 2-package => exactly 2 independent print jobs.
- Initialization is idempotent.
- Lelaki and Perempuan do not overwrite one another.
- Print jobs reference the approved/latest artwork version used for production.
- Operational records are not permanently deleted.
- Important actions are recorded in print_job_events.

## Add relationships

App\Models\Order:

public function printJobs()
{
    return $this->hasMany(\App\Models\PrintJob::class);
}

App\Models\DesignJob:

public function printJob()
{
    return $this->hasOne(\App\Models\PrintJob::class);
}

App\Models\ArtworkVersion:

public function printJobs()
{
    return $this->hasMany(\App\Models\PrintJob::class);
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
Use a real local order that has already reached PAID:

$response = Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/dev/orders/ORDER-ID/print-jobs" `
    -Method Post `
    -Headers @{ Accept = "application/json" }

$response | ConvertTo-Json -Depth 5

Expected:
1-package => print_job_count = 1
2-package => print_job_count = 2

Running the endpoint again must not create duplicates.

## Not included yet
- physical printer integration
- print quantity business policy
- reprint policy
- packing
- courier/pickup completion

Those should not be invented without operational verification.
