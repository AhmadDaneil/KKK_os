/*
KKK OS V1 - Stage 9B Photopea Acceptance Adapter v0.1
Fixture: KKK-260909-0006_READY_TO_MERGE.csv

Purpose:
- Prove KKK OS CSV values can drive real Photopea PSD text layers.
- Replace qrlocation Smart Object using qrlink.
- Does NOT yet auto-export JPG/PSD.
- Does NOT yet parse a local CSV file inside Photopea.
*/

(function () {
    var DATA = {
  "noinvoice": "KKK-260909-0006",
  "qtykad": "200",
  "tema": "Songket",
  "designcode": "CKS-218",
  "gambar": "",
  "majlis": "LELAKI",
  "namapengantinlelaki": "Mad Dane",
  "namapengantinperempuan": "Joy Heong Hyo",
  "singkatanlelaki": "Mad",
  "singkatanperempuan": "Joy",
  "namaayah": "Wari Bin Ramelan",
  "namaibu": "Rafeah Binti Saat",
  "hari": "RABU",
  "tarikh": "30 SEPTEMBER 2026",
  "tarikhhari": "30",
  "bulan": "SEP 2026",
  "bulanislam": "18 RABIULAKHIR 1448H",
  "masabersanding": "13:00:00",
  "masajamuanmakan": "10:00:00",
  "alamat": "Dewan Semai Bakti Felda Pemanis 1, Felda Pemanis 1, Segamat, 85009, Johor",
  "qrlink": "https://maps.app.goo.gl/example",
  "nama1": "Ahmad",
  "notel1": "0123456789",
  "nama2": "Iqbal",
  "notel2": "0101234567",
  "nama3": "Joy Crookes",
  "notel3": "0182341234",
  "flaggambar": ""
};

    var report = [];
    var changed = 0;
    var missing = [];

    function log(msg) {
        report.push(msg);
    }

    function lower(s) {
        return String(s || "").toLowerCase();
    }

    function findLayerRecursive(container, targetName) {
        if (!container || !container.layers) return null;

        for (var i = 0; i < container.layers.length; i++) {
            var layer = container.layers[i];

            if (lower(layer.name) === lower(targetName)) {
                return layer;
            }

            if (layer.layers && layer.layers.length > 0) {
                var found = findLayerRecursive(layer, targetName);
                if (found) return found;
            }
        }
        return null;
    }

    function setTextIfExists(doc, layerName, value) {
        if (value === null || value === undefined || String(value) === "") {
            return false;
        }

        var layer = findLayerRecursive(doc, layerName);
        if (!layer) return false;

        try {
            layer.textItem.contents = String(value);
            changed++;
            log(doc.name + " :: " + layerName + " = " + value);
            return true;
        } catch (e) {
            log("WARN text failed: " + doc.name + " :: " + layerName + " :: " + e.toString());
            return false;
        }
    }

    function numericValue(v) {
        if (v === null || v === undefined) return 0;
        if (typeof v === "number") return v;
        if (typeof v.value === "number") return v.value;
        var n = parseFloat(String(v));
        return isNaN(n) ? 0 : n;
    }

    function fitActiveLayerToCanvas(doc) {
        try {
            var layer = doc.activeLayer;
            var b = layer.bounds;

            var left = numericValue(b[0]);
            var top = numericValue(b[1]);
            var right = numericValue(b[2]);
            var bottom = numericValue(b[3]);

            var w = right - left;
            var h = bottom - top;
            var dw = numericValue(doc.width);
            var dh = numericValue(doc.height);

            if (w <= 0 || h <= 0 || dw <= 0 || dh <= 0) {
                log("WARN QR fit skipped: invalid bounds");
                return;
            }

            var scale = Math.min(dw / w, dh / h) * 100;

            try {
                layer.resize(scale, scale, AnchorPosition.MIDDLECENTER);
            } catch (resizeError) {
                log("WARN QR resize not supported; inserted QR left at Photopea placement size.");
                return;
            }

            // Re-read bounds after resize and center it.
            b = layer.bounds;
            left = numericValue(b[0]);
            top = numericValue(b[1]);
            right = numericValue(b[2]);
            bottom = numericValue(b[3]);

            var cx = (left + right) / 2;
            var cy = (top + bottom) / 2;
            var dcx = dw / 2;
            var dcy = dh / 2;

            layer.translate(dcx - cx, dcy - cy);
            log("QR fitted to Smart Object canvas.");
        } catch (e) {
            log("WARN QR fit failed: " + e.toString());
        }
    }

    function updateQr() {
        if (!DATA.qrlink) {
            log("QR SKIPPED: qrlink empty.");
            return false;
        }

        var parentDoc = null;
        var qrLayer = null;

        for (var d = 0; d < app.documents.length; d++) {
            var doc = app.documents[d];
            var candidate = findLayerRecursive(doc, "qrlocation");
            if (candidate) {
                parentDoc = doc;
                qrLayer = candidate;
                break;
            }
        }

        if (!parentDoc || !qrLayer) {
            log("QR FAILED: qrlocation not found in any open document.");
            return false;
        }

        app.activeDocument = parentDoc;
        parentDoc.activeLayer = qrLayer;

        executeAction(stringIDToTypeID("placedLayerEditContents"));

        var qrDoc = app.activeDocument;
        var qrUrl =
            "https://api.qrserver.com/v1/create-qr-code/" +
            "?size=1200x1200&data=" + encodeURIComponent(DATA.qrlink);

        app.open(qrUrl, null, true);

        fitActiveLayerToCanvas(qrDoc);

        qrDoc.save();
        qrDoc.close();

        log("QR updated in " + parentDoc.name + " from qrlink.");
        return true;
    }

    try {
        if (!app.documents || app.documents.length === 0) {
            alert("KKK PHOTOPEA ADAPTER\n\nFAIL: Tiada PSD terbuka.");
            return;
        }

        var mappings = [
            ["namapengantinlelaki", DATA.namapengantinlelaki],
            ["namapengantinperempuan", DATA.namapengantinperempuan],
            ["singkatanlelaki", DATA.singkatanlelaki],
            ["singkatanperempuan", DATA.singkatanperempuan],
            ["namaayah", DATA.namaayah],
            ["namaibu", DATA.namaibu],
            ["hari", DATA.hari],
            ["tarikh", DATA.tarikh],
            ["tarikhhari", DATA.tarikhhari],
            ["bulan", DATA.bulan],
            ["bulanislam", DATA.bulanislam],
            ["masabersanding", DATA.masabersanding],
            ["masajamuanmakan", DATA.masajamuanmakan],
            ["alamat", DATA.alamat],
            ["nama1", DATA.nama1],
            ["notel1", DATA.notel1],
            ["nama2", DATA.nama2],
            ["notel2", DATA.notel2],
            ["nama3", DATA.nama3],
            ["notel3", DATA.notel3]
        ];

        for (var m = 0; m < mappings.length; m++) {
            var layerName = mappings[m][0];
            var value = mappings[m][1];
            var foundSomewhere = false;

            for (var d = 0; d < app.documents.length; d++) {
                if (setTextIfExists(app.documents[d], layerName, value)) {
                    foundSomewhere = true;
                }
            }

            if (!foundSomewhere && value !== null && value !== undefined && String(value) !== "") {
                missing.push(layerName);
            }
        }

        var qrOk = updateQr();

        var summary =
            "KKK PHOTOPEA ADAPTER v0.1\n\n" +
            "Order: " + DATA.noinvoice + "\n" +
            "Tema: " + DATA.tema + "\n" +
            "DesignCode: " + DATA.designcode + "\n" +
            "Side: " + DATA.majlis + "\n" +
            "QtyKad: " + DATA.qtykad + "\n\n" +
            "Text updates: " + changed + "\n" +
            "QR: " + (qrOk ? "UPDATED" : "FAILED") + "\n" +
            "Missing/non-matching fields: " + missing.length + "\n\n";

        if (missing.length > 0) {
            summary += "Missing: " + missing.join(", ") + "\n\n";
        }

        summary +=
            "NEXT: Periksa KAD DEPAN + KAD BELAKANG secara visual.\n" +
            "Jangan mark Stage 9B PASS lagi.";

        alert(summary);

    } catch (e) {
        alert(
            "KKK PHOTOPEA ADAPTER v0.1\n\nERROR:\n" +
            e.toString() +
            "\n\nProgress log:\n" +
            report.join("\n")
        );
    }
})();
