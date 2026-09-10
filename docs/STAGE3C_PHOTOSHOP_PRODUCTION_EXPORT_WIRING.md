# KKK OS V1 — Stage 3C Photoshop Production Export Wiring

Status: PARTIALLY IMPLEMENTED — BUSINESS DATA-SOURCE DECISION REQUIRED

## What is completed safely

Stage 3C now provides:

- a formal `CardQuantityProviderContract`;
- a fail-closed unresolved provider;
- production-order export orchestration;
- exact merge-job count validation;
- exact package-side ↔ merge-job validation;
- 1-package → one Photoshop CSV data row;
- 2-package → two independent Photoshop CSV data rows;
- use of the verified Stage 3B 28-column CSV exporter;
- regression tests for the production wiring.

## Why qtykad is not added to the database yet

The Master Blueprint confirms:
- one package vs two package behavior;
- one business order can create two independent Photoshop rows;
- exact Photoshop headers must follow the real working automation;
- the database is the application source of truth.

However, the Master Blueprint does not state whether card quantity is:

A. one quantity for the whole business order, or
B. independently selectable per package side.

This distinction materially affects:
- database ownership of the field;
- Customer Dashboard UX;
- validation;
- final-review snapshot;
- 2-package Photoshop output;
- payment/price logic if quantity affects pricing.

Therefore KKK Systems Team must not invent the answer.

## Working automation evidence

The current JSX reads `qtykad` per CSV row and uses it as the default output item
quantity, except for fixed accessory template names such as banner/sticker/arrows/hanger.

Existing CSV examples contain values such as:
- 30 PCS
- 50 PCS
- 100 PCS
- 150 PCS
- 200 PCS
- 300 PCS
- 400 PCS
- 500 PCS

This proves the Photoshop consumer needs the value, but does not prove whether KKK OS
should store it on `orders` or `order_package_sides`.

## Required Project Owner decision

Lock exactly one rule:

### Option A — quantity per business order
Example:
`orders.card_quantity`

Both LELAKI and PEREMPUAN rows inherit the same quantity.

### Option B — quantity per package side
Example:
`order_package_sides.card_quantity`

LELAKI and PEREMPUAN may have different quantities.

Do not implement either schema until this rule is confirmed.

## Safe temporary behavior

`PhotoshopExportServiceProvider` binds the quantity contract to
`UnresolvedCardQuantityProvider`.

Therefore production export fails loudly rather than generating an incorrect CSV.

Once the business rule is approved:
1. add the correct migration;
2. capture quantity in Customer Dashboard;
3. save/normalize/validate it;
4. include it in Final Review and confirmed snapshot;
5. implement the DB-backed quantity provider;
6. replace the safety binding;
7. run both 1-package and 2-package tests;
8. compare generated CSV with the working JSX.

## Provider registration

If this application uses Laravel's provider discovery via `bootstrap/providers.php`,
add:

```php
App\Providers\PhotoshopExportServiceProvider::class,
```

Do this only after reviewing the project's existing provider registration format.

## Tests

Run:

```powershell
php artisan test --filter=PhotoshopProductionExportWiringTest
php artisan test
```

Expected Stage 3C tests:
- unresolved qty source blocks production export;
- one package exports one row with a supplied approved quantity;
- two package exports two independent rows and can represent different quantities;
- mismatched merge-job count is rejected.

These tests intentionally prove both quantity ownership possibilities without choosing
one as the business rule.
