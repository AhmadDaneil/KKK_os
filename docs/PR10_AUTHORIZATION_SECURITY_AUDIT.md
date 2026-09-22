# PR-10 Authorization & Security Audit

**Project:** KKK OS V1

**Audit:** PR-10 Authorization & Access-Control Audit

**Status:** PASS / LOCKED

**Completed:** 2026-09-22

## 1. Purpose

PR-10 verifies the authorization and access-control boundaries of KKK OS V1 without changing the approved business rules or staff authorization model.

The audit covers:

- customer order isolation;
- staff role authorization;
- operational job assignment isolation;
- private artwork and payment receipt access;
- private packing/courier proof storage;
- local-only development routes;
- 1-package and 2-package authorization paths.

## 2. Governing Authorization Rules

The following rules remain locked:

1. A customer may access only an order authorized by that customer's order session/access token.
2. A customer authorized for Order A must not read or mutate Order B.
3. Staff access is controlled by active staff status, role, and resource/job assignment where applicable.
4. ADMIN manages, monitors, assigns, and reassigns operational work but does not automatically bypass job ownership to perform assigned operational actions.
5. Assigned operational staff must not operate another staff member's assigned job.
6. Sensitive artwork, payment receipts, packing proof, and courier proof remain on private storage.
7. `/dev/*` routes must not be registered outside the local environment.
8. Two-package orders remain one business order with independent side-specific downstream jobs where required.

## 3. PR-10A — Route & Production Exposure

**Result: PASS / LOCKED**

Verified:

- customer routes are protected by the centralized customer order session/access mechanism;
- staff routes use staff/admin authentication, active-staff checks, and role middleware as applicable;
- resource-level ownership checks remain in the operational workflow;
- `/dev/*` routes are registered only when the application environment is `local`;
- automated regression coverage confirms development routes are unavailable outside the local environment.

No production authorization change was required.

## 4. PR-10B — Customer Cross-Order Isolation

**Result: PASS / LOCKED**

Verified:

- valid magic-link access establishes an order-specific customer session;
- clean dashboard access without authorization is blocked;
- invalid and expired access tokens are blocked;
- a session for Order A cannot access Order B;
- Order A authorization cannot mutate Order B draft data;
- Order A authorization cannot confirm Order B;
- Order A authorization cannot upload Order B deposit or balance receipts;
- Order A authorization cannot request artwork correction for Order B;
- Order A authorization cannot approve Order B artwork;
- protected Order B state remains unchanged after denied mutation attempts.

Dedicated regression coverage:

`tests/Feature/Security/CustomerCrossOrderMutationTest.php`

## 5. PR-10C — Staff Role & Assignment Isolation

**Result: PASS / LOCKED**

Verified:

- DESIGNER access is restricted to design work;
- PRINTING access is restricted to printing work;
- PACKING access is restricted to packing work;
- OPERATION_MANAGEMENT retains its approved monitoring/workflow permissions;
- inactive, unsupported-role, and unauthorized users are denied;
- assignment and reassignment remain ADMIN-controlled;
- assigned operational staff cannot operate another staff member's job;
- ADMIN does not bypass assignment to perform designer, printing, or packing operational actions;
- two-package design sides remain independently assignable and independently authorized.

Important distinction:

Role middleware acceptance does not imply resource-level operational ownership. Resource/job authorization remains enforced by the workflow layer.

## 6. PR-10D — Private File & Proof Endpoints

**Result: PASS / LOCKED**

Verified:

### Artwork

- customer artwork preview requires an authorized customer order session;
- design jobs are scoped to the authorized order;
- cross-order artwork preview is denied;
- customer preview uses the customer preview artifact and does not fall back to the private source artwork;
- artwork is served through controlled application responses;
- private/no-store response behavior is applied to customer artwork preview;
- staff artwork upload currently stores source and preview artifacts on the private `local` disk.

### Payment Receipts

- deposit and balance receipts are stored on the private `local` disk;
- payment receipt retrieval is protected by staff authentication and authorized payment-review roles;
- DESIGNER, PRINTING, and PACKING roles cannot retrieve private payment receipts;
- authorized OPERATION_MANAGEMENT access is covered by regression tests.

### Packing / Courier Proof

- packing and courier proof files are stored on the private `local` disk;
- no public proof retrieval route was identified during the PR-10 route audit;
- courier completion remains protected by assignment authorization.

Security invariant:

Sensitive operational files must remain on private storage. They must not be moved to the public disk without a separate security review and approved access design.

## 7. PR-10E — Full Regression

**Result: PASS / LOCKED**

Final full regression checkpoint:

- **269 tests passed**
- **1335 assertions**
- **0 failures**

Previous pre-PR-10 baseline:

- 262 tests passed
- 1302 assertions

PR-10 added security regression coverage without breaking the existing application suite.

The full suite also confirms critical 1-package and 2-package paths, including:

- customer data flows;
- independent two-package design jobs;
- Photoshop Auto Merge row generation;
- printing;
- packing;
- fulfilment;
- authorization and assignment isolation.

## 8. Changes Introduced by PR-10

PR-10 did not require production authorization logic changes.

Test coverage added/extended:

- added `CustomerCrossOrderMutationTest`;
- extended `StaffDepositPaymentWorkflowTest` with private receipt access-control coverage.

Temporary route inventory generated during the audit was removed before commit.

## 9. Known Security Invariants

Future changes must preserve:

- order-specific customer authorization;
- no cross-order customer access;
- staff role boundaries;
- assignment ownership boundaries;
- no implicit ADMIN operational bypass;
- private sensitive-file storage;
- customer preview/source-artwork separation;
- production exclusion of `/dev/*`;
- independent authorization of side-specific two-package jobs.

Any intentional change to these rules requires explicit review against the KKK OS V1 Master Blueprint and the approved authorization architecture.

## 10. Final PR-10 Decision

PR-10 Authorization & Access-Control Audit is:

**PASS / LOCKED**

No unresolved authorization defect was identified in the audited V1 scope.

The repository may proceed to final diff review and commit after documentation and working-tree verification.
