# KKK OS V1 — Stage 9B Photopea Adapter v0.2.1 Patch

## Root cause found

The direct-write diagnostic proved:

- KAD BELAKANG is found;
- `namaayah` exists;
- it is a Kind 2 text layer;
- direct assignment works when `app.activeDocument = back` is set.

Therefore the v0.2 failure was not caused by CSV data, field mapping or layer names.

The Photopea-specific issue is document context: the target PSD must be made active before layer writes are performed.

## Fix

Inside `updateText()`:

```javascript
app.activeDocument = doc;
doc.activeLayer = layer;
```

is now set before text replacement / auto-fit.

## Test procedure

1. Re-open FRESH TEST COPIES of:
   - KAD DEPAN
   - KAD BELAKANG
2. Apply the v0.2.1 patch to the v0.2 script.
3. Run the adapter again.
4. Verify both sides change.
5. Do not judge auto-fit yet if mapping itself still fails; report the popup and screenshots.

Expected:
- KAD DEPAN fields update.
- KAD BELAKANG fields update.
- QR updates.
