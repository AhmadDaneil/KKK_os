<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $filename }} · KingKadKahwin</title>
    <style>
        html, body, object { width: 100%; height: 100%; margin: 0; }
        body { overflow: hidden; background: #eef3f0; }
        .watermark { position: fixed; z-index: 2; inset: 0; pointer-events: none; overflow: hidden; }
        .watermark span { position: absolute; top: 50%; left: 50%; width: 90%; color: rgba(255, 255, 255, .5); font: 800 clamp(1.1rem, 3vw, 3.2rem)/1 Arial, sans-serif; letter-spacing: .04em; text-align: center; text-shadow: 2px 2px 4px rgba(0, 0, 0, .34); transform: translate(-50%, -50%) rotate(-30deg); white-space: nowrap; }
    </style>
</head>
<body>
    <object data="data:application/pdf;base64,{{ $pdf }}" type="application/pdf" aria-label="Artwork preview"></object>
    <div class="watermark" aria-hidden="true">
        <span>KING KAD KAHWIN · PREVIEW</span>
    </div>
</body>
</html>
