# Order Progress Visibility — V1 Implementation Note

## Scope

This change adds one canonical, per-order progress-update stream without
replacing the existing workflow artefacts:

- Designer artwork remains in the artwork/review workflow.
- Production progress evidence remains in `print_jobs.progress_files`; each
  uploaded file is private by default and can be explicitly shared with the
  customer from the Production upload form.
- OM packing and courier proof remains in the Packing/Fulfilment workflow.
- A new `order_progress_updates` stream carries progress messages and optional
  attachments intended for management monitoring and, only when explicitly
  published, the Customer Dashboard.
- The Customer Dashboard also provides proof buttons for customer-safe Design
  previews, Packing proof after packing is complete, the final Courier proof
  after courier handover, and the Pickup completion record after collection.
  Source artwork and other pre-completion operational material remain private.

This avoids asking the customer to re-enter data and avoids treating private
operational evidence as customer content.

## Authorization Contract

- ADMIN, OPERATION_MANAGEMENT, and CUSTOMER_SERVICE can read every progress
  update and its attachment. CUSTOMER_SERVICE remains read-only.
- DESIGNER can create an update only when at least one Design job on the order
  is assigned to that user.
- PRODUCTION can create an update only when a Print job on the order is
  assigned to that user.
- OPERATION_MANAGEMENT can create an update only when the order has a Packing
  or Fulfilment job, matching OM's approved operational ownership.
- A specialist uploader can read their own attachment. Other specialist staff
  receive a not-found response.
- CUSTOMER_SERVICE cannot create progress updates and does not receive access
  to Designer or Courier operational-proof endpoints.
- Customers must first pass the existing order session/token check. The
  requested update must belong to that exact order and must be explicitly
  customer-visible.

## Customer Publishing and Privacy

Publishing is default-private. The uploader must explicitly select
`customer_visible`; omission stores an INTERNAL update with no publication
timestamp. Customer queries also enforce an uploader-role allow-list of
DESIGNER, PRODUCTION, and OPERATION_MANAGEMENT.

Attachments use the private `local` disk and are streamed only through
authorized controllers. Storage paths are never exposed as public URLs.
Operational proofs are not copied into this stream. Production progress files
explicitly marked customer-visible are shown in a separate Production Proof
section and streamed only through a customer-session endpoint scoped to the
matching order and print job. Existing progress files without this explicit
flag remain private until an authorized Production assignee or OM explicitly
shares them from the order details page; this action records an audit event.
Following the Project
Owner instruction on 2026-10-15, the final Courier proof may be streamed to the
customer only after the matching order's Courier job is `COMPLETED`. The same
session/order check is enforced again by the file endpoint. Design proof links
open only the established customer-preview workflow; source artwork is never
served by these proof buttons.

Following the Project Owner request on 2026-10-20, the Packing proof is also
available through an order-scoped customer endpoint once the Packing job is
`PACKED`. Pickup jobs expose their completion date and completion reference as
the customer-facing collection record; V1 does not upload a separate pickup
photo. All file links continue to require the customer's existing order or
proofing session.

Each record stores order, uploader ID, uploader-role snapshot, order-status
snapshot, created timestamp, visibility, publication timestamp, message, and
attachment metadata/hash. A deterministic unique key rejects exact duplicate
submissions.

## Tests

`tests/Feature/Orders/OrderProgressVisibilityTest.php` covers:

- assigned and unassigned Designer permissions;
- assigned and unassigned Production permissions;
- OM operational-order scope;
- ADMIN/OM/CUSTOMER_SERVICE read access, with CUSTOMER_SERVICE upload and
  operational-proof denial;
- uploader-only private attachment access;
- customer order isolation and private-update exclusion;
- customer attachment order/visibility checks;
- one- and two-package Production Proof display, private-file exclusion,
  customer session enforcement, and per-order file isolation;
- one- and two-package Packing proof sections, order-session protected proof
  download, completed Pickup confirmation display, and completed Courier proof
  access from the proofing session;
- default-private behaviour and duplicate rejection;
- both one-package and two-package orders.

## Project Owner Decisions Still Required

The synced project `sources/` directory did not contain the MASTER BLUEPRINT &
SYSTEM SPECIFICATION during implementation. The accepted authorization ADR and
current workflow code were therefore used as the available V1 contract, with
the safer default-private rule applied.

Project Owner approval is required before any of these behaviours are changed:

1. whether particular workflow events should auto-publish to customers;
2. whether an approval/moderation step is required before a specialist's
   customer-visible update is released;
3. retention/retraction rules for an already published customer update.

Until approved, there is no auto-publishing and no post-publication edit/delete
workflow. Legacy Production evidence and pre-completion Packing proof remain
private; only the explicitly approved completed Courier proof and customer-safe
Design previews are exposed through order-scoped endpoints.
