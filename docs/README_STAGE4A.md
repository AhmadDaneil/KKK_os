# KKK OS V1 — Stage 4A: Designer & Artwork Workflow Database Foundation

## Tables
- design_jobs
- artwork_versions
- design_job_events

## Core rules
- One merge job -> exactly one design job.
- 1-package -> one design job.
- 2-package -> two independent design jobs.
- Artwork versions increment; older versions are never overwritten.
- Artwork binary files are not stored in MySQL; database stores file references and metadata.
- Designer actions are auditable through design_job_events.
- This stage does not implement customer correction/approval, final Photoshop export contract, balance payment, or production.

## Add relationships

App\Models\Order:

public function designJobs()
{
    return $this->hasMany(\App\Models\DesignJob::class);
}

App\Models\OrderPackageSide:

public function designJob()
{
    return $this->hasOne(\App\Models\DesignJob::class);
}

App\Models\MergeJob:

public function designJob()
{
    return $this->hasOne(\App\Models\DesignJob::class);
}

## Install
1. Merge app/, database/, tests/, docs/.
2. Add the three relationships above.
3. Add the local-only dev route.
4. Add local-only CSRF exception:
   dev/orders/*/design-jobs
5. Run:
   php artisan migrate
   php artisan test

## Manual local test
For confirmed order KKK-260909-0007 whose merge jobs already exist:

$response = Invoke-RestMethod `
    -Uri "http://127.0.0.1:8000/dev/orders/KKK-260909-0007/design-jobs" `
    -Method Post `
    -Headers @{ Accept = "application/json" }

$response | ConvertTo-Json -Depth 5

Expected:
design_job_count = 2
LELAKI    READY_FOR_DESIGN
PEREMPUAN READY_FOR_DESIGN

Run it again. There must still be exactly 2 design_jobs.
