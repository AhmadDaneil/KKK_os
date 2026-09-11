# KKK OS V1 — Stage 9B Photopea Adapter v0.4

## Purpose
Validate automatic JPG + PSD generation in Photopea after the already-passing mapping / QR workflow.

## Expected generated files
- KKK-260909-0006_KAD_DEPAN.jpg
- KKK-260909-0006_KAD_DEPAN.psd
- KKK-260909-0006_KAD_BELAKANG.jpg
- KKK-260909-0006_KAD_BELAKANG.psd

Photopea documentation states that files created in its script filesystem are offered as a ZIP after the script finishes.

## Test
1. Close modified PSDs.
2. Open fresh test copies of KAD DEPAN and KAD BELAKANG.
3. File > Script.
4. Paste the complete `KKK_Photopea_AutoMerge_Adapter_v0_4_AUTO_EXPORT.js`.
5. Run.
6. Observe:
   - final report;
   - whether Photopea offers / downloads a ZIP;
   - contents of the ZIP.

## Acceptance
PASS only if:
- both card sides still map correctly;
- QR remains correct;
- 2 JPG files exist;
- 2 PSD files exist;
- PSD files remain layered/editable;
- source master copies were not overwritten.

Folder hierarchy and 2-package processing are intentionally not included yet.
