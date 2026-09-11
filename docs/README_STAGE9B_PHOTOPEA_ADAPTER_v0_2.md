# KKK OS V1 — Stage 9B Photopea Adapter v0.2

Target: **Songket / CKS-218**

Fixture: `KKK-260909-0006_READY_TO_MERGE.csv`

## Scope

v0.2 uses the exact audited CKS-218 layer names.

### KAD DEPAN
- singkatanlelaki
- singkatanperempuan
- hari
- tarikh

### KAD BELAKANG
- namaayah
- namaibu
- namapengantinlelaki
- namapengantinperempuan
- hari
- tarikhhari
- bulan
- bulanislam
- masajamuanmakan
- masabersanding
- nama1
- notel1
- nama2
- notel2
- nama3
- notel3
- alamat
- qrlocation

The adapter does not touch static/template layers such as TEMPLATE TAK UBAH, Design Here, FRAME, Background Letak Sini, Layer 0 / Layer 1, or Group 7.

## Controlled text auto-fit

For each dynamic text layer, the script:
1. records original bounds and font size;
2. replaces text;
3. checks whether the new bounds exceed the original bounds;
4. shrinks font size gradually when needed;
5. stops once it fits or reaches a conservative lower limit;
6. reports the result.

## Test procedure

Use fresh TEST COPIES of the real CKS-218 PSDs.

Open both at the same time in Photopea:
- KAD DEPAN
- KAD BELAKANG

Then:
1. File > Script
2. Paste `photopea/KKK_Photopea_AutoMerge_Adapter_v0_2.js`
3. Click Run.

The report should show PASS / MISSING / ERROR / AUTO-FIT / QR UPDATED counts.

## Mandatory visual verification

Check both sides carefully. Pay special attention to:
- all mapped fields changed from placeholders;
- font sizes remain visually acceptable;
- no overlap or clipping;
- alignment remains correct;
- static layers are untouched;
- QR remains within the intended area.

## QR limitation

The current fixture still uses a placeholder qrlink, so QR generation and placement can be tested but scan-to-real-location acceptance remains pending.

## Not included yet

- direct local CSV parsing in Photopea;
- automatic template opening from MASTER;
- automatic JPG / PSD batch export;
- folder naming;
- 2-package batch processing.

These remain deferred until v0.2 visual mapping and auto-fit pass.
