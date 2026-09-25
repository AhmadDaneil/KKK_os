<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $filename }} · KingKadKahwin</title>
    <style>
        html, body, object { width: 100%; height: 100%; margin: 0; }
        body { overflow: hidden; background: #eef3f0; }
        .watermark { position: fixed; z-index: 2; inset: 0; display: grid; place-items: center; pointer-events: none; overflow: hidden; }
        .watermark span { color: rgba(23, 75, 55, .24); font: 800 clamp(2rem, 8vw, 7rem)/1 Arial, sans-serif; transform: rotate(-28deg); white-space: nowrap; }
    </style>
</head>
<body>
    <object data="data:application/pdf;base64,{{ $pdf }}" type="application/pdf" aria-label="Artwork preview"></object>
    <div class="watermark" aria-hidden="true"><span>King Kad Kahwin</span></div>
</body>
</html>
