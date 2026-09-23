# KKK OS V1 — Photoshop Operational Re-Verification

## Status

Result: PASS

Verification date: 2026-09-23

This re-verification does not reopen or change the locked Stage 9 acceptance.
It provides additional operational evidence using KKK OS generated acceptance
exports with the verified Adobe Photoshop workflow.

## Automated Regression Tests

Focused Photoshop integration tests:

- PhotoshopAutoMergeContractV1Test
- PhotoshopProductionExportWiringTest
- OrderToPhotoshopEndToEndTest

Result:

- 10 tests passed
- 59 assertions passed

## Scenario A — 1-Package PEREMPUAN

Order:

KKK-260922-0002

KKK OS export:

- package_count = 1
- card_quantity = 200
- side = PEREMPUAN
- merge job = KKK-260922-0002-P
- theme = CARTOON
- design_code = CKC-024
- CSV columns = 28
- CSV data rows = 1

Photoshop batch:

Batch_20260923_1040

Automated filesystem verification:

- Stage folder exists: PASS
- JPEG outputs: 8
- PSD outputs: 8
- QR outputs: 1
- No `PCS PCS` filename defect: PASS
- PEREMPUAN output evidence: PASS
- Photoshop log detected: PASS
- Overall automated file checks: PASS

Manual JPEG inspection performed during the controlled run confirmed the
expected customer/event/contact data was present in the generated artwork.

## Scenario B — 2-Package LELAKI + PEREMPUAN

Order:

KKK-260909-0007

KKK OS export:

- package_count = 2
- card_quantity = 500
- merge jobs:
  - KKK-260909-0007-L
  - KKK-260909-0007-P
- sides:
  - LELAKI
  - PEREMPUAN
- theme = Songket
- design_code = CKS-218
- CSV columns = 28
- CSV data rows = 2
- same qtykad for both rows = 500

Photoshop batch:

Batch_20260923_1046

Automated filesystem verification:

- Stage folder exists: PASS
- JPEG outputs: 16
- PSD outputs: 16
- QR outputs: 2
- No `PCS PCS` filename defect: PASS
- LELAKI output evidence: PASS
- PEREMPUAN output evidence: PASS
- Photoshop logs detected: PASS
- Overall automated file checks: PASS

Manual JPEG inspection performed during the controlled run confirmed distinct
LELAKI and PEREMPUAN customer/event/contact data in the inspected outputs.
No cross-side output overwrite was observed in the inspected evidence.

Actual inspected image evidence included:

- LELAKI KAD DEPAN
- LELAKI KAD BELAKANG
- PEREMPUAN KAD BELAKANG
- PEREMPUAN BANTING

A PEREMPUAN KAD DEPAN image was not part of the supplied manual inspection
evidence in this re-verification.

## Known Visual Observation

Long address text may wrap awkwardly depending on the PSD/template layout.
During inspection, address data reached Photoshop, but one output visually
split a word across lines.

This is recorded as a template/layout observation and is not evidence of
database-to-CSV data loss.

## Acceptance Tooling Fix

During this re-verification,
tools/Verify-PhotoshopAcceptance.ps1 initially failed to parse under Windows
PowerShell because the UTF-8 file did not contain a BOM.

The file encoding was changed to UTF-8 with BOM without changing verifier
business logic.

Verification after the fix:

- PowerShell parser check: PASS
- 1-package automated filesystem verification: PASS
- 2-package automated filesystem verification: PASS

Commit:

ddad23a — fix: make Photoshop acceptance verifier PowerShell compatible

## Conclusion

Operational re-verification: PASS.

The tested KKK OS export path successfully produced Photoshop-compatible
artifacts for both:

- 1-package processing
- 2-package LELAKI + PEREMPUAN processing

Stage 9 remains COMPLETE / LOCKED.

Production deployment remains a separate pending activity.
