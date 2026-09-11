# Change Record — 2026-09-11

## Change
Approved side-aware bride/groom visual ordering.

## Business rule
- LELAKI: groom above bride.
- PEREMPUAN: bride above groom.
- Applies to KAD DEPAN and KAD BELAKANG.

## Reason
Project Owner reviewed Stage 9B two-package output and approved the corrected visual ordering for Pihak Perempuan.

## Impact
- Photopea acceptance adapter presentation mapping.
- Production Photoshop adapter/JSX must implement the same side-aware presentation rule after acceptance verification.
- Database and CSV semantic fields do not change.
- No side-specific PSD layers are introduced.

## Required regression
- 1-package LELAKI
- 1-package PEREMPUAN
- 2-package LELAKI + PEREMPUAN
- no overwrite
