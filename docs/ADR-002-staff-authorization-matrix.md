# ADR-002 — Staff Authorization Matrix

## Status

Accepted — KKK OS V1

Updated 2026-10-05 following Project Owner approval that Packing belongs to
Operation Management, OM may view private payment receipts, and OM may assign
operational jobs.

## Context

KKK OS V1 separates staff visibility, assignment authority, and operational
authority.

Access to a staff route or workstream does not automatically grant permission
to operate a job. Operational authorization must continue to be enforced at
the controller/service boundary where required.

This ADR records the approved V1 authorization contract and prevents broad
role checks from unintentionally granting operational permissions.

## Approved Roles

- ADMIN
- OPERATION_MANAGEMENT
- CUSTOMER_SERVICE
- DESIGNER
- PRODUCTION

There is no separate PACKING or FULFILMENT role in V1. `ROLE_PRINTING` and
`ROLE_PACKING` remain deprecated code aliases only; new accounts must use
`PRODUCTION` or `OPERATION_MANAGEMENT`.

## Authorization Matrix

| Capability | ADMIN | OPERATION_MANAGEMENT | CUSTOMER_SERVICE | DESIGNER | PRODUCTION |
|---|---|---|---|---|---|
| Monitor all departments | YES | YES | YES | NO | NO |
| View private payment receipts | YES | YES | NO | NO | NO |
| Approve / verify payment | YES | YES | NO | NO | NO |
| Assign / reassign operational jobs | YES | YES | NO | NO | NO |
| Cancel / archive with audit reason | YES | YES | NO | NO | NO |
| Operate Design jobs | NO | NO | NO | Assigned only | NO |
| Operate Printing jobs | NO | NO | NO | NO | Assigned only |
| Operate Packing / fulfilment | NO | Any Packing job | NO | NO | NO |

## Packing Assignment

ADMIN or OPERATION_MANAGEMENT may assign or reassign a Packing job to an
active OPERATION_MANAGEMENT staff member.

## Packing Operational Rules

Normal Packing operations are:

- start Packing;
- verify Packing items;
- mark Packing as PACKED.

OPERATION_MANAGEMENT may operate Packing and fulfilment jobs. ADMIN retains
monitoring and assignment authority but does not perform the specialist
Packing workflow through the staff role.

## Design and Printing Rules

DESIGNER may operate only Design jobs assigned to themselves.

PRODUCTION may operate only Printing jobs assigned to themselves.

ADMIN and OPERATION_MANAGEMENT must not receive an operational bypass for
Design or Printing jobs.

## Assignment Authority

ADMIN and OPERATION_MANAGEMENT may assign or reassign Design, Printing,
Packing and fulfilment work.

## Route Access vs Operational Authorization

Middleware role access and job operational authorization are separate
concerns.

ADMIN may have broad route/workstream access for monitoring and administration,
but that must not be interpreted as permission to perform operational job
actions.

Do not use a broad ADMIN or operation-management helper as an operational
bypass where the approved matrix requires assigned-only access.

`User::isOperationManagement()` includes ADMIN and is appropriate for shared
management actions. Specialist workflow controllers must still enforce the
specific operational role and job ownership where applicable.

## Courier Ownership

There is no separate FULFILMENT staff role in KKK OS V1.

OPERATION_MANAGEMENT owns the post-packing courier update:

PACKED
→ proof photo
→ courier provider
→ tracking number
→ explicit COMPLETE
→ fulfilment COMPLETED
→ order COMPLETED

For a 2-package order there is still one business Packing job and one shipment
completion/tracking record.

## Regression Requirements

Authorization changes must retain regression coverage for:

- ADMIN monitoring without operational job execution;
- OPERATION_MANAGEMENT monitoring all orders;
- OPERATION_MANAGEMENT payment approval / verification;
- ADMIN and OPERATION_MANAGEMENT assignment and reassignment;
- Packing jobs assigned to active OPERATION_MANAGEMENT staff;
- OPERATION_MANAGEMENT denied Design operations;
- OPERATION_MANAGEMENT denied Printing operations;
- OPERATION_MANAGEMENT allowed normal operations on any Packing job;
- OPERATION_MANAGEMENT Packing and fulfilment authorization;
- DESIGNER assigned-only authorization;
- PRODUCTION assigned-only authorization;
- both 1-package and 2-package workflow paths.

## Verification

Authorization contract regression checkpoint on 2026-10-05:

- Full suite: 363 tests passed, 2,053 assertions.

## Known Frontend Integration Note

Frontend ownership is handled separately from this backend authorization
contract.

Frontend controls and backend authorization must reflect the same matrix.
Backend middleware/controller authorization remains authoritative.
