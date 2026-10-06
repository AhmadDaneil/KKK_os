/* auto_kad_full_qr_patched.jsx
   Full pipeline: Auto Kad Full (patched V23 - ask ROOT + robust template discovery + export validation + POTRAIT customer image)
   - Ask operator to choose ROOT folder
   - MASTER template path = <ROOT>/MASTER
   - OUTPUT path = <ROOT>/OUTPUT
   - Choose CSV (Ready to merge)
   - For each CSV row:
       • find templates in MASTER/<Tema/<DesignCode>/*.psd
       • open each template, update text layers (namaayah, namaibu, alamat, HARI/TARIKH/BULAN)
       • generate QR PNG from qrlink and save to customer/QR
       • REPLACE Smart Object "qrlocation" dengan QR PNG (auto-convert kalau bukan SO) + AUTO-FIT
       • export DRAFT (jpg), PRINT (jpg), PSD
   - Write log to OUTPUT/_customer_log.txt + mirror log dalam folder customer
   Notes:
     - Legacy ExtendScript friendly (tiada .trim(), .filter()).
     - CSV parser handle quoted commas/newlines.
     - Guna PowerShell untuk download QR (Windows).
*/

#target photoshop
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

function findPortraitImageLayer(doc){
    // 1) Exact layer names first.
    var exact = findLayerByNames(doc, [
        "potrait", "portrait", "potret",
        "gambar potrait", "gambar portrait", "gambar potret",
        "potrait picture", "portrait picture", "potret picture",
        "gambar customer", "customer photo", "customer image",
        "gambar", "cardimage", "card_image", "gambar pengantin", "pengantin", "photo", "image"
    ]);
    if(exact) return exact;

    // 2) Then accept common variants such as "Gambar Pengantin Copy",
    //    "Portrait Photo 1", "GAMBAR CUSTOMER", etc.
    var keywords = [
        "potrait", "portrait", "potret",
        "gambar pengantin", "gambar customer",
        "customer photo", "customer image",
        "cardimage", "card_image", "photo pengantin"
    ];

    function walk(container){
        for(var i=0;i<container.layers.length;i++){
            var L = container.layers[i];
            var nm = s(L.name).toLowerCase();

            // Never treat the QR layer as a portrait image.
            if(nm !== "qrlocation"){
                for(var k=0;k<keywords.length;k++){
                    if(nm.indexOf(keywords[k]) >= 0) return L;
                }
            }

            if(L.typename === "LayerSet"){
                var nested = walk(L);
                if(nested) return nested;
            }
        }
        return null;
    }

    var fuzzy = walk(doc);
    if(fuzzy) return fuzzy;

    // 3) Conservative fallback:
    //    If there is exactly ONE non-QR Smart Object in the whole template,
    //    use it as the portrait placeholder. Do not guess when there are many.
    var candidates = [];
    function collectSO(container){
        for(var j=0;j<container.layers.length;j++){
            var X = container.layers[j];
            if(X.typename === "LayerSet"){
                collectSO(X);
            }else{
                try{
                    var xn = s(X.name).toLowerCase();
                    if(X.kind == LayerKind.SMARTOBJECT && xn !== "qrlocation"){
                        candidates.push(X);
                    }
                }catch(e){}
            }
        }
    }
    collectSO(doc);
    return candidates.length === 1 ? candidates[0] : null;
}

function replacePortraitImageIfExists(doc, imagePath){
    if(!imagePath || t(imagePath) === "") return false;

    var imageLayer = findPortraitImageLayer(doc);
    if(!imageLayer) return false;

    try{
        imageLayer = convertToSmartObjectIfNeeded(imageLayer);
        var targetBox = _boundsPx(imageLayer);

        if(!replaceSmartObjectContents(imageLayer, imagePath)) return false;

        fitLayerToBox(imageLayer, targetBox);
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
        photoDoc = app.open(new File(imagePath));
        var photoLayer = photoDoc.activeLayer.duplicate(doc, ElementPlacement.PLACEATBEGINNING);
        closeDocNoSave(photoDoc);
        photoDoc = null;

        app.activeDocument = doc;
        doc.activeLayer = photoLayer;
        photoLayer.name = "Gambar Pengantin";

        var width = doc.width.as("px");
        var height = doc.height.as("px");
        var current = _boundsPx(photoLayer);
        if(current.w <= 0 || current.h <= 0) return false;
        var scale = Math.max(width / current.w, height / current.h) * 100;
        _transformScalePercent(scale);
        current = _boundsPx(photoLayer);
        _translate((width / 2) - current.cx, (height / 2) - current.cy);
        bringTextGroupsToFront(doc, photoLayer);
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

// ------------------ Main Flow ------------------
try{
    // Ask operator to select the ROOT folder for this run.
    // Expected structure:
    // <ROOT>/
    //   MASTER/<Tema>/<DesignCode>/*.psd
    //   OUTPUT/
    //
    // Example on KKK laptop:
    // ROOT   = C:\KAD KAHWIN\MASTER
    // MASTER = C:\KAD KAHWIN\MASTER\MASTER
    // OUTPUT = C:\KAD KAHWIN\MASTER\OUTPUT
    var root = Folder.selectDialog("Pilih ROOT folder KKK (folder yang mengandungi MASTER)");
    if(!root){
        alert("Dibatalkan.");
        throw "User cancelled";
    }
    var ROOT = root.fsName;

    var MASTER = new Folder(ROOT + "/MASTER");
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

    var logPath = OUTPUT.fsName + "/_customer_log.txt";
    logAppend(logPath, "===== " + nowStamp() + " | START BATCH: " + batchName + " =====");

    var exportSuccessCount = 0;
    var templateMissingCount = 0;
    var openFailCount = 0;

    for(var r=1; r<rows.length; r++){
        var row = rows[r]; if(!row || row.length === 0) continue;
        function g(i){ return (i>=0 && i<row.length) ? row[i] : ""; }

        var NoInv = t(g(iNoInv)); if(NoInv === "") continue;
        var qty = t(g(iQty));
        var tema = t(g(iTema));
        var code = t(g(iCode));
        var majlis = t(g(iMajlis)).toUpperCase();
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


            // POTRAIT / PORTRAIT DESIGN FAMILY:
            // Do NOT rely on the PSD filename. The family may be identified by Tema
            // or by the parent folder, e.g. POTRAIT/CKP-005/KAD DEPAN.psd.
            var portraitDesign = isPortraitDesign(tema, tplPath, tplName);

            if(portraitDesign){
                var skipPortraitCustomerImage = (tplLower.indexOf("wooden hanger") >= 0);

                if(skipPortraitCustomerImage){
                    logBoth(logPath, custLogPath, "[POTRAIT] SKIP CUSTOMER IMAGE FOR WOODEN HANGER -> " + tplName);
                }else if(gambarPath !== ""){
                    var portraitReplaced = replacePortraitImageIfExists(doc, gambarPath);
                    if(portraitReplaced){
                        logBoth(logPath, custLogPath, "[POTRAIT] CUSTOMER IMAGE OK -> " + tplName);
                    }else{
                        // Important: do not silently claim success.
                        // The template may genuinely have no portrait image layer
                        // (e.g. some arrows/stickers), so continue but log clearly.
                        logBoth(logPath, custLogPath, "[POTRAIT] NO REPLACEABLE CUSTOMER IMAGE LAYER -> " + tplName);
                    }
                }else{
                    logBoth(logPath, custLogPath, "[POTRAIT] CUSTOMER IMAGE MISSING -> " + tplName);
                }
            }

            var outBase = safeName(
                NoInv + " " + majlis + " " + tplName +
                (namesShort!==""?(" " + namesShort):"") +
                (itemQty?(" " + itemQty + " PCS"):"")
            );

            
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

    logAppend(logPath, "===== " + nowStamp() + " | END BATCH: " + batchName + " | exports=" + exportSuccessCount + " | templateMissing=" + templateMissingCount + " | openFail=" + openFailCount + " =====");

    if(exportSuccessCount === 0){
        alert(
            "TIADA OUTPUT DIHASILKAN.\n\n" +
            "Kemungkinan utama: template PSD tidak ditemui atau gagal dibuka.\n" +
            "Template missing: " + templateMissingCount + "\n" +
            "Open fail: " + openFailCount + "\n\n" +
            "Semak log:\n" + logPath + "\n\n" +
            "MASTER digunakan:\n" + MASTER.fsName
        );
    } else {
        alert(
            "Selesai.\n" +
            "Jumlah fail berjaya diexport: " + exportSuccessCount + "\n\n" +
            "Semak folder: " + batchFolder.fsName + "\n" +
            "Log: " + logPath
        );
    }

}catch(err){
    alert("Ralat: " + err);
}
