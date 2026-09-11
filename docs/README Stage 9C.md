# KKK OS V1 — Production Photoshop JSX Side-Aware Patch

## Approved business/display rule
Approved by Project Owner on 2026-09-11:

- Pihak LELAKI: groom above bride.
- Pihak PEREMPUAN: bride above groom.
- Applies to KAD DEPAN and KAD BELAKANG.

## Technical implementation
The database/CSV semantics remain unchanged:
- `namapengantinlelaki` remains groom data.
- `namapengantinperempuan` remains bride data.
- `singkatanlelaki` remains groom abbreviation.
- `singkatanperempuan` remains bride abbreviation.

`majlis` is now actively consumed by the Photoshop JSX.

For template presentation:
- LELAKI: top <- groom, bottom <- bride.
- PEREMPUAN: top <- bride, bottom <- groom.

The current PSD layer names are retained. No side-specific PSD layer schema is introduced.

## Critical collision fix
The old JSX used the same customer folder/output basename for two rows that shared:
- order ID,
- couple,
- card quantity,
- design/template.

That could overwrite one side of a 2-package order.

This patch includes `majlis` in:
- customer folder name,
- QR filename,
- JPEG/PSD output basename.

Therefore LELAKI and PEREMPUAN outputs are collision-safe even when both use the same design code.

## Files
- `photoshop/auto_kad_full_qr_patched_v11_side_aware.jsx`
- `laravel/app/Support/Photoshop/PhotoshopAutoMergeContractV1.php`
- `laravel/tests/Feature/Merge/PhotoshopSideAwareContractTest.php`

## Installation
1. Back up the existing production JSX.
2. Replace the current JSX with `auto_kad_full_qr_patched_v11_side_aware.jsx` only in the controlled test environment first.
3. In Laravel, replace/update `PhotoshopAutoMergeContractV1.php`.
4. Add `PhotoshopSideAwareContractTest.php`.
5. Run the targeted Laravel tests.
6. Perform Photoshop/Photopea regression with 1-package LELAKI, 1-package PEREMPUAN, and 2-package CSV before production rollout.

Do not deploy straight to live designer workflow before regression passes.
