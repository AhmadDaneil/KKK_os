# KKK OS V1 — Operations Guide

## 1. Purpose

This document is the canonical operating guide for KKK OS V1.

It describes the current staff-facing operational workflow for processing a KKK order from customer submission through design, payment, printing, packing, and final fulfilment.

Historical Stage README files remain development and implementation references. Where an older Stage README describes a local development route or an earlier implementation state, this Operations Guide represents the current V1 operational workflow.

The MASTER BLUEPRINT & SYSTEM SPECIFICATION remains the primary source of truth for KKK OS V1 business rules.

---

## 2. Core Operating Principles

1. The database is the application source of truth.
2. Customer data should be entered once wherever possible.
3. Routine normalization, validation, mapping, and Photoshop preparation should be automated.
4. Google Sheet/CSV is the mandatory Photoshop Auto Merge output/integration layer.
5. One business order remains one business order even when two package sides are selected.
6. A 1-package order produces one package-side workflow.
7. A 2-package order contains LELAKI and PEREMPUAN package sides and produces independent design/merge/print work where required.
8. Operational orders must not be permanently deleted.
9. Orders that should no longer continue operationally use Cancelled or Archived handling.
10. Important operational actions are recorded through the relevant event/audit records.

---

## 3. Staff Access and Roles

KKK OS V1 provides a staff login and staff dashboard.

Operational access is controlled by both:

- staff role; and
- assignment to the relevant operational job where required.

### ADMIN

ADMIN is responsible for administrative control such as:

- staff management;
- assigning or reassigning Design jobs;
- assigning or reassigning Printing jobs;
- assigning or reassigning Packing jobs;
- monitoring operational orders.

ADMIN assignment authority does not automatically mean the ADMIN performs an assigned operational task.

### DESIGNER

DESIGNER performs assigned design work.

A Designer may operate only on the Design jobs assigned to that Designer.

### PRINTING

PRINTING staff performs assigned printing work.

A Printing staff member may operate only on Print jobs assigned to that staff member.

### PACKING

PACKING staff performs assigned packing and fulfilment work.

Packing and final fulfilment actions remain subject to assignment rules.

### OPERATION_MANAGEMENT

OPERATION_MANAGEMENT may perform the operational functions explicitly permitted by KKK OS V1, including payment review and applicable packing/fulfilment operations.

Where an operation requires assignment, the staff member must still be assigned to the relevant job.

---

## 4. Staff Order Queue

Staff access operational orders through the Staff Dashboard and Staff Order Queue.

The queue provides operational visibility appropriate to the logged-in role.

Depending on the role and workflow state, staff may:

- search orders;
- filter orders;
- identify orders requiring attention;
- open an order;
- inspect customer/order information;
- inspect operational jobs;
- perform actions permitted for their role and assignment.

Operational actions should be performed through the production staff workflow rather than historical local development endpoints.

---

## 5. Customer and Order Flow

The Customer Dashboard is the production customer input for KKK OS V1.

Google Form V4 is a requirements/prototype reference and is not the final production customer input.

The customer workflow includes:

1. access the correct order/dashboard;
2. provide or complete required order information;
3. save incomplete work where supported;
4. resume the order later;
5. review normalized information;
6. confirm complete order information;
7. participate in design review/correction;
8. complete required payment steps;
9. obtain fulfilment information such as courier tracking information where applicable.

Known customer/payment information should not require routine re-entry when already available to the system.

---

## 6. Package Rules

### 1-Package

A 1-package order contains one selected package side:

- LELAKI; or
- PEREMPUAN.

The selected side proceeds through the applicable design, Photoshop merge, printing, and packing workflow.

### 2-Package

A 2-package order remains one business order but contains two package sides:

- LELAKI; and
- PEREMPUAN.

Where side-specific production work is required, the two sides must remain independent.

In particular:

- Photoshop merge jobs/rows must remain independent;
- output for one side must not overwrite the other;
- printing produces independent side-specific print jobs;
- packing remains one order-level packing job containing both required package-side items.

---

## 7. Data Normalization and Final Review

Customer data is normalized by the application before downstream production use.

Normalization is intended to remove routine manual cleaning while preserving valid customer data.

Examples include:

- trimming unnecessary surrounding whitespace;
- collapsing unnecessary repeated whitespace;
- applying field-specific normalization where required;
- preparing consistent values for downstream mapping.

Valid name formatting must not be corrupted merely to force generic title casing.

The customer must have a final review/confirmation step before confirmed production data proceeds downstream.

---

## 8. Design Workflow

The Design workflow is assignment-based.

Typical operational sequence:

1. ADMIN assigns the Design job to a Designer.
2. Assigned Designer opens the order.
3. Designer starts the Design job.
4. Designer prepares the required artwork.
5. Designer uploads the production artwork and customer preview.
6. Designer marks the artwork ready for customer review.
7. Customer reviews the design.
8. If correction is requested, the Design job enters the correction workflow.
9. Assigned Designer resumes correction work and uploads the next artwork version.
10. When the customer approves the design, the approved artwork becomes the production reference for subsequent workflow.

Important rules:

- only the assigned Designer performs Designer operational actions;
- artwork versions must remain traceable;
- correction requests and approval transitions must remain traceable;
- approved production work must use the appropriate approved/latest production artwork version.

---

## 9. Photoshop Auto Merge

Photoshop Auto Merge remains part of the V1 production integration.

The database is the application source of truth.

Google Sheet/CSV remains the mandatory Photoshop Auto Merge integration/output layer.

### 1-Package

A 1-package order produces one independent Photoshop merge job/row for the selected package side.

### 2-Package

A 2-package order produces two independent Photoshop merge jobs/rows:

- LELAKI;
- PEREMPUAN.

The two sides must not overwrite each other.

The current accepted Photoshop implementation is the locked Stage 9 V11 side-aware workflow documented by the Stage 9 acceptance records.

Real Adobe Photoshop acceptance has been completed for the accepted V1 Photoshop workflow.

Production deployment status is tracked separately from acceptance status.

---

## 10. Payment Workflow

KKK OS V1 currently uses the approved manual receipt-review workflow for operational payment handling.

A real production payment gateway has not been selected/integrated and must not be assumed by staff documentation.

### Deposit

Where the order requires deposit verification:

1. customer submits the required payment receipt;
2. authorized staff reviews the receipt;
3. authorized staff approves or rejects the receipt;
4. rejection should include the required reason;
5. the relevant payment and order state is updated through the application.

### Balance Payment

After the design reaches the required approved state:

1. the balance payment becomes applicable;
2. customer submits the required balance payment receipt;
3. authorized staff reviews the receipt;
4. authorized staff approves or rejects the payment;
5. successful approval updates the correct payment transaction/order;
6. the order may then proceed to the paid/production workflow.

Payment operations must remain associated with the correct order.

Payment events and important review actions must remain traceable.

---

## 11. Printing Workflow

Printing begins only after the order satisfies the required paid/production conditions.

### Job Creation

Each production-approved design side creates the corresponding Print job.

Expected behavior:

- 1-package → exactly 1 Print job;
- 2-package → exactly 2 independent Print jobs.

Initialization must be idempotent and must not create duplicate jobs.

### Staff Operation

Typical sequence:

1. ADMIN assigns the Print job.
2. Assigned PRINTING staff opens the order.
3. PRINTING staff starts the job.
4. Printing is performed using the correct approved artwork.
5. PRINTING staff marks the job printed.

For a 2-package order, both independent Print jobs must reach the required completed printing state before the order proceeds into packing.

Important Print actions are recorded through Print job events.

---

## 12. Packing Workflow

Packing is order-level.

Expected structure:

- 1-package → one Packing job containing one package-side item;
- 2-package → one Packing job containing two package-side items, LELAKI and PEREMPUAN.

Typical sequence:

1. all required Print jobs reach PRINTED;
2. Packing job becomes available;
3. ADMIN assigns the Packing job;
4. assigned PACKING/authorized operational staff starts packing;
5. every required Packing item is verified;
6. staff marks the Packing job packed.

A Packing job must not be marked complete until every required package-side item has been verified.

Packing initialization must be idempotent.

Important Packing actions are recorded through Packing job events.

---

## 13. Pickup Fulfilment

Pickup uses the production staff workflow.

Typical sequence:

1. Packing job reaches PACKED.
2. Fulfilment is READY.
3. Customer/order is handled for pickup according to current KKK operations.
4. Assigned authorized staff performs the final pickup collection action.
5. Staff explicitly confirms completion.
6. Fulfilment becomes COLLECTED.
7. Business order becomes COMPLETED.

An optional completion reference may be recorded where applicable.

Only staff permitted by role and satisfying the applicable assignment rule may perform final pickup collection.

### Current V1 Limitation

A mandatory automated pickup identity-verification process is not included in V1.

Do not invent an identity-verification rule without Project Owner approval.

---

## 14. Courier Fulfilment

Courier fulfilment uses the production Packing workflow.

Typical sequence:

1. Packing job reaches PACKED.
2. Fulfilment is READY.
3. Parcel is prepared for courier handoff.
4. Required packing proof image is captured/uploaded.
5. Courier provider is recorded.
6. Tracking number is recorded.
7. Assigned authorized staff explicitly confirms COMPLETE.
8. Fulfilment becomes COMPLETED.
9. Business order becomes COMPLETED.

For the current V1 courier contract, COMPLETED means KKK operational responsibility has completed after the packed parcel has been handed to the courier and the required proof/tracking information has been recorded.

It does not mean the courier has delivered the parcel to the customer.

### 2-Package Courier Rule

A 2-package business order remains one business fulfilment.

Both package sides remain under:

- one order-level Packing job; and
- one Courier fulfilment.

V1 records one shipment proof and one tracking number for that business order unless a future approved business rule introduces split shipment.

---

## 15. Customer Courier Tracking

For a completed Courier order:

1. KKK OS stores the courier provider and tracking number.
2. Tracking information is exposed through the Customer Dashboard contract.
3. Customer obtains the tracking number from KKK.
4. Customer uses the courier's external tracking service to track the parcel.

KKK OS V1 does not provide live courier tracking inside the system.

---

## 16. Cancelled and Archived Orders

Operational orders must not be permanently deleted.

When an order should no longer proceed normally, use the approved Cancelled or Archived lifecycle behavior.

Cancelled/Archived records must remain recoverable/auditable according to the application rules.

Staff must not manually delete operational database records as a substitute for lifecycle handling.

---

## 17. Error and Recovery Procedure

### Expected Business Rejection

Some operations may be rejected because the required workflow condition has not been satisfied.

Examples include:

- attempting an operation in the wrong lifecycle state;
- attempting an assigned operation as the wrong staff member;
- attempting packing before required printing is complete;
- attempting fulfilment before packing is complete;
- missing required courier proof/provider/tracking information.

Staff should:

1. read the displayed error;
2. verify the order/job state;
3. verify the assigned staff member;
4. verify required information is complete;
5. correct the operational prerequisite;
6. retry the normal workflow action.

Do not modify database records manually merely to bypass a rejected workflow transition.

### Unexpected System Error

If an unexpected system error occurs:

1. do not repeatedly force the operation;
2. record the Order ID and affected operational job;
3. record the action being attempted;
4. retain any visible error information;
5. escalate the issue to the KKK Systems Team;
6. verify the database/event state before attempting manual recovery.

Unexpected application failures should remain available to the application's error reporting/logging mechanism.

---

## 18. Audit and Traceability

Critical operational actions are recorded using the relevant event/audit mechanisms.

This includes applicable events for:

- order lifecycle;
- Design jobs;
- artwork versions/corrections;
- payments;
- Printing jobs;
- Packing jobs;
- fulfilment jobs;
- assignments and reassignments.

Where an actor is recorded, operational investigation should use those audit records rather than relying solely on staff recollection.

---

## 19. Known V1 Limitations

The following are known limitations or deliberately excluded automation in the current V1 scope:

- no selected real production payment gateway integration;
- no live courier tracking API inside KKK OS;
- no courier tracking webhook;
- no automatic airway bill creation;
- no automated courier pricing/SLA policy;
- no mandatory automated pickup identity-verification process;
- uploaded courier proof is validated as an accepted upload but KKK OS does not automatically inspect the image contents to confirm that the parcel and tracking label are visibly correct.

PACKING staff remains operationally responsible for ensuring the courier proof satisfies the required operational evidence.

These limitations must not be silently converted into new V1 business rules or V2 features.

---

## 20. Operational Escalation

Escalate to the KKK Systems Team when:

- the application reports an unexpected error;
- an order appears stuck despite satisfying its normal prerequisites;
- an expected operational job was not created;
- assignment/state does not match the visible workflow;
- an audit/event discrepancy is suspected;
- Photoshop production output does not match the accepted contract;
- customer/order/payment association appears incorrect.

Escalate to the Project Owner when a decision would change:

- a business rule;
- customer experience;
- cost;
- V1 scope;
- operational policy.

Do not invent a new business rule to resolve an operational exception.

---

## 21. Regression and Change Safety

Changes affecting an operational workflow must preserve the Master Blueprint and V1 business rules.

Relevant automated tests must pass before a change is considered complete.

Both 1-package and 2-package paths must be tested for modules where package behavior is relevant.

The V1 minimum acceptance matrix T01-T12 remains part of completion verification.

Photoshop-related changes must preserve the accepted Photoshop contract and independent 2-package behavior.

---

## 22. Documentation Authority

Documentation should be interpreted in this order:

1. MASTER BLUEPRINT & SYSTEM SPECIFICATION — business and V1 source of truth.
2. Current canonical V1 operating/completion documentation.
3. Current code, tests, ADRs, and locked acceptance evidence for technical implementation.
4. Historical Stage README files and development notes.

Historical documents should not be interpreted as overriding the Master Blueprint or a later locked V1 implementation decision.

---

## 23. V1 Operational Flow Summary

Customer Order
→ Customer Dashboard
→ Data Completion / Normalization / Final Review
→ Design
→ Customer Review / Correction / Approval
→ Balance Payment
→ Printing
→ Packing
→ Courier or Pickup
→ Completed

For 2-package orders, LELAKI and PEREMPUAN remain independent where side-specific production work is required, while remaining under one business order.

---

## 24. Document Status

Document: KKK OS V1 Operations Guide
Purpose: Canonical current V1 operational reference
Scope: V1 only
Business authority: MASTER BLUEPRINT & SYSTEM SPECIFICATION
Historical Stage documentation: retained for development history and technical reference
