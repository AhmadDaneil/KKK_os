# KKK OS V1 — Stage 3A: Canonical Merge Jobs

## Purpose
Generate internal KKK Engine merge jobs from confirmed database data.

This stage intentionally DOES NOT define the final Google Sheet/CSV Photoshop contract.
The exact existing Auto Merge headers, encoding, line breaks, date/time display format and
file format must still be inspected directly from the real working Photoshop integration.

## Business behavior
- DETAILS_INCOMPLETE cannot generate merge jobs.
- DETAILS_CONFIRMED can generate merge jobs.
- 1 package => exactly 1 merge job.
- 2 packages => exactly 2 merge jobs.
- Job IDs use ORDERID-L and ORDERID-P.
- Re-running generation is idempotent: no duplicate rows.
- Each job stores an internal canonical JSON snapshot.
- Database remains source of truth.
- No permanent deletion behavior is introduced.

## Installation
1. Merge app/, database/, tests/, docs/ into the Laravel project.
2. Add the relationship snippets described below.
3. Add the local-only development route from docs/STAGE3A_ROUTES_SNIPPET.txt.
4. Run:
   php artisan migrate
   php artisan test

## Relationship snippets

Add to App\Models\Order:

public function mergeJobs()
{
    return $this->hasMany(\App\Models\MergeJob::class);
}

Add to App\Models\OrderPackageSide:

public function mergeJob()
{
    return $this->hasOne(\App\Models\MergeJob::class);
}

## Manual generation
POST /dev/orders/{ORDER_ID}/merge-jobs

Example:
Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/dev/orders/KKK-260909-0007/merge-jobs" `
    -Method Post `
    -Headers @{ Accept = "application/json" }

Expected for 2-package:
job_count = 2
ORDERID-L
ORDERID-P
