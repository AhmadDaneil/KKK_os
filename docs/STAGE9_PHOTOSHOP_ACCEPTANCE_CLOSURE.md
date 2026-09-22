# KKK OS V1 — Stage 9 Photoshop Integration Acceptance Closure

## Status

Stage 9A: PASS / LOCKED
Stage 9B: PASS / LOCKED
Stage 9C: PASS / LOCKED
Stage 9: COMPLETE / LOCKED

Acceptance date: 2026-09-22

## Authoritative Photoshop JSX

Repository artifact:

photoshop/auto_kad_full_qr_patched_v11_side_aware.jsx

SHA256:

E78BF94AE791AB5AFFB3B273B97714E3DA2C0FF0987962A90FEE3BF2A8372D39

The repository artifact is byte-for-byte identical to the V11 JSX used
during the controlled real Adobe Photoshop acceptance run.

## Acceptance Environment

Controlled acceptance root:

C:\KKK-DEV\photoshop-acceptance

Real production MASTER assets were not modified.

Template tested:

MASTER/SONGKET/CKS-218

The acceptance environment used copied production template assets.

## Scenario A — 1-Package LELAKI

Order / fixture:

KKK-260909-0006

CSV contract:

- 1 row
- 28 columns
- majlis = LELAKI
- qtykad = 200
- Tema = Songket
- DesignCode = CKS-218

Photoshop batch:

Batch_20260922_1115

Observed result:

- V11 execution: PASS
- JPEG outputs: 8
- PSD outputs: 8
- QR outputs: 1
- LELAKI groom-above-bride presentation: PASS
- KAD DEPAN visual data: PASS
- KAD BELAKANG visual data: PASS
- QR placement on KAD BELAKANG: PASS
- "PCS PCS" regression: NOT PRESENT
- Runtime exception: NONE

Known fixture limitation:

KKK-260909-0006 uses a placeholder Maps URL. Therefore QR destination
validation for this fixture is not treated as acceptance evidence.

## Scenario B — 2-Package

Order / fixture:

KKK-260909-0007

CSV contract:

- 2 rows
- 28 columns per row
- one LELAKI row
- one PEREMPUAN row
- qtykad = 500 for both rows
- Tema = Songket
- DesignCode = CKS-218

Photoshop batch:

Batch_20260922_1125

Observed result:

- V11 execution: PASS
- JPEG outputs: 16
- PSD outputs: 16
- QR outputs: 2
- LELAKI output identity: PASS
- PEREMPUAN output identity: PASS
- LELAKI groom-above-bride presentation: PASS
- PEREMPUAN bride-above-groom presentation: PASS
- Side-specific parent data: PASS
- Side-specific event data: PASS
- Cross-side contamination: NONE OBSERVED
- Cross-side output overwrite: NONE OBSERVED
- "PCS PCS" regression: NOT PRESENT
- QR insertion and auto-fit on KAD BELAKANG: PASS
- LELAKI QR destination: PASS
- PEREMPUAN QR destination: PASS
- PSD layered/editable output: PASS
- Runtime exception: NONE

## Side-Aware Rule Verified

LELAKI:

- groom above bride
- groom abbreviation above bride abbreviation

PEREMPUAN:

- bride above groom
- bride abbreviation above groom abbreviation

Database and CSV semantic field meanings remain unchanged.

## Collision Protection Verified

For a 2-package order, V11 produced independent LELAKI and PEREMPUAN
customer folders, QR files, JPEG outputs, and PSD outputs.

No side overwrote the other side.

## Known Runtime Behaviour

Only templates containing the `qrlocation` layer receive QR replacement.

For CKS-218, KAD BELAKANG contained the required `qrlocation` layer and
QR replacement plus auto-fit completed successfully.

Templates without that layer logged:

[QR] Layer 'qrlocation' tak jumpa.

This did not prevent their JPEG or PSD export.

## Acceptance Conclusion

The tested KKK OS V1 Photoshop integration supports:

- 1-package LELAKI processing
- 2-package LELAKI and PEREMPUAN processing
- exact 28-column CSV input
- side-aware presentation
- independent 2-package outputs
- QR generation and placement
- layered PSD output
- JPEG output
- collision-safe side-specific output identity

Stage 9 real Photoshop acceptance is complete for the tested V1 paths.

Production deployment was NOT performed as part of this acceptance.

Git push remains a separate controlled action.
