# KKK OS V1 — Stage 9A End-to-End Integration Hardening

## Objective

Prove the real application path from confirmed customer order data to Photoshop-ready CSV,
without adding new V1 business features.

Coverage:

1. Create one business order.
2. Initialize correct 1-package or 2-package structure.
3. Save normalized customer/order data.
4. Save approved order-level `card_quantity`.
5. Final confirmation.
6. Generate canonical merge jobs.
7. Export exact 28-column Photoshop CSV.
8. Verify 1-package gives exactly one data row.
9. Verify 2-package gives exactly two independent data rows.
10. Verify Option A quantity ownership: both rows inherit the same `orders.card_quantity`.

## Pass criteria

### 1-package

- one business order;
- one package side;
- one merge job;
- one CSV data row;
- correct order ID;
- correct qtykad;
- correct theme/designcode;
- correct `majlis`.

### 2-package

- one business order;
- LELAKI + PEREMPUAN package sides;
- two merge jobs;
- two CSV data rows;
- same `qtykad` on both rows;
- independent design codes;
- independent addresses;
- no row overwrite.

## Commands

```powershell
php artisan test --filter=OrderToPhotoshopEndToEndTest
php artisan test
```

## Important

This stage tests the Laravel/database/CSV contract end-to-end.

It does NOT yet automate Adobe Photoshop itself. The current JSX still runs in the designer
Windows/Photoshop environment and has its own external runtime dependencies, including QR
generation behavior already documented in Stage 3B.

Actual Photoshop execution should be handled as a separate production-environment acceptance
test, using generated CSV plus the real MASTER template folder and real JSX.
