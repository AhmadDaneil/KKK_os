/* V35 - V33 portrait handling plus two-package Cek Hantaran mockup.
   The cleaned cek_hantaran_master_clean.png must be beside this JSX. */
/* auto_kad_full_qr_patched.jsx
   Full pipeline: Auto Kad Full (patched V32 - Arrow visible image + compatible transparent VIP PNG)
   - Ask operator to choose ROOT folder
   - MASTER template path = <ROOT>/MASTER
   - OUTPUT path = <ROOT>/OUTPUT
   - Choose CSV (Ready to merge)
   - For each CSV row:
       • find templates in MASTER/<Tema/<DesignCode>/*.psd
       • open each template, update text layers (namaayah, namaibu, alamat, HARI/TARIKH/BULAN)
       • generate QR PNG from qrlink and save to customer/QR
       • REPLACE Smart Object "qrlocation" dengan QR PNG (auto-convert kalau bukan SO) + AUTO-FIT
       • export VIP CARD as transparent PNG; other items as JPEG; all items as PSD
   - Write log to OUTPUT/_customer_log.txt + mirror log dalam folder customer
   Notes:
     - Legacy ExtendScript friendly (tiada .trim(), .filter()).
     - CSV parser handle quoted commas/newlines.
     - Guna PowerShell untuk download QR (Windows).
*/

#target photoshop
var KKK_ORIGINAL_DIALOGS = app.displayDialogs;
var KKK_ORIGINAL_UNITS = app.preferences.rulerUnits;
app.displayDialogs = DialogModes.NO;

// ------------------ Utilities ------------------
function s(x){ return String(x == null ? "" : x).replace(/\uFEFF/g, ""); } // strip BOM
function t(x){ return s(x).replace(/^\s+|\s+$/g, ""); } // manual trim
function pad2(n){ return (n<10?"0":"")+n; }
function nowStamp(){ var d=new Date(); return d.getFullYear()+pad2(d.getMonth()+1)+pad2(d.getDate())+"_"+pad2(d.getHours())+pad2(d.getMinutes()); }
function safeName(x){ return s(x).replace(/[\\\/\:\*\?"<>\|]/g, "_").replace(/\s+/g," ").replace(/^\s+|\s+$/g,""); }
function ensureFolderObj(path){ var f = new Folder(path); if(!f.exists) f.create(); return f; }
function logAppend(path, line){ try{ var f = new File(path); f.open("a"); f.write(line + "\r\n"); f.close(); }catch(e){} }
function logBoth(globalPath, custPath, line){
    logAppend(globalPath, line);
    if (custPath && custPath !== "") logAppend(custPath, line);
}

// ------------------ CSV parse (quote-aware) ------------------
function parseCSV(text){
    if(text == null) return [];
    text = s(text);
    text = text.replace(/\r\n/g, "\n").replace(/\r/g, "\n");
    var rows = [], row = [], cell = "", inQ = false;
    for(var i=0;i<text.length;i++){
        var ch = text.charAt(i);
        if(inQ){
            if(ch === '"'){
                if(i+1 < text.length && text.charAt(i+1) === '"'){ cell += '"'; i++; }
                else { inQ = false; }
            } else { cell += ch; }
        } else {
            if(ch === '"'){ inQ = true; }
            else if(ch === ','){ row.push(cell); cell = ""; }
            else if(ch === '\n'){ row.push(cell); cell = ""; rows.push(row); row = []; }
            else { cell += ch; }
        }
    }
    if(cell !== "" || row.length > 0){ row.push(cell); rows.push(row); }
    var cleaned = [];
    for(var r=0;r<rows.length;r++){
        var any=false;
        for(var c=0;c<rows[r].length;c++){ if(t(rows[r][c]) !== "") { any=true; break; } }
        if(any) cleaned.push(rows[r]);
    }
    return cleaned;
}
function findHeaderIndex(headerArr, name){
    var target = t(name).toLowerCase();
    for(var i=0;i<headerArr.length;i++){
        var cell = t(headerArr[i]).toLowerCase();
        if(cell === target) return i;
    }
    return -1;
}

// ------------------ QR generation ------------------
function generateQRtoPath(link, outPath){
    if(!link || t(link) === "") return false;
    try{
        var qrApi = "https://api.qrserver.com/v1/create-qr-code/?size=1200x1200&data=" + encodeURIComponent(link);
        var outEsc = outPath.replace(/\\/g,"\\\\");
        var cmd = 'powershell -NoProfile -NonInteractive -Command "try{ (New-Object System.Net.WebClient).DownloadFile(\''+qrApi.replace(/'/g,"''")+'\', \''+outEsc.replace(/'/g,"''")+'\'); }catch{ exit 1 }"';
        app.system(cmd);
        var f = new File(outPath);
        return f.exists && f.length > 50;
    }catch(e){
        return false;
    }
}

// ------------------ Photoshop helpers ------------------
function openPSDQuiet(path){
    try{ var f = new File(path); if(!f.exists) return null; return app.open(f); }catch(e){ return null; }
}
function closeDocNoSave(doc){ try{ doc.close(SaveOptions.DONOTSAVECHANGES); }catch(e){} }
var LAST_SAVE_ERROR = "";

function saveJPG(doc, outPath, quality){
    LAST_SAVE_ERROR = "";
    try{
        app.activeDocument = doc;
        var outFile = new File(outPath);
        if(outFile.exists){
            try{ outFile.remove(); }catch(removeErr){}
        }

        var opts = new JPEGSaveOptions();
        opts.quality = quality;
        doc.saveAs(outFile, opts, true, Extension.LOWERCASE);

        if(!outFile.exists || outFile.length <= 0){
            LAST_SAVE_ERROR = "JPEG file tidak wujud / saiz 0 selepas saveAs";
            return false;
        }
        return true;
    }catch(e){
        LAST_SAVE_ERROR = s(e);
        return false;
    }
}

function savePNG(doc, outPath){
    LAST_SAVE_ERROR = "";
    var pngDoc = null;
    try{
        var outFile = new File(outPath);
        // Save for Web replaces spaces in the output basename with hyphens.
        // Export under a stable name, then copy to the exact requested name.
        var tempFile = new File(outFile.parent.fsName + "/_kkk_vip_export_tmp.png");
        if(tempFile.exists && !tempFile.remove()) throw new Error("Gagal mengganti PNG sementara.");

        // Production PSD templates can be CMYK. Photoshop cannot save CMYK
        // directly through PNGSaveOptions, so work on a temporary RGB duplicate.
        // Save for Web PNG-24 is supported by older Photoshop versions and keeps
        // the transparent pixels required by VIP Card.
        app.activeDocument = doc;
        pngDoc = doc.duplicate();
        app.activeDocument = pngDoc;
        try{
            if(pngDoc.mode != DocumentMode.RGB) pngDoc.changeMode(ChangeMode.RGB);
        }catch(modeErr){}
        try{ pngDoc.bitsPerChannel = BitsPerChannelType.EIGHT; }catch(bitsErr){}

        var opts = new ExportOptionsSaveForWeb();
        opts.format = SaveDocumentType.PNG;
        opts.PNG8 = false;
        opts.transparency = true;
        opts.interlaced = false;
        try{ opts.includeProfile = false; }catch(profileErr){}
        pngDoc.exportDocument(tempFile, ExportType.SAVEFORWEB, opts);
        closeDocNoSave(pngDoc);
        pngDoc = null;
        app.activeDocument = doc;

        if(!tempFile.exists || tempFile.length <= 0){
            LAST_SAVE_ERROR = "PNG file tidak wujud / saiz 0 selepas Save for Web";
            return false;
        }
        if(outFile.exists && !outFile.remove()) throw new Error("Gagal mengganti PNG lama.");
        if(!tempFile.copy(outFile.fsName)) throw new Error("Gagal menyalin PNG kepada nama output sebenar.");
        try{ tempFile.remove(); }catch(cleanErr){}
        if(!outFile.exists || outFile.length <= 0) throw new Error("PNG output kosong.");
        return true;
    }catch(e){
        if(pngDoc) closeDocNoSave(pngDoc);
        try{ app.activeDocument = doc; }catch(activeErr){}
        LAST_SAVE_ERROR = s(e);
        return false;
    }
}

function savePSDdoc(doc, outPath){
    LAST_SAVE_ERROR = "";
    try{
        app.activeDocument = doc;
        var outFile = new File(outPath);
        if(outFile.exists){
            try{ outFile.remove(); }catch(removeErr){}
        }

        var opts = new PhotoshopSaveOptions();
        opts.layers = true;
        doc.saveAs(outFile, opts, true);

        if(!outFile.exists || outFile.length <= 0){
            LAST_SAVE_ERROR = "PSD file tidak wujud / saiz 0 selepas saveAs";
            return false;
        }
        return true;
    }catch(e){
        LAST_SAVE_ERROR = s(e);
        return false;
    }
}

function addCekHantaranText(doc, layerName, value, fontName, sizePt, centerX, baselineY, maxWidth){
    var layer = doc.artLayers.add();
    layer.name = layerName;
    layer.kind = LayerKind.TEXT;
    var item = layer.textItem;
    item.contents = s(value);
    try{ item.font = fontName; }
    catch(fontError){ try{ item.font = "TimesNewRomanPSMT"; }catch(fallbackError){} }
    item.size = UnitValue(sizePt, "pt");
    item.justification = Justification.CENTER;
    var white = new SolidColor();
    white.rgb.red = 255;
    white.rgb.green = 255;
    white.rgb.blue = 255;
    item.color = white;
    item.position = [UnitValue(centerX, "px"), UnitValue(baselineY, "px")];
    for(var attempt = 0; attempt < 16; attempt++){
        var width = layer.bounds[2].as("px") - layer.bounds[0].as("px");
        if(width <= maxWidth || sizePt <= 7) break;
        sizePt *= 0.92;
        item.size = UnitValue(sizePt, "pt");
    }
    return layer;
}

function exportCekHantaranRow(root, outputId, amount, bride, groom, day, date, logPath){
    if(amount === "" || bride === "" || groom === "" || day === "" || date === ""){
        logAppend(logPath, "[CEK HANTARAN FAIL] " + outputId + " | nama, hari, tarikh atau jumlah_hantaran kosong");
        return 0;
    }
    var scriptFile = new File($.fileName);
    var background = new File(scriptFile.parent.fsName + "/cek_hantaran_master_clean.png");
    if(!background.exists){
        logAppend(logPath, "[CEK HANTARAN FAIL] " + outputId + " | background tiada: " + background.fsName);
        return 0;
    }

    var customerFolder = ensureFolderObj(root.fsName + "/" + safeName(outputId + " Cek Hantaran"));
    var jpegFolder = ensureFolderObj(customerFolder.fsName + "/Export JPEG");
    var psdFolder = ensureFolderObj(customerFolder.fsName + "/PSD");
    var customerLog = customerFolder.fsName + "/_customer_log.txt";
    var doc = null;
    var success = 0;
    try{
        doc = app.open(background);
        if(!doc) throw "background gagal dibuka";
        app.activeDocument = doc;
        addCekHantaranText(doc, "namapengantinperempuan", bride, "Baskervville-Regular", 14.7, 1055, 548, 1400);
        addCekHantaranText(doc, "jumlah_hantaran", amount, "Baskerville-SemiBold", 23.1, 1420, 725, 540);
        addCekHantaranText(doc, "hari", day, "Baskerville-SemiBold", 12.1, 742, 845, 360);
        addCekHantaranText(doc, "tarikh", date, "Baskerville-SemiBold", 14.6, 1202, 845, 620);
        addCekHantaranText(doc, "namapengantinlelaki", groom, "Baskervville-Regular", 13.4, 1055, 950, 1320);

        var jpegPath = jpegFolder.fsName + "/" + safeName(outputId) + ".jpg";
        var psdPath = psdFolder.fsName + "/" + safeName(outputId) + ".psd";
        if(saveJPG(doc, jpegPath, 12)){
            success++;
            logBoth(logPath, customerLog, "[CEK HANTARAN] JPEG -> " + jpegPath);
        }else{
            logBoth(logPath, customerLog, "[CEK HANTARAN FAIL] JPEG -> " + LAST_SAVE_ERROR);
        }
        if(savePSDdoc(doc, psdPath)){
            success++;
            logBoth(logPath, customerLog, "[CEK HANTARAN] PSD -> " + psdPath);
        }else{
            logBoth(logPath, customerLog, "[CEK HANTARAN FAIL] PSD -> " + LAST_SAVE_ERROR);
        }
    }catch(error){
        logBoth(logPath, customerLog, "[CEK HANTARAN ERROR] " + outputId + " | " + error);
    }finally{
        if(doc) closeDocNoSave(doc);
    }
    return success;
}
function setTextIfExistsInDoc(doc, layerName, value){
    try{
        var layer = doc.artLayers.getByName(layerName);
        if(layer && layer.kind == LayerKind.TEXT){
            layer.textItem.contents = s(value);
            return true;
        }
    }catch(e){}
    try{
        function walkLayers(container){
            for(var i=0;i<container.layers.length;i++){
                var L = container.layers[i];
                if(L.name === layerName && L.kind == LayerKind.TEXT){
                    L.textItem.contents = s(value);
                    return true;
                }
                if(L.typename === "LayerSet"){
                    var ok = walkLayers(L);
                    if(ok) return true;
                }
            }
            return false;
        }
        return walkLayers(doc);
    }catch(e){}
    return false;
}

function getTextLayerAnchorInfo(doc, layerName){
    try{
        var layer = findLayerByNames(doc, [layerName]);
        if(!layer || layer.kind != LayerKind.TEXT) return null;
        var b = _boundsPx(layer);
        return {
            layerName: layerName,
            cx: b.cx,
            cy: b.cy,
            w: b.w,
            h: b.h
        };
    }catch(e){ return null; }
}

function centerTextLayerToTargetCenterX(doc, layerName, targetCenterX){
    try{
        var layer = findLayerByNames(doc, [layerName]);
        if(!layer || layer.kind != LayerKind.TEXT) return false;
        var b = _boundsPx(layer);
        doc.activeLayer = layer;
        _translate(targetCenterX - b.cx, 0);
        return true;
    }catch(e){ return false; }
}

function shouldCenterShortName(value, templateAnchor, currentLayer){
    try{
        var clean = t(value).replace(/\s+/g, "");
        if(clean === "") return false;

        var currentBox = _boundsPx(currentLayer);
        if(clean.length <= 8) return true;

        if(templateAnchor && templateAnchor.w > 0){
            if(currentBox.w <= (templateAnchor.w * 0.78)) return true;
        }

        return false;
    }catch(e){ return false; }
}

function centerShortNamesForPortraitBannerBanting(doc, topValue, bottomValue, topAnchor, bottomAnchor){
    var result = { topCentered:false, bottomCentered:false };
    try{
        var topLayer = findLayerByNames(doc, ["singkatanlelaki"]);
        var bottomLayer = findLayerByNames(doc, ["singkatanperempuan"]);

        if(topLayer && topLayer.kind == LayerKind.TEXT && topAnchor){
            if(shouldCenterShortName(topValue, topAnchor, topLayer)){
                result.topCentered = centerTextLayerToTargetCenterX(doc, "singkatanlelaki", topAnchor.cx);
            }
        }

        if(bottomLayer && bottomLayer.kind == LayerKind.TEXT && bottomAnchor){
            if(shouldCenterShortName(bottomValue, bottomAnchor, bottomLayer)){
                result.bottomCentered = centerTextLayerToTargetCenterX(doc, "singkatanperempuan", bottomAnchor.cx);
            }
        }
    }catch(e){}
    return result;
}

function findLayerByNames(doc, names){
    try{
        function matches(name){
            var lower = s(name).toLowerCase();
            for(var n=0;n<names.length;n++){
                if(lower === names[n]) return true;
            }
            return false;
        }
        function walk(container){
            for(var i=0;i<container.layers.length;i++){
                var L = container.layers[i];
                if(matches(L.name)) return L;
                if(L.typename === "LayerSet"){
                    var nested = walk(L);
                    if(nested) return nested;
                }
            }
            return null;
        }
        return walk(doc);
    }catch(e){ return null; }
}

function replaceCardImageIfExists(doc, imagePath){
    if(!imagePath || t(imagePath) === "") return false;
    var imageLayer = findLayerByNames(doc, ["gambar", "cardimage", "card_image", "gambar pengantin", "pengantin", "photo", "image"]);
    if(!imageLayer) return false;
    try{
        imageLayer = convertToSmartObjectIfNeeded(imageLayer);
        var targetBox = _boundsPx(imageLayer);
        if(!replaceSmartObjectContents(imageLayer, imagePath)) return false;
        fitLayerToBox(imageLayer, targetBox);
        return true;
    }catch(e){ return false; }
}

function containsPortraitWord(x){
    var nm = s(x).toLowerCase();
    return (nm.indexOf("potrait") >= 0 || nm.indexOf("portrait") >= 0 || nm.indexOf("potret") >= 0);
}

function isPortraitDesign(tema, tplPath, tplName){
    // PORTRAIT/POTRAIT is a DESIGN FAMILY, not necessarily part of each PSD filename.
    // Example: ...\\POTRAIT\\CKP-005\\KAD DEPAN.psd
    return containsPortraitWord(tema) || containsPortraitWord(tplPath) || containsPortraitWord(tplName);
}

function _containsAnyKeyword(text, keywords){
    var nm = s(text).toLowerCase();
    for(var i=0;i<keywords.length;i++){
        if(nm.indexOf(keywords[i]) >= 0) return true;
    }
    return false;
}

// V27: robustly locate the customer-photo placeholder.
// Important changes from V26:
// - never returns a LayerSet/group as the replacement target;
// - considers parent-group names (many templates keep the real photo layer inside a group named GAMBAR/PHOTO);
// - scores multiple Smart Objects instead of giving up when more than one exists;
// - excludes QR/text/decorative candidates as much as possible.
var LAST_PORTRAIT_IMAGE_LAYER = "";
var LAST_PORTRAIT_IMAGE_METHOD = "";

function findPortraitImageLayer(doc){
    LAST_PORTRAIT_IMAGE_LAYER = "";
    LAST_PORTRAIT_IMAGE_METHOD = "";

    var positive = [
        "potrait", "portrait", "potret", "foto", "photo", "picture", "pic",
        "gambar pengantin", "gambar customer", "gambar", "pengantin", "customer image",
        "customer photo", "cardimage", "card_image", "couple", "model", "subject"
    ];
    var negative = [
        "qrlocation", "qr code", "qrcode", "logo", "watermark", "frame", "border",
        "ornament", "hiasan", "decoration", "decor", "icon", "arrow", "anak panah",
        "text", "teks", "nama", "tarikh", "bulan", "hari", "alamat"
    ];

    var docW = 0, docH = 0, docArea = 0;
    try{
        docW = doc.width.as("px");
        docH = doc.height.as("px");
        docArea = docW * docH;
    }catch(e){}

    var candidates = [];

    function scoreArtLayer(L, parentPath){
        try{
            if(!L || L.typename !== "ArtLayer") return;
            var nm = s(L.name).toLowerCase();
            var path = s(parentPath).toLowerCase();

            if(nm === "qrlocation" || _containsAnyKeyword(nm, ["qrlocation", "qr code", "qrcode"])) return;
            if(L.kind == LayerKind.TEXT) return;

            var score = 0;
            var method = [];

            if(_containsAnyKeyword(nm, positive)){
                score += 120;
                method.push("layer-name");
            }
            if(_containsAnyKeyword(path, positive)){
                score += 90;
                method.push("parent-group");
            }
            if(_containsAnyKeyword(nm, negative)) score -= 140;
            if(_containsAnyKeyword(path, ["qr", "logo", "watermark"])) score -= 100;

            try{
                if(L.kind == LayerKind.SMARTOBJECT){
                    score += 45;
                    method.push("smart-object");
                }else if(L.kind == LayerKind.NORMAL){
                    score += 15;
                    method.push("pixel-layer");
                }
            }catch(kindErr){}

            try{
                var b = _boundsPx(L);
                var area = Math.max(0, b.w) * Math.max(0, b.h);
                var ratio = docArea > 0 ? area / docArea : 0;

                // A portrait placeholder is usually a meaningful visual area,
                // not a tiny icon. Give larger visual layers a controlled bonus.
                if(ratio >= 0.02) score += 10;
                if(ratio >= 0.08) score += 12;
                if(ratio >= 0.20) score += 12;
                if(ratio >= 0.45) score += 8;
                if(ratio < 0.004) score -= 35;

                candidates.push({layer:L, score:score, ratio:ratio, method:method.join("+")});
            }catch(boundsErr){
                candidates.push({layer:L, score:score, ratio:0, method:method.join("+")});
            }
        }catch(e){}
    }

    function walk(container, parentPath){
        for(var i=0;i<container.layers.length;i++){
            var L = container.layers[i];
            var nextPath = parentPath;
            if(L.typename === "LayerSet"){
                nextPath = parentPath === "" ? s(L.name) : (parentPath + "/" + s(L.name));
                walk(L, nextPath);
            }else{
                scoreArtLayer(L, parentPath);
            }
        }
    }
    walk(doc, "");

    if(candidates.length === 0) return null;

    candidates.sort(function(a,b){
        if(a.score > b.score) return -1;
        if(a.score < b.score) return 1;
        if(a.ratio > b.ratio) return -1;
        if(a.ratio < b.ratio) return 1;
        return 0;
    });

    var best = candidates[0];

    // Named/grouped photo candidates easily pass this threshold.
    // For generic Smart Objects, allow the largest meaningful candidate as fallback.
    if(best.score < 48){
        var genericBest = null;
        for(var c=0;c<candidates.length;c++){
            try{
                if(candidates[c].layer.kind == LayerKind.SMARTOBJECT && candidates[c].ratio >= 0.03){
                    if(!genericBest || candidates[c].ratio > genericBest.ratio) genericBest = candidates[c];
                }
            }catch(e){}
        }
        if(genericBest){
            best = genericBest;
            best.method = (best.method ? best.method + "+" : "") + "largest-smart-object-fallback";
        }else{
            return null;
        }
    }

    LAST_PORTRAIT_IMAGE_LAYER = s(best.layer.name);
    LAST_PORTRAIT_IMAGE_METHOD = best.method || "scored-candidate";
    return best.layer;
}


// ------------------ V28: KAD BELAKANG ONLY ------------------
// Preserve all V27 behaviour for every other template.
// KAD BELAKANG needs a stricter photo-target rule because generic "largest smart object"
// detection can select the dark/base artwork instead of the actual portrait area.
// Strategy:
//   1) Prefer a real photo/portrait layer located in the UPPER section of KAD BELAKANG.
//   2) Reject full-canvas/background/QR/decor candidates aggressively.
//   3) If no reliable placeholder exists, add the customer photo only into a masked
//      upper portrait region (never full-canvas), above the base background.
function findKadBelakangPortraitLayer(doc){
    LAST_PORTRAIT_IMAGE_LAYER = "";
    LAST_PORTRAIT_IMAGE_METHOD = "";

    var positive = [
        "potrait", "portrait", "potret", "foto", "photo", "picture", "pic",
        "gambar pengantin", "gambar customer", "gambar", "pengantin", "customer image",
        "customer photo", "cardimage", "card_image", "couple", "model", "subject"
    ];
    var negative = [
        "qrlocation", "qr code", "qrcode", "logo", "watermark",
        "background", "bg", "base", "frame", "border", "bingkai", "overlay",
        "texture", "tekstur", "shadow", "bayang", "ornament", "hiasan",
        "decoration", "decor", "icon", "arrow", "anak panah", "line", "garisan",
        "flower", "bunga", "daun", "leaf", "text", "teks", "nama", "tarikh",
        "bulan", "hari", "alamat"
    ];

    var docW = 0, docH = 0, docArea = 0;
    try{
        docW = doc.width.as("px");
        docH = doc.height.as("px");
        docArea = docW * docH;
    }catch(e){}

    var candidates = [];

    function scoreLayer(L, parentPath){
        try{
            if(!L || L.typename !== "ArtLayer") return;
            if(L.kind == LayerKind.TEXT) return;

            var nm = s(L.name).toLowerCase();
            var path = s(parentPath).toLowerCase();
            if(nm === "qrlocation" || _containsAnyKeyword(nm, ["qrlocation", "qr code", "qrcode"])) return;

            var b = _boundsPx(L);
            if(b.w <= 0 || b.h <= 0) return;

            var area = b.w * b.h;
            var ratio = docArea > 0 ? area / docArea : 0;
            var wRatio = docW > 0 ? b.w / docW : 0;
            var hRatio = docH > 0 ? b.h / docH : 0;
            var cyRatio = docH > 0 ? b.cy / docH : 1;
            var bottomRatio = docH > 0 ? (b.y + b.h) / docH : 1;

            // Ignore tiny items outright.
            if(ratio < 0.008) return;

            var score = 0;
            var method = [];

            if(_containsAnyKeyword(nm, positive)){
                score += 190;
                method.push("photo-layer-name");
            }
            if(_containsAnyKeyword(path, positive)){
                score += 135;
                method.push("photo-parent-group");
            }
            if(_containsAnyKeyword(nm, negative)) score -= 210;
            if(_containsAnyKeyword(path, ["qr", "logo", "background", "bg", "base", "frame", "border"])) score -= 120;

            try{
                if(L.kind == LayerKind.SMARTOBJECT){
                    score += 55;
                    method.push("smart-object");
                }else if(L.kind == LayerKind.NORMAL){
                    score += 18;
                    method.push("pixel-layer");
                }
            }catch(kindErr){}

            // KAD BELAKANG portrait is visually in the upper portion of the card.
            if(cyRatio <= 0.42){ score += 80; method.push("upper-zone"); }
            else if(cyRatio <= 0.55){ score += 40; method.push("upper-mid-zone"); }
            else if(cyRatio >= 0.68){ score -= 110; }

            if(b.y <= docH * 0.12) score += 20;
            if(bottomRatio <= 0.62) score += 35;
            if(bottomRatio > 0.82) score -= 60;

            // Typical photo area: meaningful width, but not a full-canvas background.
            if(wRatio >= 0.45) score += 28;
            if(wRatio >= 0.70) score += 12;
            if(hRatio >= 0.16 && hRatio <= 0.58) score += 32;
            if(ratio >= 0.08 && ratio <= 0.58) score += 30;
            if(ratio > 0.72 || (wRatio > 0.93 && hRatio > 0.82)) score -= 190;

            candidates.push({
                layer:L,
                score:score,
                ratio:ratio,
                cyRatio:cyRatio,
                method:method.join("+")
            });
        }catch(e){}
    }

    function walk(container, parentPath){
        for(var i=0;i<container.layers.length;i++){
            var L = container.layers[i];
            if(L.typename === "LayerSet"){
                var nextPath = parentPath === "" ? s(L.name) : (parentPath + "/" + s(L.name));
                walk(L, nextPath);
            }else{
                scoreLayer(L, parentPath);
            }
        }
    }
    walk(doc, "");

    if(candidates.length === 0) return null;

    candidates.sort(function(a,b){
        if(a.score > b.score) return -1;
        if(a.score < b.score) return 1;
        if(a.cyRatio < b.cyRatio) return -1;
        if(a.cyRatio > b.cyRatio) return 1;
        if(a.ratio > b.ratio) return -1;
        if(a.ratio < b.ratio) return 1;
        return 0;
    });

    var best = candidates[0];
    // High enough to avoid replacing a dark/background layer by mistake.
    if(best.score < 72) return null;

    LAST_PORTRAIT_IMAGE_LAYER = s(best.layer.name);
    LAST_PORTRAIT_IMAGE_METHOD = "kad-belakang-target:" + (best.method || "upper-geometry");
    return best.layer;
}

function replaceKadBelakangPortraitImageIfExists(doc, imagePath){
    if(!imagePath || t(imagePath) === "") return false;

    var imageLayer = findKadBelakangPortraitLayer(doc);
    if(!imageLayer) return false;

    try{
        app.activeDocument = doc;
        imageLayer = convertToSmartObjectIfNeeded(imageLayer);
        var targetBox = _boundsPx(imageLayer);

        if(!replaceSmartObjectContents(imageLayer, imagePath)) return false;

        fitLayerToBox(imageLayer, targetBox);
        LAST_PORTRAIT_IMAGE_LAYER = s(imageLayer.name);
        return true;
    }catch(e){
        return false;
    }
}

function _addRevealSelectionMask(){
    try{
        var idMk = charIDToTypeID("Mk  ");
        var desc = new ActionDescriptor();
        var ref = new ActionReference();
        ref.putClass(charIDToTypeID("Chnl"));
        desc.putReference(charIDToTypeID("null"), ref);
        var atRef = new ActionReference();
        atRef.putEnumerated(charIDToTypeID("Chnl"), charIDToTypeID("Chnl"), charIDToTypeID("Msk "));
        desc.putReference(charIDToTypeID("At  "), atRef);
        desc.putEnumerated(charIDToTypeID("Usng"), charIDToTypeID("UsrM"), charIDToTypeID("RvlS"));
        executeAction(idMk, desc, DialogModes.NO);
        return true;
    }catch(e){ return false; }
}

function addKadBelakangPortraitTopRegion(doc, imagePath){
    var photoDoc = null;
    var oldUnits = null;
    try{
        app.activeDocument = doc;
        try{
            oldUnits = app.preferences.rulerUnits;
            app.preferences.rulerUnits = Units.PIXELS;
        }catch(unitErr){}

        var bottomAnchor = null;
        try{
            if(doc.layers.length > 0) bottomAnchor = doc.layers[doc.layers.length - 1];
        }catch(anchorErr){}

        photoDoc = app.open(new File(imagePath));
        var photoLayer = photoDoc.activeLayer.duplicate(doc, ElementPlacement.PLACEATEND);
        closeDocNoSave(photoDoc);
        photoDoc = null;

        app.activeDocument = doc;
        doc.activeLayer = photoLayer;
        photoLayer.name = "Gambar Pengantin - V28 Kad Belakang";

        var width = doc.width.as("px");
        var height = doc.height.as("px");

        // Based on KAD BELAKANG composition: customer portrait occupies only the upper section.
        // Small side inset keeps the existing outer/frame design visible.
        var insetX = width * 0.035;
        var topY = height * 0.035;
        var targetW = width - (insetX * 2);
        var targetH = height * 0.43;
        var targetBox = {
            x: insetX,
            y: topY,
            w: targetW,
            h: targetH,
            cx: insetX + targetW/2,
            cy: topY + targetH/2
        };

        var current = _boundsPx(photoLayer);
        if(current.w <= 0 || current.h <= 0) return false;
        var scale = Math.max(targetBox.w / current.w, targetBox.h / current.h) * 100;
        _transformScalePercent(scale);
        current = _boundsPx(photoLayer);
        _translate(targetBox.cx - current.cx, targetBox.cy - current.cy);

        // Clip the photo strictly to the upper portrait area.
        doc.selection.select([
            [targetBox.x, targetBox.y],
            [targetBox.x + targetBox.w, targetBox.y],
            [targetBox.x + targetBox.w, targetBox.y + targetBox.h],
            [targetBox.x, targetBox.y + targetBox.h]
        ]);
        _addRevealSelectionMask();
        try{ doc.selection.deselect(); }catch(deselectErr){}

        // Keep it above the original base/background while leaving the existing
        // KAD BELAKANG information, QR, frame and decorative layers untouched above it.
        if(bottomAnchor && bottomAnchor !== photoLayer){
            try{ photoLayer.move(bottomAnchor, ElementPlacement.PLACEBEFORE); }catch(moveErr){}
        }
        bringTextGroupsToFront(doc, photoLayer);

        LAST_PORTRAIT_IMAGE_LAYER = s(photoLayer.name);
        LAST_PORTRAIT_IMAGE_METHOD = "kad-belakang-masked-upper-fallback";

        if(oldUnits !== null){
            try{ app.preferences.rulerUnits = oldUnits; }catch(restoreErr){}
        }
        return true;
    }catch(e){
        if(photoDoc) closeDocNoSave(photoDoc);
        if(oldUnits !== null){
            try{ app.preferences.rulerUnits = oldUnits; }catch(restoreErr2){}
        }
        return false;
    }
}

function replacePortraitImageIfExists(doc, imagePath){
    if(!imagePath || t(imagePath) === "") return false;

    var imageLayer = findPortraitImageLayer(doc);
    if(!imageLayer) return false;

    try{
        app.activeDocument = doc;
        imageLayer = convertToSmartObjectIfNeeded(imageLayer);
        var targetBox = _boundsPx(imageLayer);

        if(!replaceSmartObjectContents(imageLayer, imagePath)) return false;

        fitLayerToBox(imageLayer, targetBox);
        LAST_PORTRAIT_IMAGE_LAYER = s(imageLayer.name);
        return true;
    }catch(e){
        return false;
    }
}

// V32: ARROW templates can contain two visible Smart Objects with the exact
// same name "gambar". Photoshop DOM enumerates them bottom-to-top for these
// production files. The first DOM match is the real "Place Your image Here"
// placeholder; later matches are redundant clipped copies that do not control
// the rendered customer photo.
function findArrowPortraitImageLayer(doc){
    var aliases = [
        "gambar", "gambar pengantin", "gambar customer", "customer image",
        "customer photo", "cardimage", "card_image", "photo", "picture", "image"
    ];
    function matches(name){
        var lower = s(name).toLowerCase();
        for(var n=0;n<aliases.length;n++){
            if(lower === aliases[n]) return true;
        }
        return false;
    }

    function walk(container){
        for(var i=0;i<container.layers.length;i++){
            var L = container.layers[i];
            if(L.typename === "LayerSet"){
                var nested = walk(L);
                if(nested) return nested;
            }else if(matches(L.name)){
                try{
                    if(L.kind != LayerKind.TEXT && L.visible !== false) return L;
                }catch(kindErr){
                    return L;
                }
            }
        }
        return null;
    }

    try{ return walk(doc); }catch(e){}
    return null;
}

function replaceArrowPortraitImageIfExists(doc, imagePath){
    if(!imagePath || t(imagePath) === "") return false;

    var imageLayer = findArrowPortraitImageLayer(doc);
    if(!imageLayer) return false;

    try{
        app.activeDocument = doc;
        imageLayer = convertToSmartObjectIfNeeded(imageLayer);
        var targetBox = _boundsPx(imageLayer);

        if(!replaceSmartObjectContents(imageLayer, imagePath)) return false;

        fitLayerToBox(imageLayer, targetBox);
        LAST_PORTRAIT_IMAGE_LAYER = s(imageLayer.name);
        LAST_PORTRAIT_IMAGE_METHOD = "arrow-first-dom-visible-named-layer";
        return true;
    }catch(e){
        return false;
    }
}

function centerLayerGroupInDocument(doc, layerNames){
    try{
        var layers = [];
        for(var i=0;i<layerNames.length;i++){
            var layer = findLayerByNames(doc, [layerNames[i]]);
            if(layer && layer.kind == LayerKind.TEXT) layers.push(layer);
        }
        if(layers.length < 2) return false;

        var left = null, right = null;
        for(var j=0;j<layers.length;j++){
            var bounds = _boundsPx(layers[j]);
            left = left === null ? bounds.x : Math.min(left, bounds.x);
            right = right === null ? bounds.x + bounds.w : Math.max(right, bounds.x + bounds.w);
        }
        var groupCenter = left + ((right - left) / 2);
        var offset = (doc.width.as("px") / 2) - groupCenter;
        for(var k=0;k<layers.length;k++){
            app.activeDocument = doc;
            doc.activeLayer = layers[k];
            _translate(offset, 0);
        }
        return true;
    }catch(e){ return false; }
}

// KAD DEPAN: susun dua singkatan kiri/kanan dengan "&" tepat di tengah.
// V13 FIX:
// - "&" menjadi anchor horizontal di tengah canvas.
// - singkatanlelaki dan singkatanperempuan menggunakan BASELINE Y yang sama dengan "&"
//   (bukan center bounding-box), supaya tulisan nampak sejajar walaupun font/saiz berbeza.
// - gap kiri/kanan kepada "&" adalah sama.
function centerShortNamesAroundAmpersand(doc){
    try{
        var leftName  = findLayerByNames(doc, ["singkatanlelaki"]);
        var rightName = findLayerByNames(doc, ["singkatanperempuan"]);
        var amp       = findLayerByNames(doc, ["&", "&amp;"]);
        if(!leftName || !rightName || !amp) return false;
        if(leftName.kind != LayerKind.TEXT || rightName.kind != LayerKind.TEXT || amp.kind != LayerKind.TEXT) return false;

        app.activeDocument = doc;

        // Helper: align TEXT BASELINE Y to the ampersand's text position.
        // textItem.position[1] is more reliable for typographic alignment than bounds center.
        function alignBaselineToAmp(layer, ampLayer){
            try{
                var ampY = ampLayer.textItem.position[1].as("px");
                var layerY = layer.textItem.position[1].as("px");
                doc.activeLayer = layer;
                _translate(0, ampY - layerY);
                return true;
            }catch(e){
                return false;
            }
        }

        // 1) Letakkan & tepat pada tengah horizontal canvas.
        var ab = _boundsPx(amp);
        doc.activeLayer = amp;
        _translate((doc.width.as("px") / 2) - ab.cx, 0);
        ab = _boundsPx(amp);

        // 2) Selaraskan BASELINE kedua-dua singkatan dengan baseline &.
        // Ini membetulkan masalah nama nampak lebih tinggi/rendah daripada ampersand.
        alignBaselineToAmp(leftName, amp);
        alignBaselineToAmp(rightName, amp);

        // 3) Gunakan gap simetri kiri/kanan.
        // Ambil gap asal template jika valid, tetapi paksa kedua-dua sisi menggunakan gap sama.
        var lb = _boundsPx(leftName);
        var rb = _boundsPx(rightName);
        ab = _boundsPx(amp);

        var gapL = ab.x - (lb.x + lb.w);
        var gapR = rb.x - (ab.x + ab.w);
        var gapCandidates = [];
        if(gapL >= 0) gapCandidates.push(gapL);
        if(gapR >= 0) gapCandidates.push(gapR);

        var gap = 18; // fallback yang selamat untuk KAD DEPAN
        if(gapCandidates.length > 0){
            var totalGap = 0;
            for(var gi=0; gi<gapCandidates.length; gi++) totalGap += gapCandidates[gi];
            gap = totalGap / gapCandidates.length;
        }
        gap = Math.max(8, Math.min(80, gap));

        // 4) Letakkan nama kiri dan kanan tepat mengapit & dengan gap yang sama.
        lb = _boundsPx(leftName);
        doc.activeLayer = leftName;
        _translate((ab.x - gap) - (lb.x + lb.w), 0);

        rb = _boundsPx(rightName);
        doc.activeLayer = rightName;
        _translate((ab.x + ab.w + gap) - rb.x, 0);

        return true;
    }catch(e){ return false; }
}

function hasCustomerPhotoLayer(doc){
    return findLayerByNames(doc, ["gambar", "cardimage", "card_image", "gambar pengantin", "pengantin", "photo", "image"]) !== null;
}

function layerContainsText(layer){
    try{
        if(layer.kind == LayerKind.TEXT) return true;
        if(layer.typename != "LayerSet") return false;
        for(var i=0;i<layer.layers.length;i++){
            if(layerContainsText(layer.layers[i])) return true;
        }
    }catch(e){}
    return false;
}

function bringTextGroupsToFront(doc, photoLayer){
    try{
        for(var i=doc.layers.length-1;i>=0;i--){
            var layer = doc.layers[i];
            if(layer !== photoLayer && layerContainsText(layer)){
                layer.move(photoLayer, ElementPlacement.PLACEBEFORE);
            }
        }
    }catch(e){}
}

function addCustomerPhotoBackground(doc, imagePath){
    var photoDoc = null;
    try{
        app.activeDocument = doc;

        // Remember the original bottom layer before adding the customer photo.
        // The fallback photo will be placed just ABOVE this base layer so the
        // template text/QR/decor remain above it.
        var bottomAnchor = null;
        try{
            if(doc.layers.length > 0) bottomAnchor = doc.layers[doc.layers.length - 1];
        }catch(anchorErr){}

        photoDoc = app.open(new File(imagePath));
        var photoLayer = photoDoc.activeLayer.duplicate(doc, ElementPlacement.PLACEATEND);
        closeDocNoSave(photoDoc);
        photoDoc = null;

        app.activeDocument = doc;
        doc.activeLayer = photoLayer;
        photoLayer.name = "Gambar Pengantin - V27 Fallback";

        var width = doc.width.as("px");
        var height = doc.height.as("px");
        var current = _boundsPx(photoLayer);
        if(current.w <= 0 || current.h <= 0) return false;

        // Cover canvas while preserving aspect ratio.
        var scale = Math.max(width / current.w, height / current.h) * 100;
        _transformScalePercent(scale);
        current = _boundsPx(photoLayer);
        _translate((width / 2) - current.cx, (height / 2) - current.cy);

        // Put it above the original base/background, not on top of the design.
        if(bottomAnchor && bottomAnchor !== photoLayer){
            try{ photoLayer.move(bottomAnchor, ElementPlacement.PLACEBEFORE); }catch(moveErr){}
        }

        // Extra guard: text groups must stay above the fallback customer photo.
        bringTextGroupsToFront(doc, photoLayer);
        LAST_PORTRAIT_IMAGE_LAYER = s(photoLayer.name);
        LAST_PORTRAIT_IMAGE_METHOD = "fallback-background-above-base";
        return true;
    }catch(e){
        if(photoDoc) closeDocNoSave(photoDoc);
        return false;
    }
}

// ---------- Smart Object replace + AUTO-FIT ----------
function convertToSmartObjectIfNeeded(layer){
    try{
        if (layer.kind != LayerKind.SMARTOBJECT){
            app.activeDocument.activeLayer = layer;
            executeAction(stringIDToTypeID("newPlacedLayer"), new ActionDescriptor(), DialogModes.NO);
            return app.activeDocument.activeLayer; // now is SO
        }
    }catch(e){}
    return layer;
}
function replaceSmartObjectContents(layer, newFilePath) {
    try {
        var fileRef = new File(newFilePath);
        if (!fileRef.exists) return false;
        app.activeDocument.activeLayer = layer;
        var id = stringIDToTypeID("placedLayerReplaceContents");
        var d  = new ActionDescriptor();
        d.putPath(charIDToTypeID("null"), fileRef);
        try{ d.putInteger(charIDToTypeID("Idnt"), 4); }catch(e){}
        executeAction(id, d, DialogModes.NO);
        return true;
    } catch (e) { return false; }
}
function _boundsPx(layer){
    var b = layer.bounds;
    var x1 = b[0].as("px"), y1 = b[1].as("px"), x2 = b[2].as("px"), y2 = b[3].as("px");
    var w = x2 - x1, h = y2 - y1;
    return {x:x1, y:y1, w:w, h:h, cx:x1+w/2, cy:y1+h/2};
}
function _transformScalePercent(percent){
    var idTrnf = charIDToTypeID("Trnf");
    var desc = new ActionDescriptor();
    var ref = new ActionReference();
    ref.putEnumerated(charIDToTypeID("Lyr "), charIDToTypeID("Ordn"), charIDToTypeID("Trgt"));
    desc.putReference(charIDToTypeID("null"), ref);
    desc.putEnumerated(charIDToTypeID("FTcs"), charIDToTypeID("QCSt"), charIDToTypeID("Qcsa")); // center
    desc.putUnitDouble(charIDToTypeID("Wdth"), charIDToTypeID("#Prc"), percent);
    desc.putUnitDouble(charIDToTypeID("Hght"), charIDToTypeID("#Prc"), percent);
    executeAction(idTrnf, desc, DialogModes.NO);
}
function _translate(dx, dy){
    try{ app.activeDocument.activeLayer.translate(dx, dy); }catch(e){}
}
function fitLayerToBox(layer, targetBox){
    try{
        var ru = app.preferences.rulerUnits;
        app.preferences.rulerUnits = Units.PIXELS;

        app.activeDocument.activeLayer = layer;
        var cur = _boundsPx(layer);
        if (cur.w <= 0 || cur.h <= 0) { app.preferences.rulerUnits = ru; return; }

        var sx = targetBox.w / cur.w;
        var sy = targetBox.h / cur.h;
        var scale = Math.max(sx, sy) * 100;
        _transformScalePercent(scale);

        cur = _boundsPx(layer);
        var dx = targetBox.cx - cur.cx;
        var dy = targetBox.cy - cur.cy;
        _translate(dx, dy);

        app.preferences.rulerUnits = ru;
    }catch(e){}
}
function replaceQRIfExists(doc, qrPath) {
    function walk(container) {
        for (var i=0; i<container.layers.length; i++) {
            var L = container.layers[i];
            if (L.typename === "LayerSet") {
                var ok = walk(L); if (ok) return true;
            } else {
                if (L.name && L.name.toLowerCase() === "qrlocation") {
                    var targetLayer = L;
                    targetLayer = convertToSmartObjectIfNeeded(targetLayer);
                    var targetBox = _boundsPx(targetLayer);

                    var repOK = replaceSmartObjectContents(targetLayer, qrPath);
                    if (repOK) {
                        fitLayerToBox(targetLayer, targetBox);
                        return true;
                    }
                    return false;
                }
            }
        }
        return false;
    }
    return walk(doc);
}

// ------------------ Template discovery ------------------
function findTemplates(masterPath, tema, designCode){
    var list = [];
    var seen = {};

    function pushIfOk(f){
        try{
            if(!f || !f.exists || f instanceof Folder) return;
            if(!/\.psd$/i.test(f.name)) return;
            var nm = f.name.toLowerCase();
            if(nm.indexOf("_patched") >= 0) return;

            var key = f.fsName.toLowerCase();
            if(seen[key]) return;
            seen[key] = true;
            list.push(f.fsName);
        }catch(e){}
    }

    function addPsdRecursive(folder, depth){
        if(depth < 0 || !folder || !folder.exists) return;
        try{
            var items = folder.getFiles();
            for(var i=0;i<items.length;i++){
                if(items[i] instanceof Folder){
                    addPsdRecursive(items[i], depth - 1);
                }else{
                    pushIfOk(items[i]);
                }
            }
        }catch(e){}
    }

    function findChildFolderCaseInsensitive(parent, wanted){
        try{
            if(!parent || !parent.exists) return null;
            var kids = parent.getFiles(function(x){ return x instanceof Folder; });
            var wk = t(wanted).toLowerCase();
            for(var i=0;i<kids.length;i++){
                if(t(kids[i].name).toLowerCase() === wk) return kids[i];
            }
        }catch(e){}
        return null;
    }

    function recursiveFindDesignFolder(parent, wantedCode, depth){
        if(depth < 0 || !parent || !parent.exists) return null;
        try{
            var kids = parent.getFiles(function(x){ return x instanceof Folder; });
            var wk = t(wantedCode).toLowerCase();

            for(var i=0;i<kids.length;i++){
                if(t(kids[i].name).toLowerCase() === wk) return kids[i];
            }
            for(var j=0;j<kids.length;j++){
                var found = recursiveFindDesignFolder(kids[j], wantedCode, depth - 1);
                if(found) return found;
            }
        }catch(e){}
        return null;
    }

    try{
        var master = new Folder(masterPath);
        if(!master.exists) return list;

        // Preferred: MASTER/<Tema>/<DesignCode>/...
        var temaFolder = findChildFolderCaseInsensitive(master, tema);
        if(temaFolder){
            var designFolder = findChildFolderCaseInsensitive(temaFolder, designCode);
            if(designFolder){
                // V19: include PSD files in subfolders too.
                addPsdRecursive(designFolder, 6);
            }
        }

        // Fallback: MASTER/<DesignCode>/...
        if(list.length === 0){
            var directCodeFolder = findChildFolderCaseInsensitive(master, designCode);
            if(directCodeFolder) addPsdRecursive(directCodeFolder, 6);
        }

        // Last resort: exact DesignCode folder anywhere under MASTER.
        if(list.length === 0){
            var recursiveFolder = recursiveFindDesignFolder(master, designCode, 6);
            if(recursiveFolder) addPsdRecursive(recursiveFolder, 6);
        }

        // Legacy fallback: PSD directly inside Tema whose filename contains DesignCode.
        if(list.length === 0 && temaFolder){
            var direct = temaFolder.getFiles(function(x){
                return (x instanceof File) && /\.psd$/i.test(x.name);
            });
            var codeKey = t(designCode).toLowerCase();
            for(var d=0; d<direct.length; d++){
                if(direct[d].name.toLowerCase().indexOf(codeKey) >= 0) pushIfOk(direct[d]);
            }
        }

        // Deterministic processing order.
        list.sort(function(a,b){
            var aa = a.toLowerCase(), bb = b.toLowerCase();
            if(aa < bb) return -1;
            if(aa > bb) return 1;
            return 0;
        });
    }catch(e){}

    return list;
}

// ------------------ Item quantity mapping ------------------
function fixedQtyForItem(tplName, qtyFromCSV) {
    var nm = (tplName||"").toLowerCase();
    if (nm.indexOf("banner") >= 0) return "1";
    if (nm.indexOf("banting") >= 0) return "1";
    if (nm.indexOf("arrow kanan") >= 0) return "2";
    if (nm.indexOf("arrow kiri") >= 0) return "2";
    if (nm.indexOf("sticker") >= 0) return "150";
    if (nm.indexOf("hanger") >= 0) return "1";
    return qtyFromCSV; // default: gunakan qtykad
}

// V33 portrait module. ES3/ExtendScript compatible. No CSV contract changes.
// Normalized [left, top, right, bottom] rectangles follow the supplied red guides.
// A PSD layer named KKK_PHOTO_FRAME or PHOTO_FRAME overrides these defaults.
// These are CROP guides; no red stroke is created or exported.
var KKK_PORTRAIT_FRAMES = {
    "ARROW KANAN": [0, 0.235, 1, 0.95],
    "ARROW KIRI":  [0, 0.235, 1, 0.95],
    "BANNER":      [0, 0, 0.51, 1],
    "BANTING":     [0, 0.258, 1, 0.96],
    "KAD DEPAN":   [0, 0, 1, 0.80],
    "STICKER":     [0, 0.20, 1, 1],
    "VIP CARD":    [0, 0, 1, 0.55]
};

// One optional visual check per template: adjust crop position and zoom.
// Turn on via the batch confirmation dialog.
var KKK_REVIEW_PORTRAIT_CROP = false;

function kkkPortraitItem(name) {
    var n = t(name).toUpperCase().replace(/\.PSD$/i, "");
    if (/^VIPCARD($|[ _(-])/.test(n)) return "VIP CARD";
    for (var key in KKK_PORTRAIT_FRAMES) {
        if (KKK_PORTRAIT_FRAMES.hasOwnProperty(key) &&
            (n === key || n.indexOf(key + "_") === 0 ||
             n.indexOf(key + " ") === 0 || n.indexOf(key + "(") === 0)) return key;
    }
    return "";
}

function kkkCoverGeometry(sourceW, sourceH, box, focusX, focusY) {
    if (!(sourceW > 0 && sourceH > 0 && box.w > 0 && box.h > 0)) {
        throw new Error("Dimensi gambar/bingkai tidak sah.");
    }
    var scale = Math.max(box.w / sourceW, box.h / sourceH);
    var fx = Math.max(0, Math.min(1, focusX));
    var fy = Math.max(0, Math.min(1, focusY));
    return {
        scale: scale, w: sourceW * scale, h: sourceH * scale,
        x: box.x - (sourceW * scale - box.w) * fx,
        y: box.y - (sourceH * scale - box.h) * fy
    };
}

function kkkWalkLayers(container, visitor) {
    for (var i = 0; i < container.layers.length; i++) {
        var layer = container.layers[i];
        visitor(layer);
        if (layer.typename === "LayerSet") kkkWalkLayers(layer, visitor);
    }
}

function kkkEffectiveVisible(layer) {
    var current = layer;
    while (current && current.typename !== "Document") {
        if (!current.visible) return false;
        current = current.parent;
    }
    return true;
}

function kkkFrameForDocument(doc, item) {
    var frames = [];
    kkkWalkLayers(doc, function(layer) {
        var name = t(layer.name).toLowerCase();
        if (name === "kkk_photo_frame" || name === "photo_frame") frames.push(layer);
    });
    if (frames.length > 1) throw new Error("Lebih daripada satu PHOTO_FRAME dalam PSD.");
    var w = doc.width.as("px"), h = doc.height.as("px"), box, marker = null;
    if (frames.length === 1) {
        marker = frames[0];
        box = _boundsPx(marker);
        // Hidden or opacity-0 shape/SO must still have measurable geometry.
        // An empty transparent pixel layer has no geometry and is rejected.
    } else {
        var rect = KKK_PORTRAIT_FRAMES[item];
        if (!rect) throw new Error("Tiada profil bingkai untuk " + item);
        box = {x: Math.round(w * rect[0]), y: Math.round(h * rect[1]),
            w: Math.round(w * rect[2]) - Math.round(w * rect[0]),
            h: Math.round(h * rect[3]) - Math.round(h * rect[1])};
    }
    if (box.w <= 0 || box.h <= 0 || box.x < 0 || box.y < 0 ||
        box.x + box.w > w + 1 || box.y + box.h > h + 1) {
        throw new Error("PHOTO_FRAME kosong atau di luar canvas. Gunakan rectangle shape yang mempunyai bounds.");
    }
    box.cx = box.x + box.w / 2;
    box.cy = box.y + box.h / 2;
    return {box: box, marker: marker, source: marker ? "PHOTO_FRAME" : "reference-profile:" + item};
}

function kkkPhotoStack(doc) {
    // Real CKP PSDs use a brush layer called Mask and clipped photo(s) above it.
    // Do not replace a decorative Smart Object by largest-area heuristics.
    var masks = [];
    kkkWalkLayers(doc, function(layer) {
        if (layer.typename === "ArtLayer" && t(layer.name).toLowerCase() === "mask" &&
            kkkEffectiveVisible(layer) && layer.kind !== LayerKind.TEXT) masks.push(layer);
    });
    if (masks.length !== 1) throw new Error("Perlu tepat satu layer foto 'Mask' yang visible; ditemui " + masks.length);
    var base = masks[0], siblings = base.parent.layers, baseIndex = -1, photos = [];
    for (var i = 0; i < siblings.length; i++) if (siblings[i] === base) baseIndex = i;
    for (var j = baseIndex - 1; j >= 0; j--) {
        var candidate = siblings[j];
        if (candidate.typename !== "ArtLayer" || !candidate.grouped) break;
        if (candidate.kind === LayerKind.TEXT) throw new Error("Layer teks dijumpai dalam clipping stack foto.");
        photos.push(candidate);
    }
    if (!photos.length) throw new Error("Tiada gambar clipping di atas layer Mask. Semak struktur PSD.");
    return {base: base, photos: photos};
}

function kkkMovePhotoGroupBehindArtwork(doc, group) {
    var bottom = null;
    for (var i = doc.layers.length - 1; i >= 0; i--) {
        if (doc.layers[i] !== group) { bottom = doc.layers[i]; break; }
    }
    if (!bottom) return;
    // An opaque canvas/background belongs below the photo. All artwork stays above.
    var b = _boundsPx(bottom), w = doc.width.as("px"), h = doc.height.as("px");
    var background = !layerContainsText(bottom) && b.x <= 1 && b.y <= 1 &&
        b.x + b.w >= w - 1 && b.y + b.h >= h - 1;
    group.move(bottom, background ? ElementPlacement.PLACEBEFORE : ElementPlacement.PLACEAFTER);
}

function kkkPositionPhoto(photo, box, x, y, state) {
    // Keep unmasked geometry: clipped layer.bounds may be smaller than the image.
    var b = state || _boundsPx(photo);
    // Let the image travel across the full overflow OR empty gap in the frame.
    // A smaller image therefore remains movable instead of being pinned to top-left.
    var targetX = box.x + (box.w - b.w) * x;
    var targetY = box.y + (box.h - b.h) * y;
    photo.translate(UnitValue(targetX - b.x, "px"), UnitValue(targetY - b.y, "px"));
    b.x = targetX; b.y = targetY;
    return b;
}

function kkkReviewCrop(doc, photo, box, state) {
    // Photoshop canvas previews crop position and zoom; proportions stay locked.
    var dialog = new Window("dialog", "Semak kedua-dua pengantin - " + doc.name);
    dialog.orientation = "column";
    dialog.add("statictext", undefined, "Laraskan saiz dan kedudukan gambar dalam ruang foto.");
    dialog.add("statictext", undefined, "Saiz gambar");
    var zoom = dialog.add("slider", undefined, 100, 50, 250); zoom.preferredSize.width = 360;
    dialog.add("statictext", undefined, "Kiri / kanan");
    var x = dialog.add("slider", undefined, 50, 0, 100); x.preferredSize.width = 360;
    dialog.add("statictext", undefined, "Atas / bawah");
    var y = dialog.add("slider", undefined, 50, 0, 100); y.preferredSize.width = 360;
    var lastZoom = 100;
    var update = function() {
        app.activeDocument = doc;
        var nextZoom = zoom.value;
        if (Math.abs(nextZoom - lastZoom) > 0.01) {
            var factor = nextZoom / lastZoom;
            photo.resize(factor * 100, factor * 100, AnchorPosition.MIDDLECENTER);
            lastZoom = nextZoom;
        }
        state = _boundsPx(photo);
        kkkPositionPhoto(photo, box, x.value / 100, y.value / 100, state);
        app.refresh();
    };
    zoom.onChanging = update; x.onChanging = update; y.onChanging = update;
    var buttons = dialog.add("group");
    buttons.add("button", undefined, "Teruskan", {name: "ok"});
    buttons.add("button", undefined, "Langkau item ini", {name: "cancel"});
    app.refresh();
    return dialog.show() === 1;
}

function kkkInsertFramedPortrait(doc, imagePath, item) {
    var oldUnits = app.preferences.rulerUnits;
    var photoDoc = null, imported = null, group = null, hidden = [];
    var frame, stack;
    try {
        if (!imagePath || !(new File(imagePath)).exists) throw new Error("Gambar pengantin tidak ditemui.");
        app.activeDocument = doc;
        app.preferences.rulerUnits = Units.PIXELS;
        frame = kkkFrameForDocument(doc, item);
        stack = kkkPhotoStack(doc);

        // Keep all original layers for editability; hide only the old photo stack.
        group = doc.layerSets.add();
        group.name = "KKK PHOTO - " + item + " - V33";
        var brush = stack.base.duplicate(group, ElementPlacement.INSIDE);
        brush.name = "Mask - original brush";
        brush.visible = true;
        // A normal base layer is already unclipped. Assigning grouped=false
        // asks Photoshop to run "Release Clipping Mask" and throws on that base.

        var alreadyOpen = [], d;
        for (d = 0; d < app.documents.length; d++) alreadyOpen.push(app.documents[d]);
        var sourceDoc = app.open(new File(imagePath));
        var sourceWasOpen = false;
        for (d = 0; d < alreadyOpen.length; d++) if (alreadyOpen[d] === sourceDoc) sourceWasOpen = true;
        // Merge a duplicate, never modify/close the user's already-open source.
        try { photoDoc = sourceDoc.duplicate("KKK temporary photo", true); }
        finally { if (!sourceWasOpen) closeDocNoSave(sourceDoc); }
        imported = photoDoc.activeLayer.duplicate(doc, ElementPlacement.PLACEATBEGINNING);
        closeDocNoSave(photoDoc); photoDoc = null;
        app.activeDocument = doc;
        imported.move(group, ElementPlacement.INSIDE);
        imported.move(brush, ElementPlacement.PLACEBEFORE);
        imported.name = "Gambar Pengantin - proportional crop";
        imported.visible = true;
        imported.allLocked = false;
        app.activeDocument.activeLayer = imported;
        executeAction(stringIDToTypeID("newPlacedLayer"), new ActionDescriptor(), DialogModes.NO);
        imported = doc.activeLayer;

        var before = _boundsPx(imported);
        var placement = kkkCoverGeometry(before.w, before.h, frame.box, 0.5, 0.5);
        // Same percentage on both axes: original image proportions are preserved.
        imported.resize(placement.scale * 100, placement.scale * 100, AnchorPosition.MIDDLECENTER);
        var cropState = kkkPositionPhoto(imported, frame.box, 0.5, 0.5);

        // Intersect the rectangle with a COPY of the original brush pixels.
        // Group masks fail on some production PSDs/Photoshop versions. Editing
        // the duplicate brush leaves the MASTER brush and the source PSD intact.
        doc.activeLayer = brush;
        var b = frame.box;
        doc.selection.select([[b.x, b.y], [b.x + b.w, b.y],
            [b.x + b.w, b.y + b.h], [b.x, b.y + b.h]]);
        doc.selection.invert();
        doc.selection.clear();
        doc.selection.deselect();
        imported.grouped = true;

        kkkMovePhotoGroupBehindArtwork(doc, group);
        var old = [stack.base];
        for (var p = 0; p < stack.photos.length; p++) old.push(stack.photos[p]);
        if (frame.marker) old.push(frame.marker);
        for (var v = 0; v < old.length; v++) {
            hidden.push({layer: old[v], visible: old[v].visible}); old[v].visible = false;
        }
        if (KKK_REVIEW_PORTRAIT_CROP && !kkkReviewCrop(doc, imported, frame.box, cropState)) {
            throw new Error("Operator melangkau crop item ini.");
        }
        LAST_PORTRAIT_IMAGE_LAYER = imported.name;
        LAST_PORTRAIT_IMAGE_METHOD = "V33 uniform-cover + rectangle-mask + original-brush + behind-artwork | " + frame.source;
        return {box: frame.box, source: frame.source};
    } catch (error) {
        for (var h = 0; h < hidden.length; h++) {
            try { hidden[h].layer.visible = hidden[h].visible; } catch (restoreVisibilityErr) {}
        }
        if (group) { try { group.remove(); } catch (removeGroupErr) {} }
        throw error;
    } finally {
        if (photoDoc) closeDocNoSave(photoDoc);
        try { app.activeDocument = doc; doc.selection.deselect(); } catch (restoreDocErr) {}
        app.preferences.rulerUnits = oldUnits;
    }
}

// ------------------ Main Flow ------------------
try{
    // Ask operator to select the ROOT folder for this run.
    // Expected structure:
    // <ROOT>/
    //   MASTER/<Tema>/<DesignCode>/*.psd
    //   OUTPUT/
    //
    // Example on KKK laptop:
    // ROOT   = C:\KAD KAHWIN
    // MASTER = C:\KAD KAHWIN\MASTER
    // OUTPUT = C:\KAD KAHWIN\OUTPUT
    var root = Folder.selectDialog("Pilih folder MASTER kad kahwin", new Folder("C:/KAD KAHWIN/MASTER"));
    if(!root){
        alert("Dibatalkan.");
        throw "User cancelled";
    }
    // Accept either the MASTER folder itself or its parent ROOT folder.
    var selectedIsMaster = root.name.toLowerCase() === "master";
    var ROOT = selectedIsMaster ? root.parent.fsName : root.fsName;
    var MASTER = selectedIsMaster ? root : new Folder(ROOT + "/MASTER");
    if(!MASTER.exists){
        alert(
            "Folder MASTER tidak ditemui dalam ROOT yang dipilih:\n" +
            MASTER.fsName +
            "\n\nSila pilih ROOT yang mempunyai struktur ROOT/MASTER."
        );
        throw "MASTER missing";
    }

    var OUTPUT = new Folder(ROOT + "/OUTPUT");
    if(!OUTPUT.exists) OUTPUT.create();

    var batchName = "Batch_" + nowStamp();
    var batchFolder = ensureFolderObj(OUTPUT.fsName + "/" + batchName);

    var stage1 = ensureFolderObj(batchFolder.fsName + "/1 Waiting Customer");
    var stage2 = ensureFolderObj(batchFolder.fsName + "/2 Correction");
    var stage3 = ensureFolderObj(batchFolder.fsName + "/3 Approved & Balance Payment");
    var stage4 = ensureFolderObj(batchFolder.fsName + "/4 Ready To Print");
    var customerParent = stage1;

    var csvFile = File.openDialog("Pilih fail CSV (Ready to merge)", "*.csv");
    if(!csvFile){ alert("Dibatalkan."); throw "CSV cancelled"; }

    var csvText = null;
    try{ var fh=new File(csvFile.fsName); fh.encoding="UTF8"; fh.open("r"); csvText=fh.read(); fh.close(); }
    catch(e){ var fh2=new File(csvFile.fsName); fh2.open("r"); csvText=fh2.read(); fh2.close(); }
    var rows = parseCSV(csvText);
    if(!rows || rows.length < 2){ alert("CSV kosong atau format tak sah."); throw "CSV empty"; }
    var header = rows[0];

    function idx(name){ return findHeaderIndex(header, name); }

    var iNoInv  = idx("NoInvoice");
    var iOutputId = idx("output_id");
    var iJumlahHantaran = idx("jumlah_hantaran");
    var iQty    = idx("qtykad");
    var iTema   = idx("Tema");
    var iCode   = idx("DesignCode");
    var iMajlis = idx("majlis");
    var iGambar = idx("gambar");
    var iNamaL  = idx("namapengantinlelaki");
    var iNamaP  = idx("namapengantinperempuan");
    var iSingL  = idx("singkatanlelaki");
    var iSingP  = idx("singkatanperempuan");
    var iNamaAy = idx("namaayah");
    var iNamaIb = idx("namaibu");
    var iNama1  = idx("nama1"); var iNotel1 = idx("notel1");
    var iNama2  = idx("nama2"); var iNotel2 = idx("notel2");
    var iNama3  = idx("nama3"); var iNotel3 = idx("notel3");
    var iAlamat = idx("alamat");
    var iQRLink = idx("qrlink");

    var iHari       = idx("hari");
    var iTarikh     = idx("tarikh");
    var iTarikhHari = idx("tarikhhari");
    var iBulan      = idx("bulan");
    var iBulanIslam = idx("bulanislam");

    if(iNoInv < 0 || iTema < 0 || iCode < 0 || iMajlis < 0){
        alert("CSV mesti ada kolum NoInvoice, Tema, DesignCode, dan majlis.");
        throw "Missing headers";
    }

    var foundLelaki = false, foundPerempuan = false, foundCek = false;
    for(var checkRow = 1; checkRow < rows.length; checkRow++){
        var checkSide = t(rows[checkRow][iMajlis]).toUpperCase();
        if(checkSide === "LELAKI") foundLelaki = true;
        if(checkSide === "PEREMPUAN") foundPerempuan = true;
        if(checkSide === "CEK_HANTARAN") foundCek = true;
    }
    if(foundLelaki && foundPerempuan && (!foundCek || iOutputId < 0 || iJumlahHantaran < 0)){
        alert("CSV 2 pakej ini tiada baris/kolum Cek Hantaran. Muat turun CSV daripada sistem versi baharu; jangan gunakan CSV lama.");
        throw "CSV dua pakej tanpa Cek Hantaran";
    }

    var logPath = OUTPUT.fsName + "/_customer_log.txt";
    logAppend(logPath, "===== " + nowStamp() + " | START BATCH: " + batchName + " =====");

    KKK_REVIEW_PORTRAIT_CROP = confirm("V35: Semak crop POTRAIT sebelum eksport?\n\nOK = semak dan laras kedudukan supaya kedua-dua pengantin kelihatan.\nCancel = crop tengah automatik (tanpa pengesanan muka).");
    var portraitFailCount = 0;
    var exportSuccessCount = 0;
    var templateMissingCount = 0;
    var openFailCount = 0;
    var cekFailCount = 0;

    for(var r=1; r<rows.length; r++){
        var row = rows[r]; if(!row || row.length === 0) continue;
        function g(i){ return (i>=0 && i<row.length) ? row[i] : ""; }

        var NoInv = t(g(iNoInv)); if(NoInv === "") continue;
        var qty = t(g(iQty));
        var tema = t(g(iTema));
        var code = t(g(iCode));
        var majlis = t(g(iMajlis)).toUpperCase();
        if(majlis === "CEK_HANTARAN"){
            var cekOutputId = t(g(iOutputId));
            if(cekOutputId !== NoInv + "-CH"){
                logAppend(logPath, "[CEK HANTARAN FAIL] " + NoInv + " | output_id tidak sah: " + cekOutputId);
                cekFailCount++;
                continue;
            }
            var cekOutputs = exportCekHantaranRow(
                customerParent,
                cekOutputId,
                t(g(iJumlahHantaran)),
                t(g(iNamaP)),
                t(g(iNamaL)),
                t(g(iHari)),
                t(g(iTarikh)),
                logPath
            );
            exportSuccessCount += cekOutputs;
            if(cekOutputs < 2) cekFailCount++;
            continue;
        }
        var cardOutputId = t(g(iOutputId));
        if(cardOutputId !== "" && cardOutputId !== NoInv + (majlis === "LELAKI" ? "-L" : "-P")){
            logAppend(logPath, "[SKIP] " + NoInv + " | output_id tidak sepadan: " + cardOutputId);
            continue;
        }
        var gambarRaw = t(g(iGambar));
        var gambarPath = "";
        if(gambarRaw !== ""){
            var gambarFile = new File(gambarRaw);
            if(!gambarFile.exists) gambarFile = new File(ROOT + "/" + gambarRaw);
            if(!gambarFile.exists) gambarFile = new File(MASTER.fsName + "/" + gambarRaw);
            if(gambarFile.exists) gambarPath = gambarFile.fsName;
            if(gambarPath === ""){
                var selectedImage = File.openDialog(
                    "Pilih gambar pengantin untuk " + NoInv,
                    "Gambar:*.jpg;*.jpeg;*.png;*.webp"
                );
                if(selectedImage && selectedImage.exists) gambarPath = selectedImage.fsName;
            }
        }

        if(majlis !== "LELAKI" && majlis !== "PEREMPUAN"){
            logAppend(logPath, "[SKIP] " + NoInv + " | majlis tidak sah: " + majlis);
            continue;
        }

        var namaL = t(g(iNamaL));
        var namaP = t(g(iNamaP));
        var singL = t(g(iSingL));
        var singP = t(g(iSingP));
        var namaayah = t(g(iNamaAy));
        var namaibu  = t(g(iNamaIb));
        var nama1 = t(g(iNama1)); var notel1 = t(g(iNotel1));
        var nama2 = t(g(iNama2)); var notel2 = t(g(iNotel2));
        var nama3 = t(g(iNama3)); var notel3 = t(g(iNotel3));

        var hari       = t(g(iHari));
        var tarikh     = t(g(iTarikh));
        var tarikhhari = t(g(iTarikhHari));
        var bulan      = t(g(iBulan));
        var bulanislam = t(g(iBulanIslam));

        var alamatRaw = g(iAlamat); if(/^".*"$/.test(alamatRaw)){ alamatRaw = alamatRaw.replace(/^"|"$/g,""); }
        var alamat = alamatRaw;

        var qrlinkRaw = g(iQRLink); if(/^".*"$/.test(qrlinkRaw)) qrlinkRaw = qrlinkRaw.replace(/^"|"$/g,"");
        var qrlink = t(qrlinkRaw);

        // APPROVED KKK OS V1 DISPLAY RULE (2026-09-11):
        // LELAKI    = groom above bride.
        // PEREMPUAN = bride above groom.
        // CSV semantics remain unchanged; only template presentation is side-aware.
        var isPerempuan = (majlis === "PEREMPUAN");

        var namaAtas  = isPerempuan ? namaP : namaL;
        var namaBawah = isPerempuan ? namaL : namaP;
        var singAtas  = isPerempuan ? singP : singL;
        var singBawah = isPerempuan ? singL : singP;

        var namesShort = "";
        if (t(singAtas) !== "") namesShort += t(singAtas);
        if (t(singBawah) !== "") namesShort += (namesShort!=="" ? " & " : "") + t(singBawah);

        // Include side in folder identity so two-package rows can never share
        // the same output folder and accidentally overwrite each other.
        var custFolderNameBase = NoInv + " " + majlis + (namesShort!=="" ? (" " + namesShort) : "") + (t(qty)!=="" ? (" " + t(qty) + " PCS") : "");
        var custFolderSafe = safeName(custFolderNameBase);

        var custRoot = ensureFolderObj(customerParent.fsName + "/" + custFolderSafe);
        var fJPEG = ensureFolderObj(custRoot.fsName + "/Export JPEG");
        var fPNG  = ensureFolderObj(custRoot.fsName + "/Export PNG");
        var fPSD  = ensureFolderObj(custRoot.fsName + "/PSD");
        var fQR   = ensureFolderObj(custRoot.fsName + "/QR");


        var custLogPath = custRoot.fsName + "/_customer_log.txt";
        logAppend(custLogPath, "===== " + nowStamp() + " | " + NoInv + " | " + custFolderSafe + " =====");
        logBoth(logPath, custLogPath, "---- " + nowStamp() + " | " + NoInv + " | " + custFolderSafe + " ----");
        if(gambarRaw !== "" && gambarPath === "") logBoth(logPath, custLogPath, "[IMAGE] Fail gambar pengantin tidak ditemui: " + gambarRaw);

        var templates = findTemplates(MASTER.fsName, tema, code);
        logBoth(logPath, custLogPath, "[TEMPLATE COUNT] " + (templates ? templates.length : 0) + " | Tema: " + tema + " | Code: " + code);
        if(templates && templates.length > 0){
            for(var tli=0; tli<templates.length; tli++){
                logBoth(logPath, custLogPath, "[TEMPLATE FOUND] " + templates[tli]);
            }
        }
        if(!templates || templates.length === 0){
            templateMissingCount++;
            logBoth(logPath, custLogPath,
                "[TEMPLATE MISSING] Tiada PSD untuk Tema='" + tema + "' DesignCode='" + code + "'. " +
                "Expected utama: " + MASTER.fsName + "/" + tema + "/" + code
            );
            continue;
        } else {
            logBoth(logPath, custLogPath, "[TEMPLATE] " + templates.length + " PSD ditemui untuk " + tema + "/" + code);
        }

        var qrFileName = safeName(NoInv + " " + majlis + (namesShort!==""?(" " + namesShort):"")) + "_QR.png";
        var qrOutPath = fQR.fsName + "/" + qrFileName;
        var qrOK = false;
        if(qrlink && qrlink !== ""){
            qrOK = generateQRtoPath(qrlink, qrOutPath);
            if(qrOK) logBoth(logPath, custLogPath, "[QR] OK -> " + qrOutPath);
            else     logBoth(logPath, custLogPath, "[QR] GAGAL -> " + qrlink + " (to " + qrOutPath + ")");
        } else {
            logBoth(logPath, custLogPath, "[QR] TIADA qrlink");
        }

        for(var ti=0; ti<templates.length; ti++){
            var tplPath = templates[ti];
            var doc = null;
            try{
                logBoth(logPath, custLogPath, "[TEMPLATE START] " + tplPath);
                doc = openPSDQuiet(tplPath);
                if(doc == null){
                    openFailCount++;
                    logBoth(logPath, custLogPath, "[OPEN FAIL] " + tplPath);
                    continue;
                }

            // set text layers
            setTextIfExistsInDoc(doc, "namaayah", namaayah);
            setTextIfExistsInDoc(doc, "namaibu", namaibu);

            // Existing template layer positions are kept:
            // "namapengantinlelaki" / "singkatanlelaki" = TOP position
            // "namapengantinperempuan" / "singkatanperempuan" = BOTTOM position
            // Values are swapped only for PEREMPUAN.
            //
            // For Portrait Banner/Banting we need one extra rule:
            // keep template layout, BUT if one short name is visually short,
            // center that individual name on its own original template anchor.
            var singAtasAnchorBefore  = getTextLayerAnchorInfo(doc, "singkatanlelaki");
            var singBawahAnchorBefore = getTextLayerAnchorInfo(doc, "singkatanperempuan");

            setTextIfExistsInDoc(doc, "namapengantinlelaki", namaAtas);
            setTextIfExistsInDoc(doc, "namapengantinperempuan", namaBawah);
            setTextIfExistsInDoc(doc, "singkatanlelaki", singAtas);
            setTextIfExistsInDoc(doc, "singkatanperempuan", singBawah);

            // Layout singkatan:
            // - KAD DEPAN: guna alignment khas sekitar "&".
            // - PORTRAIT BANNER / BANTING:
            //     ikut template 100%, tetapi jika nama singkatan nampak pendek,
            //     center nama tersebut pada anchor asal template.
            // - BANNER / BANTING lain: jangan gerakkan layer singkatan.
            // - Template lain: kekalkan behaviour centering lama.
            var currentTplNameForLayout = (new File(tplPath)).name.toLowerCase();
            try{ currentTplNameForLayout = decodeURI(currentTplNameForLayout); }catch(layoutNameDecodeErr){}

            var isKadDepanLayout = (currentTplNameForLayout.indexOf("kad depan") >= 0);
            var isBannerLayout   = (currentTplNameForLayout.indexOf("banner") >= 0);
            var isBantingLayout  = (currentTplNameForLayout.indexOf("banting") >= 0);
            var isPortraitLayout = isPortraitDesign(tema, tplPath, currentTplNameForLayout);

            if(isKadDepanLayout){
                centerShortNamesAroundAmpersand(doc);
            }else if(isPortraitLayout && (isBannerLayout || isBantingLayout)){
                var shortCenterResult = centerShortNamesForPortraitBannerBanting(
                    doc,
                    singAtas,
                    singBawah,
                    singAtasAnchorBefore,
                    singBawahAnchorBefore
                );
                logBoth(
                    logPath,
                    custLogPath,
                    "[LAYOUT] " + currentTplNameForLayout +
                    " | ikut template portrait | singkatan atas=" + singAtas +
                    (shortCenterResult.topCentered ? " [CENTERED]" : " [AS TEMPLATE]") +
                    " | singkatan bawah=" + singBawah +
                    (shortCenterResult.bottomCentered ? " [CENTERED]" : " [AS TEMPLATE]")
                );
            }else if(isBannerLayout || isBantingLayout){
                // Deliberately do nothing:
                // preserve exact X/Y position, baseline, spacing and composition
                // of singkatanlelaki / singkatanperempuan / "&" from the master template.
            }else{
                centerLayerGroupInDocument(doc, ["singkatanlelaki", "&", "&amp;", "singkatanperempuan"]);
            }
            setTextIfExistsInDoc(doc, "nama1", nama1); setTextIfExistsInDoc(doc, "notel1", notel1);
            setTextIfExistsInDoc(doc, "nama2", nama2); setTextIfExistsInDoc(doc, "notel2", notel2);
            setTextIfExistsInDoc(doc, "nama3", nama3); setTextIfExistsInDoc(doc, "notel3", notel3);

            setTextIfExistsInDoc(doc, "hari",       hari);
            var dateUpdated = setTextIfExistsInDoc(doc, "tarikh", tarikh);
            if(setTextIfExistsInDoc(doc, "tarikh copy", tarikh)) dateUpdated = true;
            if(!dateUpdated) logBoth(logPath, custLogPath, "[DATE] Layer tarikh tidak ditemui dalam " + tplPath);
            setTextIfExistsInDoc(doc, "tarikhhari", tarikhhari);
            setTextIfExistsInDoc(doc, "bulan",      bulan);
            setTextIfExistsInDoc(doc, "bulanislam", bulanislam);

            setTextIfExistsInDoc(doc, "alamat", alamat);

            // PATCH masa dari CSV (exact string)
            setTextIfExistsInDoc(doc, "masabersanding",  t(g(idx("masabersanding"))));
            setTextIfExistsInDoc(doc, "masajamuanmakan", t(g(idx("masajamuanmakan"))));

            if (qrOK) {
                var rep = replaceQRIfExists(doc, qrOutPath);
                if (rep) logBoth(logPath, custLogPath, "[QR] qrlocation replaced & auto-fit.");
                else     logBoth(logPath, custLogPath, "[QR] Layer 'qrlocation' tak jumpa.");
            }

            // Nama fail export (strip _patched jika ada)
            var tplName = (new File(tplPath)).name
                .replace(/\.psd$/i,"")
                .replace(/_patched$/i,"");
            try{ tplName = decodeURI(tplName); }catch(nameDecodeErr){}

            var itemQty = fixedQtyForItem(tplName, qty);
            var tplLower = tplName.toLowerCase();
            var isVipCard = (tplLower.indexOf("vip card") >= 0 || tplLower.indexOf("vipcard") >= 0);
            var isArrowTemplate = (tplLower.indexOf("arrow kanan") >= 0 || tplLower.indexOf("arrow kiri") >= 0);


            // POTRAIT / PORTRAIT DESIGN FAMILY:
            // Do NOT rely on the PSD filename. The family may be identified by Tema
            // or by the parent folder, e.g. POTRAIT/CKP-005/KAD DEPAN.psd.
            var portraitDesign = isPortraitDesign(tema, tplPath, tplName);

            var portraitItem = portraitDesign ? kkkPortraitItem(tplName) : "";
            if (portraitItem !== "") {
                try {
                    var frameResult = kkkInsertFramedPortrait(doc, gambarPath, portraitItem);
                    logBoth(logPath, custLogPath, "[PORTRAIT V33] " + tplName + " | " +
                        LAST_PORTRAIT_IMAGE_METHOD + " | crop=" + frameResult.box.x + "," +
                        frameResult.box.y + "," + frameResult.box.w + "," + frameResult.box.h);
                } catch (portraitError) {
                    portraitFailCount++;
                    logBoth(logPath, custLogPath, "[PORTRAIT V33 FAIL - NO EXPORT] " + tplName + " | " + portraitError);
                    continue; // finally closes this template; never export placeholder customer photo.
                }
            } else if (portraitDesign) {
                logBoth(logPath, custLogPath, "[PORTRAIT V33] NO CUSTOMER PHOTO (outside seven-item scope): " + tplName);
            }

            var outBase = safeName(
                (cardOutputId !== "" ? cardOutputId : NoInv) + " " + majlis + " " + tplName +
                (namesShort!==""?(" " + namesShort):"") +
                (itemQty?(" " + itemQty + " PCS"):"")
            );

            
            if(isVipCard){
                try{
                    var pngOut = fPNG.fsName + "/" + outBase + ".png";
                    if(savePNG(doc, pngOut)){
                        exportSuccessCount++;
                        logBoth(logPath, custLogPath, "[EXPORT] VIP CARD PNG TRANSPARENT -> " + pngOut);
                    } else {
                        logBoth(logPath, custLogPath, "[EXPORT FAIL] VIP CARD PNG -> " + pngOut + " | " + LAST_SAVE_ERROR);
                    }
                }catch(e){
                    logBoth(logPath, custLogPath, "[EXPORT FAIL] VIP CARD PNG -> " + e);
                }
            }else{
                try{
                    var jpegOut = fJPEG.fsName + "/" + outBase + ".jpg";
                    if(saveJPG(doc, jpegOut, 12)){
                        exportSuccessCount++;
                        logBoth(logPath, custLogPath, "[EXPORT] JPEG -> " + jpegOut);
                    } else {
                        logBoth(logPath, custLogPath, "[EXPORT FAIL] JPEG -> " + jpegOut + " | " + LAST_SAVE_ERROR);
                    }
                }catch(e){
                    logBoth(logPath, custLogPath, "[EXPORT FAIL] JPEG -> " + e);
                }
            }


            try{
                var psdOut = fPSD.fsName + "/" + outBase + ".psd";
                if(savePSDdoc(doc, psdOut)){
                    exportSuccessCount++;
                    logBoth(logPath, custLogPath, "[EXPORT] PSD -> " + psdOut);
                } else {
                    logBoth(logPath, custLogPath, "[EXPORT FAIL] PSD -> " + psdOut + " | " + LAST_SAVE_ERROR);
                }
            }catch(e){ logBoth(logPath, custLogPath, "[EXPORT FAIL] PSD -> " + e); }

            // For PORTRAIT/POTRAIT designs the customer image has already been
            // replaced BEFORE the normal export above. Do not create a second
            // template-photo version for Banner/Banting.
            if(!portraitDesign && gambarPath !== "" && (tplLower.indexOf("banner") >= 0 || tplLower.indexOf("banting") >= 0)){
                var isBanner = tplLower.indexOf("banner") >= 0;
                var imageAdded = hasCustomerPhotoLayer(doc) && replaceCardImageIfExists(doc, gambarPath);
                if(!imageAdded) imageAdded = addCustomerPhotoBackground(doc, gambarPath);
                if(imageAdded){
                    var photoBase = safeName(outBase + " GAMBAR PENGANTIN");
                    var photoJpeg = fJPEG.fsName + "/" + photoBase + ".jpg";
                    var photoPsd = fPSD.fsName + "/" + photoBase + ".psd";
                    if(saveJPG(doc, photoJpeg, 12)) logBoth(logPath, custLogPath, "[EXPORT] JPEG gambar pengantin -> " + photoJpeg);
                    else logBoth(logPath, custLogPath, "[EXPORT FAIL] JPEG gambar pengantin -> " + photoJpeg + " | " + LAST_SAVE_ERROR);
                    if(savePSDdoc(doc, photoPsd)) logBoth(logPath, custLogPath, "[EXPORT] PSD gambar pengantin -> " + photoPsd);
                    else logBoth(logPath, custLogPath, "[EXPORT FAIL] PSD gambar pengantin -> " + photoPsd + " | " + LAST_SAVE_ERROR);
                }else{
                    logBoth(logPath, custLogPath, "[IMAGE] Gagal memasukkan gambar pengantin untuk " + tplName);
                }
            }

                logBoth(logPath, custLogPath, "[TEMPLATE DONE] " + tplPath);
            }catch(templateErr){
                logBoth(logPath, custLogPath, "[TEMPLATE ERROR] " + tplPath + " | " + templateErr);
            }finally{
                if(doc) closeDocNoSave(doc);
            }
        }

        logBoth(logPath, custLogPath, "---- DONE " + NoInv + " ----");
    }

    logAppend(logPath, "===== " + nowStamp() + " | END BATCH: " + batchName + " | exports=" + exportSuccessCount + " | templateMissing=" + templateMissingCount + " | openFail=" + openFailCount + " | cekFail=" + cekFailCount + " =====");

    if(exportSuccessCount === 0){
        alert(
            "TIADA OUTPUT DIHASILKAN.\n\n" +
            "Kemungkinan utama: template PSD tidak ditemui atau gagal dibuka.\n" +
            "Template missing: " + templateMissingCount + "\n" +
            "Open fail: " + openFailCount + "\n" +
            "Item POTRAIT gagal/dilangkau: " + portraitFailCount + "\n\n" +
            "Semak log:\n" + logPath + "\n\n" +
            "MASTER digunakan:\n" + MASTER.fsName
        );
    } else if(cekFailCount > 0){
        alert("PERHATIAN: Mockup Cek Hantaran gagal atau tidak lengkap. Semak log: " + logPath);
    } else {
        alert(
            "Selesai.\n" +
            "Jumlah fail berjaya diexport: " + exportSuccessCount + "\n" +
            "Item POTRAIT gagal/dilangkau: " + portraitFailCount + "\n\n" +
            "Semak folder: " + batchFolder.fsName + "\n" +
            "Log: " + logPath
        );
    }

}catch(err){
    alert("Ralat: " + err);
}finally{
    app.displayDialogs = KKK_ORIGINAL_DIALOGS;
    app.preferences.rulerUnits = KKK_ORIGINAL_UNITS;
}
