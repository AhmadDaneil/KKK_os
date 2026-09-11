# KKK OS V1 — Stage 9B Real Photoshop Acceptance Test

## Purpose

Validate the real designer runtime path:

KKK OS confirmed order
→ canonical merge jobs
→ exact 28-column Ready To Merge CSV
→ current verified JSX
→ real MASTER/<Tema>/<DesignCode>/*.psd
→ JPEG + PSD + QR + logs

This is an acceptance test. It does not introduce a new V1 business rule.

## Preconditions

Use a Windows workstation with:
- Adobe Photoshop used by the KKK designer workflow;
- current verified JSX `auto_kad_full_qr_patched_v10_2026.jsx`;
- real KKK root folder;
- real `MASTER/<Tema>/<DesignCode>/` template structure;
- internet access and Windows PowerShell available because the current JSX QR path depends on them.

Use test/non-customer-sensitive order data where possible.

## Required scenarios

### Scenario A — 1 package

Prepare one confirmed order where:
- package_count = 1;
- one valid side exists;
- card_quantity is set;
- theme/design_code points to a real PSD template;
- qrlink is a valid Google Maps URL;
- all required Photoshop/customer fields are populated.

Expected:
- exactly one merge job;
- exactly one CSV data row;
- correct qtykad;
- correct template selected;
- one customer output set;
- JPEG generated;
- layered PSD generated;
- QR generated/replaced;
- no `PCS PCS`.

### Scenario B — 2 packages

Prepare one confirmed order where:
- package_count = 2;
- both LELAKI and PEREMPUAN exist;
- both have distinct real design codes or at minimum distinct package data;
- one order-level card_quantity is set;
- both qrlink values are valid.

Expected:
- exactly two merge jobs;
- exactly two CSV data rows;
- both rows use the SAME qtykad (Option A);
- LELAKI/PEREMPUAN data remain independent;
- both outputs are generated;
- no overwrite between sides;
- no `PCS PCS`.

## Step 1 — Export from KKK OS

Run for the 1-package order:

```powershell
php artisan photoshop:acceptance-export KKK-YYMMDD-NNNN
```

Run again for the 2-package order.

Default output:

```text
storage/app/photoshop-acceptance/<ORDER_ID>/
```

Each export contains:
- `<ORDER_ID>_READY_TO_MERGE.csv`
- `<ORDER_ID>_acceptance_manifest.json`

Do not manually edit the CSV.

## Step 2 — Inspect CSV before Photoshop

Open only for inspection.

Verify:
- 28 columns;
- noinvoice matches order ID;
- qtykad is numeric only, e.g. `200`, NOT `200 PCS`;
- tema matches real MASTER theme folder;
- designcode matches real template/design folder;
- qrlink is populated;
- 1 package = one data row;
- 2 package = two data rows;
- both 2-package rows have same qtykad.

## Step 3 — Run real JSX in Photoshop

Use the same runtime procedure currently used by the KKK designer:

1. Launch Photoshop.
2. Run the verified JSX.
3. Select the real ROOT KING KAD KAHWIN folder.
4. Select the generated KKK OS CSV.
5. Allow the batch to finish.
6. Do not rename or modify generated output during execution.

The verified JSX expects required headers:
- NoInvoice
- Tema
- DesignCode

Header matching is case-insensitive.

## Step 4 — Locate batch output

Expected batch pattern:

```text
OUTPUT/Batch_YYYYMMDD_HHMM/
```

Expected initial workflow stage:

```text
1 Waiting Customer
```

Expected per-customer output directories include:

```text
Export JPEG
PSD
QR
```

## Step 5 — Automated filesystem verification

Run:

```powershell
powershell -ExecutionPolicy Bypass -File tools\Verify-PhotoshopAcceptance.ps1 `
  -ManifestPath "C:\path\to\<ORDER_ID>_acceptance_manifest.json" `
  -BatchFolder "C:\KING KAD KAHWIN\OUTPUT\Batch_YYYYMMDD_HHMM"
```

Run once for each scenario/batch.

The verifier checks:
- stage folder exists;
- minimum JPEG count;
- minimum PSD count;
- minimum QR count;
- no filename contains `PCS PCS`;
- log presence when detectable.

## Step 6 — Mandatory manual visual verification

Automated checks cannot prove visual correctness.

For each output, open the JPEG and layered PSD and verify:

- groom name;
- bride name;
- abbreviations if template contains those layers;
- father name;
- mother name;
- day/date/month/Hijri text;
- bersanding time;
- meal time;
- address;
- contact names and numbers;
- QR visibly fits the intended QR location;
- QR scans to the expected Google Maps URL;
- correct design/template was selected;
- no text unexpectedly overflows;
- no LELAKI value appears in PEREMPUAN output or vice versa.

For 2-package, compare both outputs side-by-side.

## Step 7 — Record result

For each scenario record:

```text
Order ID:
Package count:
Card quantity:
Sides:
Theme/design code per side:
CSV generated: PASS/FAIL
Template discovery: PASS/FAIL
JPEG: PASS/FAIL
PSD: PASS/FAIL
QR generated: PASS/FAIL
QR scan: PASS/FAIL
Text mapping: PASS/FAIL
No PCS PCS: PASS/FAIL
No cross-side overwrite: PASS/FAIL
Photoshop/JSX errors:
Notes:
Overall: PASS/FAIL
```

## Stage 9B completion rule

Do NOT mark Stage 9B DONE until:
- 1-package real Photoshop scenario passes;
- 2-package real Photoshop scenario passes;
- QR generation and scan pass;
- no `PCS PCS`;
- no cross-side overwrite;
- generated JPEG and layered PSD are visually correct;
- any JSX/runtime error is documented and resolved or explicitly accepted by Project Owner if it changes operations/cost/customer experience.

## Known runtime limitation

The currently verified JSX generates/downloads QR in the Windows/Photoshop runtime and therefore depends on external network/runtime behavior. Stage 9B should document any failure caused by that dependency rather than silently changing the architecture.
