# KKK OS V1 — Stage 9B Photopea v0.5 — 2-Package Acceptance

## Fixture audit

The supplied KKK OS CSV contains exactly two rows.

### LELAKI
- qtykad: 500
- theme/design: Songket / CKS-218
- father: Ali Bin Abu
- mother: Aminah Binti Minah
- day: ISNIN
- date: 28 SEPTEMBER 2026
- contacts: Iqbal / Farhan / Aliya
- address: Jalan Kenanga Blok 16...
- QR: real Google Maps URL ending `EqxKRuBntB8YNLjd9`

### PEREMPUAN
- qtykad: 500
- theme/design: Songket / CKS-218
- father: John Carter
- mother: Miya Layla
- day: RABU
- date: 30 SEPTEMBER 2026
- contacts: Halimah / Hajah / Alif
- address: 85000, Johor Darul Ta'zim
- QR: real Google Maps URL ending `fk1XEBLCnH989qSx6`

## Output names

The script exports each row before applying the next one:

- KKK-260909-0007_LELAKI_KAD_DEPAN.jpg
- KKK-260909-0007_LELAKI_KAD_DEPAN.psd
- KKK-260909-0007_LELAKI_KAD_BELAKANG.jpg
- KKK-260909-0007_LELAKI_KAD_BELAKANG.psd

- KKK-260909-0007_PEREMPUAN_KAD_DEPAN.jpg
- KKK-260909-0007_PEREMPUAN_KAD_DEPAN.psd
- KKK-260909-0007_PEREMPUAN_KAD_BELAKANG.jpg
- KKK-260909-0007_PEREMPUAN_KAD_BELAKANG.psd

## Test procedure

1. Close previously modified templates.
2. Open fresh KAD DEPAN and KAD BELAKANG test copies for CKS-218.
3. Photopea > File > Script.
4. Paste the complete `KKK_Photopea_AutoMerge_v0_5_2PACKAGE.js`.
5. Run.
6. Allow QR URLs to load.
7. After script completion, Photopea should provide generated output files (typically as a ZIP).
8. Upload that output ZIP for audit.

## PASS criteria

- exactly 8 output files;
- both rows use qtykad 500;
- LELAKI and PEREMPUAN output filenames are distinct;
- LELAKI parents/event/contacts/address are correct;
- PEREMPUAN parents/event/contacts/address are correct;
- LELAKI QR scans to the LELAKI Maps URL;
- PEREMPUAN QR scans to the PEREMPUAN Maps URL;
- PSDs remain layered;
- no side overwrites the other.
