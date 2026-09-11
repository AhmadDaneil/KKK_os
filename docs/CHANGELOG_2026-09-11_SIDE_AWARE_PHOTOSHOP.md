# CHANGELOG — 2026-09-11

## Change
Production Photoshop Auto Merge becomes side-aware using the existing `majlis` column.

## Approved rule
- LELAKI: groom above bride.
- PEREMPUAN: bride above groom.
- Both KAD DEPAN and KAD BELAKANG.

## Additional technical fix
`majlis` is included in folder/output/QR names to prevent two-package overwrite when both sides use the same template.

## Data contract impact
- 28-column CSV shape: unchanged.
- `majlis`: changes from compatibility-only to consumed + required.
- Database semantic fields: unchanged.
- PSD generic layer schema: unchanged.

## Required regression
- 1-package / LELAKI
- 1-package / PEREMPUAN
- 2-package / both sides same design
- QR per side
- JPEG/PSD outputs
- no overwrite
