# KKK OS V1 — Stage 9B Photopea v0.2 Layer Audit

## Tujuan

Audit ini diperlukan sebelum Complete Layer Mapping + Text Auto-Fit dibina.

Ia **tidak mengubah PSD**. Script hanya membaca:
- semua dokumen Photopea yang sedang terbuka;
- hierarchy layer;
- exact layer name;
- layer kind;
- text contents bagi text layer yang boleh dibaca.

## Sebelum Run

Buka **test copy** template sebenar `Songket / CKS-218`:

- `KAD DEPAN`
- `KAD BELAKANG`

Pastikan kedua-duanya masih terbuka serentak dalam Photopea.

## Cara Run

1. Photopea → `File > Script`
2. Buka:
   `photopea/KKK_Photopea_Layer_Audit_v0_2.js`
3. Copy semua script.
4. Paste ke Photopea.
5. Klik `Run`.

Script akan menunjukkan report dalam beberapa `prompt` jika report panjang.

Untuk setiap prompt:
1. Klik dalam kotak text.
2. `Ctrl+A`
3. `Ctrl+C`
4. Paste semua chunk ke ChatGPT.

Jangan edit layer name atau placeholder sebelum audit selesai.

## Output yang diperlukan

Contoh:

```text
DOCUMENT 1
NAME: KAD DEPAN
...
- PATH: ... | NAME: singkatanlelaki | KIND: ... | TEXT: ...

DOCUMENT 2
NAME: KAD BELAKANG
...
- PATH: ... | NAME: namaayah | KIND: ... | TEXT: NAMA BAPA
```

Selepas exact audit diterima, v0.2 mapping akan dibina berdasarkan struktur PSD sebenar — bukan berdasarkan tekaan visual.

## Selepas Audit

Target implementation seterusnya:

1. exact CSV field → actual PSD layer mapping;
2. alias only where required by real template;
3. safe text replacement;
4. controlled text auto-fit;
5. per-field PASS / MISSING / ERROR report;
6. retain existing QR Smart Object flow;
7. visual acceptance against CKS-218.

Auto-export dan 2-package batch processing masih ditangguhkan sehingga mapping + fit lulus.
