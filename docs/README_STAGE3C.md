# Stage 3C — Photoshop Production Export Wiring

Merge:
- app/Contracts/Photoshop/
- app/Services/Photoshop/
- app/Providers/PhotoshopExportServiceProvider.php
- tests/Feature/Merge/PhotoshopProductionExportWiringTest.php
- docs/STAGE3C_PHOTOSHOP_PRODUCTION_EXPORT_WIRING.md

No migration is included intentionally.

The only unresolved item is the approved database ownership of `qtykad`.

Before testing, register `PhotoshopExportServiceProvider` in the same way other providers
are registered in this Laravel 13 project (typically `bootstrap/providers.php`).

Then run:

php artisan test --filter=PhotoshopProductionExportWiringTest
php artisan test
