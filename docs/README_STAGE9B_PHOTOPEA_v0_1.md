# KKK OS V1 — Stage 9B Photopea Adapter v0.1

Fixture order: `KKK-260909-0006`  
Package: 1 (`LELAKI`)  
Tema: `Songket`  
DesignCode: `CKS-218`  
QtyKad: `200`

## Scope

This v0.1 is an acceptance adapter, not the final production batch engine.

It proves:
1. KKK OS 28-column CSV values can drive Photopea text layers.
2. The same script can update matching fields across multiple open PSD documents.
3. `qrlink` can generate a QR and update `qrlocation`.
4. Existing KKK OS database / CSV contract stays unchanged.

Not yet included:
- reading a local CSV file directly inside Photopea;
- finding/opening MASTER templates automatically;
- automatic JPG/PSD batch export;
- 2-package batch processing;
- production output-folder naming.

Those should only be added after this fixture passes visually.

## Before running

Open the real `Songket / CKS-218` PSD files needed for the card, including:
- KAD DEPAN
- KAD BELAKANG

Do NOT use your only master copy. Use test copies.

## Run

Photopea:

`File > Script`

Paste the contents of:

`photopea/KKK_Photopea_AutoMerge_Adapter_v0_1.js`

Click **Run**.

## Expected result

The script searches every currently-open document recursively and updates any matching KKK layer.

It then finds `qrlocation`, opens the Smart Object, generates a QR from the CSV `qrlink`, inserts it, attempts to fit it, saves the Smart Object, and returns to the parent PSD.

Expected alert includes:

- Order: `KKK-260909-0006`
- Tema: `Songket`
- DesignCode: `CKS-218`
- Side: `LELAKI`
- QtyKad: `200`
- QR: UPDATED

Some fields may be reported missing because individual templates do not contain every possible KKK layer. That alone is not a failure.

## Mandatory visual checks

Check both card sides:
- groom / bride names;
- abbreviations;
- parents;
- date/day/month/Hijri;
- bersanding / meal time;
- address;
- contacts;
- QR location and size;
- no unexpected text overflow;
- no wrong-side data.

## Important QR limitation for this fixture

The uploaded KKK OS CSV contains:

`qrlink = https://maps.app.goo.gl/example`

This is only a placeholder test URL. It is sufficient to test QR generation and Smart Object replacement, but it is **not sufficient for final Stage 9B QR-scan acceptance**.

For final acceptance, update the order's `google_maps_url` in KKK OS to a real test Google Maps URL, re-confirm/re-export according to the approved workflow, and run the resulting CSV without manual editing.

## After v0.1 PASS

Proceed to Photopea Adapter v0.2:
- direct CSV ingestion / injected CSV payload;
- automatic template loading;
- controlled JPG + PSD output;
- then 2-package processing.
