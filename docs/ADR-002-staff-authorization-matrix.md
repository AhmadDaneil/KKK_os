# ADR-002 — Staff Authorization Matrix

## Status

Accepted — KKK OS V1

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
- DESIGNER
- PRINTING
- PACKING

There is no separate FULFILMENT role in V1.

## Authorization Matrix

| Capability | ADMIN | OPERATION_MANAGEMENT | DESIGNER | PRINTING | PACKING |
|---|---|---|---|---|---|
| Monitor all orders | YES | YES | NO | NO | NO |
| Approve / verify payment | YES | YES | NO | NO | NO |
| Assign / reassign Design jobs | YES | NO | NO | NO | NO |
| Assign / reassign Printing jobs | YES | NO | NO | NO | NO |
| Assign / reassign Packing jobs | YES | NO | NO | NO | NO |
| Operate Design jobs | NO | NO | Assigned only | NO | NO |
| Operate Printing jobs | NO | NO | NO | Assigned only | NO |
| Operate normal Packing workflow | NO | Any Packing job | NO | NO | Assigned only |
| Complete courier fulfilment | NO | Own assigned Packing job only | NO | NO | Own assigned Packing job only |

## Packing Assignment

ADMIN may assign or reassign a Packing job to:

- an active PACKING staff member; or
- an active OPERATION_MANAGEMENT staff member.

This is required because OPERATION_MANAGEMENT is allowed to complete courier
fulfilment only when the Packing job is assigned to that OPERATION_MANAGEMENT
user.

## Packing Operational Rules

Normal Packing operations are:

- start Packing;
- verify Packing items;
- mark Packing as PACKED.

For these operations:

- PACKING staff may operate only their assigned Packing job.
- OPERATION_MANAGEMENT may operate any Packing job.
- ADMIN may not perform Packing operations.

Courier completion has a stricter authorization boundary:

- PACKING may complete only their own assigned Packing job.
- OPERATION_MANAGEMENT may complete only a Packing job assigned to themselves.
- ADMIN may not complete courier fulfilment.

## Design and Printing Rules

DESIGNER may operate only Design jobs assigned to themselves.

PRINTING may operate only Printing jobs assigned to themselves.

ADMIN and OPERATION_MANAGEMENT must not receive an operational bypass for
Design or Printing jobs.

## Assignment Authority

Only ADMIN may assign or reassign Design, Printing, or Packing jobs.

OPERATION_MANAGEMENT must not assign or reassign operational jobs.

## Route Access vs Operational Authorization

Middleware role access and job operational authorization are separate
concerns.

ADMIN may have broad route/workstream access for monitoring and administration,
but that must not be interpreted as permission to perform operational job
actions.

Do not use a broad ADMIN or operation-management helper as an operational
bypass where the approved matrix requires assigned-only access.

In particular, `User::isOperationManagement()` includes ADMIN and therefore
must not be used for Packing operational authorization where ADMIN must be
excluded.

## Courier Ownership

There is no separate FULFILMENT staff role in KKK OS V1.

PACKING owns the post-packing courier update:

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
- ADMIN-only Design / Printing / Packing assignment and reassignment;
- ADMIN assigning Packing jobs to active OPERATION_MANAGEMENT staff;
- OPERATION_MANAGEMENT denied Design operations;
- OPERATION_MANAGEMENT denied Printing operations;
- OPERATION_MANAGEMENT allowed normal operations on any Packing job;
- OPERATION_MANAGEMENT courier completion allowed only on their own assigned job;
- PACKING assigned-only authorization;
- DESIGNER assigned-only authorization;
- PRINTING assigned-only authorization;
- both 1-package and 2-package workflow paths.

## Verification

Authorization contract regression checkpoint:

- Staff suite: 101 tests passed, 605 assertions.
- Full backend suite: 262 tests passed, 1302 assertions.

## Known Frontend Integration Note

Frontend ownership is handled separately from this backend authorization
contract.

A frontend test currently exposes ADMIN designer operational controls in the
admin layout. Backend authorization remains authoritative: ADMIN must not be
allowed to execute Design, Printing, or Packing operational actions.

Frontend controls must eventually reflect the same authorization matrix, but
frontend changes are outside the scope of this backend authorization patch.