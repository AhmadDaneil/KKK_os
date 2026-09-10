# KKK OS V1 — Photoshop Auto Merge Contract V1 (VERIFIED)

Status: VERIFIED AGAINST WORKING CSV + CURRENT JSX + PROVIDED PSD  
Date locked: 2026-09-10

## 1. Evidence audited

The contract below is based on these actual working artefacts:

1. `V2_DESIGNER_CREATE_HERE_JANGAN_DELETE_DI_TAB_RTM!_READY_TO_MERGE(1).csv`
2. `V1 ALIVECARD CREATE HERE - READY TO MERGE (2)(1).csv`
3. `auto_kad_full_qr_patched_v10_2026(1).jsx`
4. `IMG_20260910_143115_471.psd`

The JSX is the authoritative consumer for the current Photoshop automation.
The V2 28-column CSV is the compatibility shape used as the Photoshop CSV contract.
The V1 CSV contains the same first 28 columns plus 8 AliveCard/downstream columns.

## 2. Exact Photoshop compatibility CSV headers

Header order is locked as:

```text
noinvoice
qtykad
tema
designcode
gambar
majlis
namapengantinlelaki
namapengantinperempuan
singkatanlelaki
singkatanperempuan
namaayah
namaibu
hari
tarikh
tarikhhari
bulan
bulanislam
masabersanding
masajamuanmakan
alamat
qrlink
nama1
notel1
nama2
notel2
nama3
notel3
flaggambar
```

The current JSX performs case-insensitive header matching, but KKK OS should export the
exact lowercase header spelling/order above for deterministic compatibility.

## 3. JSX-required headers

The current JSX hard-fails if these are missing:

- `noinvoice`
- `tema`
- `designcode`

Rows with blank `noinvoice` are skipped.

## 4. Columns consumed by the current JSX

The JSX reads these columns:

- noinvoice
- qtykad
- tema
- designcode
- namapengantinlelaki
- namapengantinperempuan
- singkatanlelaki
- singkatanperempuan
- namaayah
- namaibu
- hari
- tarikh
- tarikhhari
- bulan
- bulanislam
- masabersanding
- masajamuanmakan
- alamat
- qrlink
- nama1 / notel1
- nama2 / notel2
- nama3 / notel3

Present in V2 but not consumed by the current JSX:

- gambar
- majlis
- flaggambar

They remain in the compatibility CSV because they are present in the working V2 shape.
Do not remove them until the wider downstream workflow is explicitly retired or changed.

## 5. V1 AliveCard extra fields

The V1 CSV contains these additional columns after the 28-column V2 contract:

```text
pagetitle
slug
wa1
wa2
wa3
calllink1
calllink2
calllink3
```

The current Photoshop JSX does not read them.

Therefore they are not part of `photoshop_auto_merge_v1`.
They belong to an AliveCard/downstream export contract if KKK OS later automates that path.

## 6. Canonical KKK Engine → Photoshop CSV mapping

| CSV field | Canonical source | Rule |
|---|---|---|
| noinvoice | `source.order_id` | Exact order ID |
| qtykad | **UNRESOLVED DB SOURCE** | Export numeric quantity only; see blocker below |
| tema | `design.theme` | Trim; preserve approved theme naming |
| designcode | `design.design_code` | Trim + uppercase |
| gambar | **UNRESOLVED / NOT CONSUMED BY JSX** | Empty until source meaning is verified |
| majlis | `source.side` | `LELAKI` or `PEREMPUAN`; compatibility only, JSX does not consume it |
| namapengantinlelaki | `couple.groom_name` | Preserve approved spelling/case |
| namapengantinperempuan | `couple.bride_name` | Preserve approved spelling/case |
| singkatanlelaki | `couple.groom_abbreviation` | Preserve approved spelling/case |
| singkatanperempuan | `couple.bride_abbreviation` | Preserve approved spelling/case |
| namaayah | `parents.father_name` | Note: canonical generic `namabapa` maps to Photoshop `namaayah` |
| namaibu | `parents.mother_name` | Preserve approved spelling/case |
| hari | `event.day_name` | Uppercase for compatibility |
| tarikh | `event.event_date` | Malay display date, e.g. `26 DISEMBER 2026` |
| tarikhhari | `event.event_date` | Day number without leading zero, e.g. `26` |
| bulan | `event.event_date` | Malay short month + year, e.g. `DIS 2026` |
| bulanislam | `event.hijri_date` | Preserve approved display text |
| masabersanding | `event.bersanding_time` | Preserve approved display text |
| masajamuanmakan | `event.meal_time` | Preserve approved display text |
| alamat | `event.full_address` | Preserve cleaned approved address |
| qrlink | `event.google_maps_url` | Valid URL; current JSX turns it into QR |
| nama1 | `event.contacts[1].contact_name` | Empty if missing |
| notel1 | `event.contacts[1].contact_phone` | Normalized phone |
| nama2 | `event.contacts[2].contact_name` | Empty if missing |
| notel2 | `event.contacts[2].contact_phone` | Normalized phone |
| nama3 | `event.contacts[3].contact_name` | Empty if missing |
| notel3 | `event.contacts[3].contact_phone` | Normalized phone |
| flaggambar | **UNRESOLVED / NOT CONSUMED BY JSX** | Empty until semantics are verified |

## 7. Verified PSD/JSX layer contract

The supplied PSD exposes layer metadata consistent with the current JSX for:

```text
namaayah
namaibu
namapengantinlelaki
namapengantinperempuan
nama1
notel1
nama2
notel2
nama3
notel3
hari
tarikhhari
bulan
bulanislam
masabersanding
masajamuanmakan
alamat
qrlocation
```

The JSX additionally attempts to write:
- singkatanlelaki
- singkatanperempuan
- tarikh

Because templates can vary, the exporter contract is CSV-based and the JSX remains
responsible for "set if layer exists" behavior.

## 8. QR behavior

Current JSX:

1. Reads `qrlink`.
2. Calls `api.qrserver.com` via PowerShell to create a 1200x1200 PNG.
3. Finds layer `qrlocation`.
4. Converts it to Smart Object if needed.
5. Replaces Smart Object content with the QR PNG.
6. Auto-fits the replacement into the previous layer bounds.

This is verified current behavior, but it introduces an external runtime dependency:
internet access to the QR API and Windows PowerShell.

## 9. Template discovery

The JSX searches:

```text
ROOT/MASTER/<Tema>/<DesignCode>/*.psd
```

If that folder does not exist, it falls back to PSD files directly under:

```text
ROOT/MASTER/<Tema>/
```

whose filenames contain the design code.

Files containing `_patched` or `copy` are skipped.

## 10. Output behavior

For each processed CSV row, the JSX creates a batch under `OUTPUT` and places the customer
initially in:

```text
1 Waiting Customer
```

It creates per-customer folders:

```text
Export JPEG
PSD
QR
```

and writes both global and customer logs.

It exports:
- JPEG quality 12
- layered PSD

The JSX defines stage folders for:
1. Waiting Customer
2. Correction
3. Approved & Balance Payment
4. Ready To Print

but the current script itself initially writes the newly merged customer into stage 1.

## 11. Quantity behavior and verified integration defect

Current working CSV examples contain values such as:

```text
200 PCS
500 PCS
```

The current JSX later appends `" PCS"` again when building customer/output filenames.

Therefore a literal `200 PCS` input can produce filename/folder text containing:

```text
200 PCS PCS
```

KKK OS exporter must export **numeric quantity only**, for example:

```text
200
```

This preserves the intended visible result when the current JSX appends `PCS`.

For special template names the JSX overrides quantity:
- banner → 1
- banting → 1
- arrow kanan → 2
- arrow kiri → 2
- sticker → 150
- hanger → 1

## 12. Current blocker: qtykad source

`kkk_merge_internal_v1` does not currently contain a card quantity field.

Photoshop export cannot be considered fully production-ready until the approved order/database
source for `qtykad` is identified or added.

This document does NOT invent a database field or business rule.

Technical adapter code therefore requires `qtykad` to be supplied explicitly until the
database source is approved and wired into the canonical payload.

## 13. CSV encoding and escaping

Export:
- UTF-8 with BOM recommended for Photoshop/Windows compatibility.
- RFC-style CSV quoting using PHP `fputcsv`.
- Preserve commas and newlines inside address fields through proper CSV quoting.
- Do not hand-concatenate CSV rows.

The current JSX CSV parser is quote-aware and supports quoted commas/newlines.

## 14. One-package and two-package behavior

No change to KKK OS business model:

- 1-package business order → 1 package side → 1 merge job → 1 CSV row.
- 2-package business order → 2 package sides → 2 independent merge jobs → 2 CSV rows.

Each side must preserve its own:
- design/theme
- parents
- event
- contacts
- QR link

Rows must never overwrite each other.

## 15. Lock status

VERIFIED and safe to lock now:
- 28 header names/order
- columns consumed by JSX
- CSV → JSX mapping
- current template lookup behavior
- current text-layer names
- QR replacement behavior
- JPEG/PSD output behavior
- one-row-per-merge-job rule

Still pending business/data-source confirmation:
- database source of `qtykad`
- semantics/source of `gambar`
- semantics/source of `flaggambar`

No final business rule has been invented for those unresolved fields.
