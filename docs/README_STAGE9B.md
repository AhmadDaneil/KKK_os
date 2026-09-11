# KKK OS V1 — Stage 9B Real Photoshop Acceptance Harness

Copy these files into the Laravel project:

- app/Console/Commands/ExportPhotoshopAcceptanceOrder.php
- tools/Verify-PhotoshopAcceptance.ps1
- docs/STAGE9B_REAL_PHOTOSHOP_ACCEPTANCE_TEST.md
- docs/STAGE9B_ACCEPTANCE_RESULT_TEMPLATE.md

Laravel 13 normally auto-discovers commands in app/Console/Commands. If your project has customized command registration, register this command using the project's existing Laravel 13 command-registration pattern.

First confirm the command:

php artisan list | findstr photoshop

Then export a CONFIRMED order whose theme/design_code points to a REAL Photoshop template:

php artisan photoshop:acceptance-export KKK-YYMMDD-NNNN

Do this once for 1-package and once for 2-package.

Do NOT mark Stage 9B DONE from Laravel tests alone.
Stage 9B requires actual Photoshop execution and visual verification.
