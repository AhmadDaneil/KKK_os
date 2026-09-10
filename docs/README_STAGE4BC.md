# KKK OS V1 — Stage 4B/4C Artwork Review, Correction, Approval

## Adds
- artwork_review_actions
- MarkDesignReadyService
- RequestArtworkCorrectionService
- ResumeDesignAfterCorrectionService
- ApproveArtworkService
- SyncOrderDesignStatusService
- CustomerArtworkReviewController
- Customer artwork review Blade view
- Feature tests

## Required relationships

Add to App\Models\DesignJob:

public function reviewActions()
{
    return $this->hasMany(\App\Models\ArtworkReviewAction::class);
}

Add to App\Models\ArtworkVersion:

public function reviewActions()
{
    return $this->hasMany(\App\Models\ArtworkReviewAction::class);
}

## State path
READY_FOR_DESIGN
→ DESIGN_IN_PROGRESS
→ DESIGN_READY
→ either:
   CORRECTION_REQUESTED
   → DESIGN_IN_PROGRESS
   → new artwork version
   → DESIGN_READY
OR
   DESIGN_APPROVED

For two-package orders, the order should only become DESIGN_APPROVED
when both independent design jobs are DESIGN_APPROVED.

## Install
1. Merge app/, database/, resources/, tests/, docs/.
2. Add relationships.
3. Add customer routes from docs/STAGE4BC_ROUTES_SNIPPET.txt.
4. Run:
   php artisan migrate
   php artisan test

## Not included yet
- actual file upload UI/storage handling
- staff designer dashboard
- balance payment
- printing/production
