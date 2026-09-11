# KKK OS V1 — Stage 9B Photopea Adapter v0.2.2 STANDALONE

This fixes the packaging error in v0.2.1.

v0.2.1 contained only a replacement `updateText()` function. Running that file by itself only defined a function and did not execute the adapter.

v0.2.2 is a complete standalone script.

## Test

1. Close modified PSDs.
2. Re-open fresh test copies of:
   - KAD DEPAN
   - KAD BELAKANG
3. Photopea > File > Script.
4. Paste the entire contents of:
   `photopea/KKK_Photopea_AutoMerge_Adapter_v0_2_2_STANDALONE.js`
5. Click Run.

This build deliberately does NOT auto-fit text. It first verifies reliable full-field mapping on both documents.

Expected:
- KAD DEPAN updates.
- KAD BELAKANG updates.
- QR updates.
- Popup report has per-field PASS / MISSING / ERROR.

Do not use the old PATCH_v0_2_1_updateText.js by itself.
