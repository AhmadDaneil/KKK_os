# Stage 3C — Option A Final Wiring

Project Owner decision:
ONE card quantity per BUSINESS ORDER.

Copy the included app/database/tests/docs files into the Laravel project.

Important:
This package intentionally does NOT overwrite:
- Order.php
- CustomerOrderDraftController.php
- SaveOrderDraftService.php
- ValidateOrderCompletionService.php
- BuildFinalReviewService.php
- dashboard.blade.php
- current confirmation-snapshot implementation

Apply the small edits documented in:
docs/STAGE3C_OPTION_A_IMPLEMENTATION.md

This prevents Stage 3C from reverting fixes already made during Stages 2A–2D.

Then run:

php artisan migrate
php artisan optimize:clear
php artisan test --filter=CardQuantityOptionATest
php artisan test --filter=PhotoshopProductionExportWiringTest
php artisan test
