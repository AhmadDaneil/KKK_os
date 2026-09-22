/* auto_kad_full_qr_patched.jsx
   Full pipeline: Auto Kad Full (patched)
   - Choose ROOT (KING KAD KAHWIN)
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
function saveJPG(doc, outPath, quality){
    try{
        var opts = new JPEGSaveOptions(); opts.quality = quality;
        doc.saveAs(new File(outPath), opts, true, Extension.LOWERCASE);
        return true;
    }catch(e){ return false; }
}
function savePSDdoc(doc, outPath){
    try{
        var opts = new PhotoshopSaveOptions(); opts.layers = true;
        doc.saveAs(new File(outPath), opts, true);
        return true;
    }catch(e){ return false; }
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
function findTemplates(rootPath, tema, designCode){
    var list = [];
    try{
        function pushIfOk(f){
            var nm = f.name.toLowerCase();
            if (nm.indexOf("_patched") >= 0) return; // ABAIKAN fail patched
            if (nm.indexOf("copy") >= 0) return;     // elak salinan
            list.push(f.fsName);
        }
        var temaFolder = new Folder(rootPath + "/MASTER/" + tema);
        if(!temaFolder.exists) return list;

        var designFolder = new Folder(temaFolder.fsName + "/" + designCode);
        if(designFolder.exists){
            var files = designFolder.getFiles("*.psd");
            for(var i=0;i<files.length;i++) pushIfOk(files[i]);
            return list;
        }
        var all = temaFolder.getFiles("*.psd");
        for(var j=0;j<all.length;j++){
            var nm = all[j].name.toLowerCase();
            if(nm.indexOf(designCode.toLowerCase()) >= 0) pushIfOk(all[j]);
        }
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
    var root = Folder.selectDialog("Pilih ROOT folder (contoh: KING KAD KAHWIN)");
    if(!root){ alert("Dibatalkan."); throw "User cancelled"; }
    var ROOT = root.fsName;

    var MASTER = new Folder(ROOT + "/MASTER");
    if(!MASTER.exists){ alert("Folder MASTER tidak ditemui dalam root. Sila pastikan struktur ROOT/MASTER ada."); throw "MASTER missing"; }
    var OUTPUT = new Folder(ROOT + "/OUTPUT"); if(!OUTPUT.exists) OUTPUT.create();

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

    for(var r=1; r<rows.length; r++){
        var row = rows[r]; if(!row || row.length === 0) continue;
        function g(i){ return (i>=0 && i<row.length) ? row[i] : ""; }

        var NoInv = t(g(iNoInv)); if(NoInv === "") continue;
        var qty = t(g(iQty));
        var tema = t(g(iTema));
        var code = t(g(iCode));
        var majlis = t(g(iMajlis)).toUpperCase();

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

        var templates = findTemplates(ROOT, tema, code);
        if(!templates || templates.length === 0){
            logBoth(logPath, custLogPath, "[TEMPLATE MISSING] Tema: " + tema + " Code: " + code);
            continue;
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
            var doc = openPSDQuiet(tplPath);
            if(doc == null){ logBoth(logPath, custLogPath, "[OPEN FAIL] " + tplPath); continue; }

            // set text layers
            setTextIfExistsInDoc(doc, "namaayah", namaayah);
            setTextIfExistsInDoc(doc, "namaibu", namaibu);

            // Existing template layer positions are kept:
            // "namapengantinlelaki" / "singkatanlelaki" = TOP position
            // "namapengantinperempuan" / "singkatanperempuan" = BOTTOM position
            // Values are swapped only for PEREMPUAN.
            setTextIfExistsInDoc(doc, "namapengantinlelaki", namaAtas);
            setTextIfExistsInDoc(doc, "namapengantinperempuan", namaBawah);
            setTextIfExistsInDoc(doc, "singkatanlelaki", singAtas);
            setTextIfExistsInDoc(doc, "singkatanperempuan", singBawah);
            setTextIfExistsInDoc(doc, "nama1", nama1); setTextIfExistsInDoc(doc, "notel1", notel1);
            setTextIfExistsInDoc(doc, "nama2", nama2); setTextIfExistsInDoc(doc, "notel2", notel2);
            setTextIfExistsInDoc(doc, "nama3", nama3); setTextIfExistsInDoc(doc, "notel3", notel3);

            setTextIfExistsInDoc(doc, "hari",       hari);
            setTextIfExistsInDoc(doc, "tarikh",     tarikh);
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

            var itemQty = fixedQtyForItem(tplName, qty);
            var outBase = safeName(
                NoInv + " " + majlis + " " + tplName +
                (namesShort!==""?(" " + namesShort):"") +
                (itemQty?(" " + itemQty + " PCS"):"")
            );

            
            try{
    var jpegOut = fJPEG.fsName + "/" + outBase + ".jpg";
    saveJPG(doc, jpegOut, 12);
    logBoth(logPath, custLogPath, "[EXPORT] JPEG -> " + jpegOut);
}catch(e){ 
    logBoth(logPath, custLogPath, "[EXPORT FAIL] JPEG -> " + e); 
}


            try{
                var psdOut = fPSD.fsName + "/" + outBase + ".psd";
                savePSDdoc(doc, psdOut);
                logBoth(logPath, custLogPath, "[EXPORT] PSD -> " + psdOut);
            }catch(e){ logBoth(logPath, custLogPath, "[EXPORT FAIL] PSD -> " + e); }

            closeDocNoSave(doc);
        }

        logBoth(logPath, custLogPath, "---- DONE " + NoInv + " ----");
    }

    logAppend(logPath, "===== " + nowStamp() + " | END BATCH: " + batchName + " =====");
    alert("Selesai. Semak folder: " + batchFolder.fsName + "\nLog: " + logPath);

}catch(err){
    alert("Ralat: " + err);
}
