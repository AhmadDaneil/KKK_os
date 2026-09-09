# ADR-001 - Order Core Foundation

Date: 2026-09-09
Status: Accepted technical implementation for Stage 1

## Decision
- Database is source of truth.
- One `orders` row represents one business order regardless of 1-package or 2-package selection.
- Public Order ID format is `KKK-YYMMDD-NNNN` for Stage 1.
- A daily sequence table is used to make sequential Order ID generation concurrency-safe.
- Dashboard authorization uses an opaque random token; only its SHA-256 hash is persisted.
- Test order creation and future payment-success order creation must share `CreateOrderService`.

## Business rules preserved
- 1 package => one business order.
- 2 packages => one business order; two Photoshop merge jobs are created later by the KKK Engine.
- No permanent operational order deletion.

## Not included in Stage 1
- Payment gateway integration.
- Customer detail domain tables.
- Photoshop export.
- Designer workflow.
