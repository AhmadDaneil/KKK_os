# KKK OS V1 — Photoshop Auto Merge Contract V1

Status: PENDING VERIFICATION

## 1. Purpose

This document defines the exact integration contract between:

KKK OS Database
→ KKK Engine
→ Canonical Merge Jobs
→ Photoshop Export Adapter
→ Google Sheet / CSV
→ Photoshop Auto Merge

The database remains the application source of truth.

Google Sheet / CSV is a downstream Photoshop Auto Merge
integration/output layer only.

This contract must NOT be marked as VERIFIED or LOCKED until
the current working Photoshop Auto Merge files and process
have been inspected directly.

---

## 2. Source of Truth

Primary business/system authority:

MASTER BLUEPRINT & SYSTEM SPECIFICATION

Technical Photoshop contract authority:

The current real working Photoshop Auto Merge implementation,
including:

- current working Sheet / CSV;
- current PSD template;
- current script / action / plugin;
- actual designer execution process;
- successful output samples.

If an older spreadsheet, prototype, chat or assumption conflicts
with the real current working Auto Merge integration, the
verified working integration must be documented before the
export implementation is locked.

---

## 3. Verification Status

Current status:

PENDING VERIFICATION

Do not implement final production headers or formatting rules
until the following have been inspected.

### Required source files

- [ ] Real working Google Sheet / CSV
- [ ] Real working PSD
- [ ] Photoshop script / action / plugin, if used
- [ ] Successful output sample
- [ ] 2-package example, if available
- [ ] Designer workflow notes or screenshots

---

## 4. Known KKK OS Internal Architecture

Confirmed customer data is stored in the KKK OS database.

After customer confirmation:

DETAILS_CONFIRMED
→ KKK Engine
→ Canonical Merge Job(s)

### 1-package

1 business order
→ 1 package side
→ 1 merge job

Recommended internal job identity:

ORDERID-L

or

ORDERID-P

depending on the selected side.

### 2-package

1 business order
→ 2 package sides
→ 2 independent merge jobs

Recommended internal identities:

ORDERID-L
ORDERID-P

The two jobs must never overwrite each other.

---

## 5. Canonical Internal Merge Concepts

KKK Engine currently exposes internal canonical concepts such as:

- job_id
- order_id
- side
- namapengantin1
- namapengantin2
- namabapa
- namaibu
- tarikh_iso
- masa_raw
- alamat
- design_code

These are internal canonical concepts.

They are NOT confirmation of the final Photoshop Sheet / CSV
headers.

---

## 6. Exact Input File Contract

Status: NOT YET VERIFIED

### File type

Pending verification.

Possible examples:

- CSV
- XLSX
- Google Sheet export
- other

### Encoding

Pending verification.

Examples to verify:

- UTF-8
- UTF-8 BOM
- ANSI / Windows encoding

### Delimiter

Pending verification.

Examples to verify:

- comma
- semicolon
- tab

### Line ending

Pending verification.

### Sheet / Tab name

Pending verification.

---

## 7. Exact Column Headers

Status: NOT YET VERIFIED

The final column names must match the real working Auto Merge
input exactly.

| Position | Exact Header | Required | Meaning | KKK Source |
|---|---|---|---|---|
| TBD | TBD | TBD | TBD | TBD |

Do not rename, normalize or improve existing Photoshop headers
without verification.

For example, the following are considered different contracts:

- namabapa
- nama_bapa
- NamaBapa
- NAMA BAPA

Exact spelling and casing must be preserved if required by the
working integration.

---

## 8. Photoshop Variables / Layers

Status: NOT YET VERIFIED

The KKK Master Blueprint prefers generic Photoshop fields such as:

- namabapa
- namaibu
- namapengantin1
- namapengantin2
- tarikh
- masa
- alamat

However, the exact production PSD variable/layer names must be
verified from the current working PSD.

| Photoshop Variable / Layer | Meaning | Export Column | Verified |
|---|---|---|---|
| TBD | TBD | TBD | No |

Do not introduce standard side-specific layers such as:

- namabapa_lelaki
- namabapa_perempuan

unless a genuine technical discovery requires escalation.

KKK Engine should perform the side mapping before export.

---

## 9. Mapping Rules

Status: PENDING VERIFICATION

### Couple

Internal source:

- groom_name
- bride_name
- groom_abbreviation
- bride_abbreviation

Final Photoshop mapping:

Pending verification.

### Parents

For each merge job, KKK Engine maps the parents belonging to
that package side into generic parent fields.

Conceptual example:

LELAKI job
→ Lelaki parent records
→ generic namabapa / namaibu

PEREMPUAN job
→ Perempuan parent records
→ generic namabapa / namaibu

Final export headers remain pending verification.

### Event

Each package side has its own event data.

Fields may include:

- date
- Hijri date
- meal time
- bersanding time
- venue
- address
- Google Maps URL
- contact persons

Final Photoshop fields remain pending verification.

---

## 10. Date Formatting

Status: NOT YET VERIFIED

KKK OS stores structured date values internally.

Example internal value:

2026-12-20

The final Photoshop display format must be verified.

Possible formats must NOT be assumed.

Examples only:

- 20/12/2026
- 20 DISEMBER 2026
- 20 Disember 2026

Final required format:

TBD

---

## 11. Time Formatting

Status: NOT YET VERIFIED

KKK OS stores time consistently.

Example internal value:

12:00

The final Photoshop display format must be verified.

Examples only:

- 12:00
- 12.00 PM
- 12.00 TENGAH HARI

Final required format:

TBD

---

## 12. Address Formatting

Status: NOT YET VERIFIED

KKK OS performs safe address cleanup while preserving meaningful
structure.

Need to verify:

- single-line vs multiline;
- line-break character;
- maximum lines;
- punctuation behavior;
- uppercase/lowercase requirements;
- handling of postcode/state;
- CSV quoting behavior.

Final required format:

TBD

---

## 13. Name Formatting

KKK OS must not blindly Title Case Malaysian names.

Need to preserve valid formats such as:

- bin / binti
- A/L
- A/P
- apostrophes
- hyphens
- initials
- supported non-standard capitalization

Photoshop export must use the already approved cleaned customer
representation unless the verified Photoshop contract requires
additional display formatting.

---

## 14. Contact Persons

Current KKK V1 rule:

Maximum and required operational contact slots remain 3 per event.

Need to verify whether contact persons are:

- included in Photoshop merge;
- excluded from Photoshop merge;
- partially included.

Final mapping:

TBD

---

## 15. Second Couple

Optional second-couple fields remain supported by KKK OS.

V1 rule:

Second-couple data is NOT part of standard Photoshop automation.

Designer handles this rare case manually.

Therefore no standard Photoshop merge mapping should be added for
second-couple fields unless Project Owner approves a scope change.

---

## 16. File Naming Contract

Status: NOT YET VERIFIED

Need to verify actual downstream file naming for:

- imported data file;
- PSD output;
- JPG output;
- PDF output;
- folders;
- versioned corrections.

### Internal KKK job IDs

1-package Lelaki:

ORDERID-L

1-package Perempuan:

ORDERID-P

2-package:

ORDERID-L
ORDERID-P

Need to verify whether these job IDs should also become downstream
filenames.

---

## 17. Two-Package Collision Protection

Mandatory behavior:

One business order with two packages must generate two independent
Photoshop merge jobs.

Example:

KKK-260909-0007-L
KKK-260909-0007-P

Need to verify:

- unique output filename;
- unique row;
- unique PSD output;
- unique preview;
- unique designer job reference.

No Lelaki/Perempuan output may overwrite the other.

---

## 18. Photoshop Execution Process

Status: NOT YET VERIFIED

Document the actual designer workflow exactly.

Example structure:

1. Designer receives ready job.
2. Designer obtains merge data.
3. Designer opens PSD.
4. Designer imports data / runs script.
5. Photoshop generates artwork.
6. Output file is saved using verified naming.
7. Artwork is attached to the KKK workflow.

Actual current procedure:

TBD

---

## 19. Error Handling

Need to verify actual Photoshop-side behavior when:

- required value is blank;
- CSV column missing;
- invalid date/time;
- multiline text too long;
- filename already exists;
- invalid encoding;
- image/path missing;
- script fails.

KKK OS should block or clearly surface export errors where
practical.

---

## 20. Verification Test Matrix

### T09A — 1-Package

Confirmed KKK order
→ one merge job
→ one verified export row
→ successful Photoshop merge

Status: PENDING

### T09B — 2-Package

Confirmed KKK order
→ Lelaki merge job
→ Perempuan merge job
→ two verified export rows
→ two successful Photoshop outputs
→ no overwrite

Status: PENDING

### T09C — Exact Headers

Generated headers exactly match working Auto Merge contract.

Status: PENDING

### T09D — Date Formatting

Generated date matches actual Photoshop requirement.

Status: PENDING

### T09E — Time Formatting

Generated time matches actual Photoshop requirement.

Status: PENDING

### T09F — Address / Line Breaks

Multiline address survives export/import correctly.

Status: PENDING

### T09G — Malaysian Names / Encoding

Names with legitimate Malaysian formats survive without corruption.

Status: PENDING

### T09H — Collision Protection

Lelaki and Perempuan output files do not overwrite each other.

Status: PENDING

---

## 21. Contract Approval State

Current state:

PENDING VERIFICATION

The contract may only move to:

VERIFIED

after the KKK Systems Team has directly inspected and tested the
current working Auto Merge implementation.

The contract may only move to:

LOCKED FOR V1

after:

- exact headers are documented;
- exact formatting is documented;
- 1-package test succeeds;
- 2-package test succeeds;
- output naming is collision-safe;
- relevant technical limitations are documented.

Any discovery that changes KKK business rules, V1 scope, customer
experience, meaningful cost or operational policy must be
escalated to the Project Owner.