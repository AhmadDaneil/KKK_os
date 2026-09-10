# Stage 9A — End-to-End Order → Photoshop Integration Hardening

Copy:

tests/Feature/Integration/OrderToPhotoshopEndToEndTest.php
docs/STAGE9A_END_TO_END_ORDER_TO_PHOTOSHOP.md

No database migration and no new V1 business rule is introduced.

Run:

php artisan test --filter=OrderToPhotoshopEndToEndTest
php artisan test

If the targeted test passes, the next step is a real Windows/Photoshop acceptance test:
generated CSV + current JSX + real MASTER template structure.
