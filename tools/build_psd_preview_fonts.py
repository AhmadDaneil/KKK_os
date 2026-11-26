"""Build live-preview font CSS from the visibility-aware PSD audit."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any


FONT_FILES = {
    "Perpetua": ("perpetua.woff", "400"),
    "AmalfiCoast": ("amalficoast.woff", "400"),
    "MomsHand": ("momshand.woff", "400"),
    "GreatVibes-Regular": ("greatvibes-regular.woff", "400"),
    "ChopinScript": ("chopinscript.woff", "400"),
    "BerastagiDEMO-Regular": ("berastagidemo-regular.woff", "400"),
    "Montserrat-Regular": ("montserrat-regular.woff", "100 900"),
    "Belladonia": ("belladonia.woff", "400"),
    "WeddingNightRegular": ("wedding-night.woff", "400"),
    "Acardia-Regular": ("acardia-demo.woff", "400"),
    "BaskOldFace": ("baskerville-old-face.woff", "400"),
    "Baskervville": ("baskervville-variable.woff", "400 700"),
    "Baskerville-Regular": ("baskerville-regular.woff", "400"),
    "Baskerville-SemiBold": ("baskerville-semibold.woff", "600"),
    "SantillanaDaughter-Regular": ("santillana-daughter.woff", "400"),
    "SpecialNightDemoRegular": ("special-night-demo.woff", "400"),
    "AllustDEMO-Regular": ("allust-demo.woff", "400"),
    "HandBoys": ("hand-boys.woff", "400"),
    "MyriadPro-Regular": ("myriad-pro-regular.woff", "400"),
    "MyriadPro-Bold": ("myriad-pro-bold.woff", "700"),
    "Calibri-Bold": ("calibri-bold.woff", "700"),
    "AgencyFB-Reg": ("agency-fb-regular.woff", "400"),
    "PalatinoLinotype-Roman": ("palatino-linotype-regular.woff", "400"),
    "TimesNewRomanPSMT": ("times-new-roman.woff", "400"),
    "CenturyGothic": ("century-gothic.woff", "400"),
    "MonotypeCorsiva": ("monotype-corsiva.woff", "400"),
}
SLOTS = {"kad depan": "card-front", "banner": "banner", "banting": "bunting", "sticker": "sticker",
         "arrow kiri": "arrow-left", "arrow kanan": "arrow-right", "wooden hanger": "wooden-hanger", "vip card": "vip-card"}


def resolve_font(original: str) -> str:
    # These two replacements were explicitly requested for the preview.
    if original.startswith("Breathing"):
        return "GreatVibes-Regular"
    if original == "Heartway":
        return "Acardia-Regular"
    if original.startswith("Montserrat"):
        return "Montserrat-Regular"
    if original.lower() == "weddingnightregular":
        return "WeddingNightRegular"
    if original == "Baskervville-SemiBold":
        return "Baskervville"
    if original not in FONT_FILES:
        raise ValueError("Missing source font asset mapping: " + original)
    return original


def font_weight(original: str, runs: list[dict[str, Any]]) -> int:
    if "SemiBold" in original:
        weight = 600
    elif "Bold" in original:
        weight = 700
    elif "Medium" in original:
        weight = 500
    else:
        weight = 400
    if runs and max(runs, key=lambda run: len(run["text"])).get("weight") == "bold":
        weight = max(weight, 700)
    return weight


def fields_for_item(selected: dict[str, Any], slot: str = "") -> dict[str, Any]:
    def pick(*names: str) -> Any:
        return next((selected[name] for name in names if name in selected), None)

    if slot == "vip-card":
        fields = {
            "groom_name": pick("namapengantinlelaki"),
            "bride_name": pick("namapengantinperempuan"),
            "father_name": pick("namaayah"),
            "mother_name": pick("namaibu"),
            "day_name_malay": pick("hari"),
            "event_date_day": pick("tarikhhari"),
            "event_date_month_year": pick("bulan"),
            "meal_time": pick("masajamuanmakan"),
            "bersanding_time": pick("masabersanding"),
            "venue_name": pick("alamat"),
            "full_address": pick("alamat"),
        }
        return {field: value for field, value in fields.items() if value is not None}

    fields = {
        "groom_display": pick("singkatanlelaki", "namapengantinlelaki", "nama anak", "namaanak"),
        "bride_display": pick("singkatanperempuan", "namapengantinperempuan", "nama anak", "namaanak"),
        "day_name_malay": pick("hari"),
        "event_date": pick("tarikh", "tarikh copy"),
        "card_title_malay": pick("majlis perkahwinan", "majlis perkahwinan copy", "walimatul urus",
                                 "majlis akikah dan kesyukuran", "majlis akikah", "wedding ceremony",
                                 "ke majlis perkahwinan"),
    }
    return {field: value for field, value in fields.items() if value is not None}


def field_selector(code: str, slot: str, field: str) -> str:
    if slot in ("banner", "bunting"):
        root = '.live-product.live-product--' + ("banting" if slot == "bunting" else "banner")
        root += '[data-font-design-code="' + code + '"]'
        selectors = {
            "groom_display": ".desa-product-copy-couple > span:first-child",
            "bride_display": ".desa-product-copy-couple > span:last-child",
            "day_name_malay": ".desa-product-copy-date > span:first-child",
            "event_date": ".desa-product-copy-date > span:last-child",
            "card_title_malay": ".desa-product-copy-subtitle" if slot == "banner" else ".desa-product-copy-kicker",
        }
        return root + " " + selectors[field]
    root = ".wedding-card-front" if slot == "card-front" else '.live-product[data-preview-artwork-slot="' + slot + '"]'
    return root + '[data-font-design-code="' + code + '"] [data-preview-field="' + field + '"]'


def horizontal_name_anchors(product: dict[str, Any], fields: dict[str, Any], slot: str) -> list[dict[str, Any]] | None:
    if slot not in {"card-front", "arrow-left", "arrow-right", "bunting", "sticker", "banner"}:
        return None
    amp = product["selected"].get("&")
    if not amp:
        return None
    boxes = [fields[field]["rect"] for field in ("groom_display", "bride_display")]
    centers = [((box[1] + box[3]) / 2, (box[0] + box[2]) / 2) for box in boxes]
    if abs(centers[0][1] - centers[1][1]) >= max(box[2] - box[0] for box in boxes) * 0.65:
        return None
    top, left, bottom, right = amp["rect"]
    if not min(center[0] for center in centers) < (left + right) / 2 < max(center[0] for center in centers):
        return None
    if abs((top + bottom) / 2 - sum(center[1] for center in centers) / 2) > max(box[2] - box[0] for box in boxes):
        return None
    canvas_width = product["width"]
    gap = max(canvas_width * 0.015, min((right - left) * 0.75, canvas_width * 0.05))
    left_edge, right_edge = left - gap, right + gap
    width = min(left_edge, canvas_width - right_edge) - canvas_width * 0.05
    if width <= 0:
        return None
    return [{"x": left_edge if center[0] < (left + right) / 2 else right_edge,
             "y": (top + bottom) / 2, "width": width,
             "align": "right" if center[0] < (left + right) / 2 else "left"} for center in centers]


def vertical_name_anchors(product: dict[str, Any], fields: dict[str, Any], slot: str) -> list[dict[str, Any]] | None:
    if slot not in {"card-front", "arrow-left", "arrow-right", "bunting", "sticker", "banner"}:
        return None
    amp = product["selected"].get("&")
    if not amp:
        return None
    boxes = [fields[field]["rect"] for field in ("groom_display", "bride_display")]
    centers = [((box[1] + box[3]) / 2, (box[0] + box[2]) / 2) for box in boxes]
    if abs(centers[0][1] - centers[1][1]) < max(box[2] - box[0] for box in boxes) * 0.65:
        return None
    top, left, bottom, right = amp["rect"]
    if not min(center[1] for center in centers) < (top + bottom) / 2 < max(center[1] for center in centers):
        return None
    canvas_width, canvas_height = product["width"], product["height"]
    x = (left + right) / 2
    width = min(max(box[3] - box[1] for box in boxes) / 0.88,
                2 * (min(x, canvas_width - x) - canvas_width * 0.05))
    if width <= 0:
        return None
    gap = max(canvas_height * 0.0025, min((bottom - top) * 0.06, canvas_height * 0.0075))
    other_boxes = [value["rect"] for field, value in fields.items()
                   if field not in {"groom_display", "bride_display"}]
    upper_limit = max([0] + [box[2] + canvas_height * 0.005 for box in other_boxes if box[2] < top])
    lower_limit = min([canvas_height] + [box[0] - canvas_height * 0.005 for box in other_boxes if box[0] > bottom])
    anchors = []
    for box, center in zip(boxes, centers):
        above = center[1] < (top + bottom) / 2
        y = top - gap if above else bottom + gap
        height = min(box[2] - box[0], y - upper_limit if above else lower_limit - y)
        if height <= 0:
            return None
        anchors.append({"x": x, "y": y, "width": width, "height": height,
                        "align": "center", "vertical_align": "bottom" if above else "top"})
    return anchors


def build(catalog: dict[str, Any]) -> tuple[str, str, list[dict[str, Any]]]:
    common = ["/* Generated from visible PSD text runs, including group visibility and duplicate-layer review. */"]
    for name, (filename, weight) in FONT_FILES.items():
        common.append('@font-face { font-family: "PSD ' + name + '"; src: url("../fonts/psd-preview/' + filename
                      + '") format("woff"); font-style: normal; font-weight: ' + weight + '; font-display: swap; }')
    common.append(".wedding-card-front[data-design-artwork], .live-product[data-design-artwork] { container-type: inline-size; }")
    items = ["/* Item font mappings from the source PSD layers. */"]
    report = []
    geometry = []
    for code, design in sorted(catalog.items()):
        for item, product in design["items"].items():
            for conflict in product.get("conflicts", []):
                if conflict["reason"] == "mixed text fonts" and conflict["field"] in {
                    "singkatanlelaki", "singkatanperempuan", "namapengantinlelaki", "namapengantinperempuan",
                    "nama anak", "namaanak", "hari", "tarikh", "tarikh copy", "majlis perkahwinan",
                    "majlis perkahwinan copy", "walimatul urus", "ke majlis perkahwinan",
                }:
                    raise ValueError(f"Review mixed-font text before building: {code}/{item}/{conflict['field']}")
            if item == "kad belakang":
                aliases = {"groom_name": "namapengantinlelaki", "bride_name": "namapengantinperempuan",
                           "father_name": "namaayah", "mother_name": "namaibu", "day_name_malay": "hari",
                           "event_date_day": "tarikhhari", "event_date_month_year": "bulan", "hijri_date": "bulanislam",
                           "meal_time": "masajamuanmakan", "bersanding_time": "masabersanding",
                           "venue_name": "alamat", "full_address": "alamat"}
                for index in range(1, 4):
                    aliases["contact_" + str(index) + "_name"] = "nama" + str(index)
                    aliases["contact_" + str(index) + "_phone"] = "notel" + str(index)
                for field, alias in aliases.items():
                    source = product["selected"].get(alias)
                    runs = source.get("runs", []) if source else []
                    size = max(runs, key=lambda run: len(run["text"])).get("pixel_size", 0) if runs else 0
                    if size > 0:
                        items.append('.wedding-card-back[data-design-code="' + code + '"] [data-preview-field="' + field + '"] { font-size: '
                                     + format(size / product["width"] * 100, ".8g") + 'cqw !important; }')
                continue
            slot = SLOTS[item]
            fields = fields_for_item(product["selected"], slot)
            name_fields = [field for field in ("groom_display", "bride_display") if field in fields]
            name_sizes = [run.get("pixel_size", 0) for field in name_fields for run in fields[field].get("runs", []) if run.get("pixel_size", 0) > 0]
            shared_size = min(name_sizes) if name_sizes else 0
            for field, source in fields.items():
                original = source["font"]
                font = resolve_font(original)
                runs = source.get("runs", [])
                weight = font_weight(original, runs)
                primary_run = max(runs, key=lambda run: len(run["text"])) if runs else {}
                italic = bool(primary_run.get("italic"))
                tracking = float(primary_run.get("tracking", 0))
                family = '"PSD ' + font + '"' + (', "PSD Montserrat-Regular"' if font == "Acardia-Regular" else "") + ", serif"
                selector = field_selector(code, slot, field)
                rule = selector + " { font-family: " + family + "; font-weight: " + str(weight)
                rule += "; font-style: " + ("italic" if italic else "normal")
                pixel_size = shared_size if field in name_fields else primary_run.get("pixel_size", 0)
                if pixel_size > 0:
                    rule += "; --psd-font-size: " + format(pixel_size / product["width"] * 100, ".8g")
                    rule += "; font-size: " + format(pixel_size / product["width"] * 100, ".8g") + "cqw" + ("" if field in name_fields else " !important")
                rule += "; letter-spacing: " + format(tracking / 1000, ".6g") + "em; }"
                (common if slot in ("banner", "bunting") else items).append(rule)
                report.append({"code": code, "slot": slot, "field": field, "source_font": original,
                               "family": "PSD " + font, "weight": str(weight), "style": "italic" if italic else "normal",
                               "tracking": tracking,
                               "layer": source["layer"], "groups": source["groups"], "rect": source["rect"],
                               "selector": selector})
            if len(name_fields) == 2:
                boxes = [fields[field]["rect"] for field in name_fields]
                centers = [((box[1] + box[3]) / 2, (box[0] + box[2]) / 2) for box in boxes]
                is_stacked = abs(centers[0][1] - centers[1][1]) >= max(box[2] - box[0] for box in boxes) * 0.65
                if is_stacked:
                    photo_root = (".wedding-card-front" if slot == "card-front"
                                  else '.live-product[data-preview-artwork-slot="' + slot + '"]')
                    photo_class = ".has-customer-photo" if slot == "card-front" else ".has-live-customer-photo"
                    photo_root += '[data-font-design-code="' + code + '"]' + photo_class
                    photo_couple = ".desa-card-custom-couple" if slot == "card-front" else ".desa-product-copy-couple"
                    items.append(photo_root + " " + photo_couple
                                 + " { display: grid !important; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important;"
                                 + " grid-template-rows: auto !important; column-gap: 8% !important; align-items: center !important; }")
                    items.append(photo_root + " " + photo_couple + " > span"
                                 + " { position: static !important; width: auto !important; max-width: 100% !important;"
                                 + " margin: 0 !important; padding: 0 !important; text-align: center !important;"
                                 + " transform: none !important; translate: none !important; }")
                    items.append(photo_root + " " + photo_couple + " > .desa-couple-ampersand-spacer"
                                 + " { display: none !important; }")
                root = ".wedding-card-front" if slot == "card-front" else '.live-product[data-preview-artwork-slot="' + slot + '"]'
                photo_class = ".has-customer-photo" if slot == "card-front" else ".has-live-customer-photo"
                root += '[data-font-design-code="' + code + '"][data-design-artwork]:not(' + photo_class + ')'
                couple = ".desa-card-custom-couple" if slot == "card-front" else ".desa-product-copy-couple"
                geometry.append(root + " " + couple + " { position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important; margin: 0 !important; display: block !important; transform: none !important; }")
                horizontal = abs(centers[0][1] - centers[1][1]) < max(b[2] - b[0] for b in boxes) * 0.65
                anchors = horizontal_name_anchors(product, fields, slot) or vertical_name_anchors(product, fields, slot)
                for index, (box, center) in enumerate(zip(boxes, centers)):
                    x, y = center
                    if horizontal:
                        y = sum(c[1] for c in centers) / 2
                    width = (box[3] - box[1]) / product["width"] * 100 / 0.88
                    alignment = "center"
                    vertical_alignment = "center"
                    transform = "translate(-50%, -50%)"
                    if anchors:
                        anchor = anchors[index]
                        x, y = anchor["x"], anchor["y"]
                        width = anchor["width"] / product["width"] * 100
                        alignment = anchor["align"]
                        vertical_alignment = anchor.get("vertical_align", "center")
                        if vertical_alignment != "center":
                            transform = "translate(-50%, -100%)" if vertical_alignment == "bottom" else "translate(-50%, 0)"
                        else:
                            transform = "translate(-100%, -50%)" if alignment == "right" else "translate(0, -50%)"
                    height = (anchors[index].get("height", box[2] - box[0]) if anchors else box[2] - box[0]) / product["height"] * 100
                    geometry.append(root + " " + couple + (" > span:first-child" if index == 0 else " > span:last-child")
                                    + " { position: absolute !important; display: block !important; left: " + format(x / product["width"] * 100, ".8g")
                                    + "% !important; top: " + format(y / product["height"] * 100, ".8g")
                                    + "% !important; width: " + format(width, ".8g") + "% !important; --psd-name-height: " + format(height, ".8g")
                                    + ("; --psd-name-align: " + alignment + ("; --psd-name-vertical-align: " + vertical_alignment if vertical_alignment != "center" else "") if anchors else "") + "; margin: 0 !important; padding: 0 !important; text-align: " + alignment + " !important; line-height: 1 !important; transform: " + transform + " !important; }")
    items.extend(geometry)
    # CKM-011 uses the current raster Master artwork, whose text anchors differ
    # from the legacy PSD geometry above. Keep these overrides in the generator
    # so regenerating the preview CSS preserves the Master-matched placement.
    items.extend([
        "",
        "/* CKM-011 text anchors measured from the current Master artwork. */",
        '.wedding-card-front[data-font-design-code="CKM-011"][data-design-artwork]:not(.has-customer-photo) .desa-card-custom-couple { position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important; margin: 0 !important; display: block !important; transform: none !important; }',
        '.wedding-card-front[data-font-design-code="CKM-011"][data-design-artwork]:not(.has-customer-photo) .desa-card-custom-couple > span:first-child { position: absolute !important; display: block !important; left: 21.1% !important; top: 52.1% !important; width: 32.6% !important; --psd-name-height: 10.5; --psd-name-align: center; --psd-name-vertical-align: bottom; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, -100%) !important; }',
        '.wedding-card-front[data-font-design-code="CKM-011"][data-design-artwork]:not(.has-customer-photo) .desa-card-custom-couple > span:last-child { position: absolute !important; display: block !important; left: 32.6% !important; top: 55% !important; width: 27.7% !important; --psd-name-height: 10.5; --psd-name-align: center; --psd-name-vertical-align: top; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, 0) !important; }',
        '.live-product[data-preview-artwork-slot="banner"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple { position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important; margin: 0 !important; display: block !important; transform: none !important; }',
        '.live-product[data-preview-artwork-slot="banner"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple > span:first-child { position: absolute !important; display: block !important; left: 24.8% !important; top: 47.6% !important; width: 18.5% !important; --psd-name-height: 19; --psd-name-align: center; --psd-name-vertical-align: bottom; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, -100%) !important; }',
        '.live-product[data-preview-artwork-slot="banner"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple > span:last-child { position: absolute !important; display: block !important; left: 31.5% !important; top: 52% !important; width: 15.8% !important; --psd-name-height: 18; --psd-name-align: center; --psd-name-vertical-align: top; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, 0) !important; }',
        '.live-product[data-preview-artwork-slot="bunting"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple { position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important; margin: 0 !important; display: block !important; transform: none !important; }',
        '.live-product[data-preview-artwork-slot="bunting"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple > span:first-child { position: absolute !important; display: block !important; left: 30.3% !important; top: 48.5% !important; width: 43.8% !important; --psd-name-height: 10.8; --psd-name-align: center; --psd-name-vertical-align: bottom; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, -100%) !important; }',
        '.live-product[data-preview-artwork-slot="bunting"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple > span:last-child { position: absolute !important; display: block !important; left: 45.9% !important; top: 51.4% !important; width: 37.7% !important; --psd-name-height: 9.9; --psd-name-align: center; --psd-name-vertical-align: top; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, 0) !important; }',
        '.live-product[data-preview-artwork-slot="arrow-left"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple, .live-product[data-preview-artwork-slot="arrow-right"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple { position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important; margin: 0 !important; display: block !important; transform: none !important; }',
        '.live-product[data-preview-artwork-slot^="arrow-"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple > span:first-child { position: absolute !important; display: block !important; left: 26.7% !important; top: 53.7% !important; width: 38.8% !important; --psd-name-height: 13.2; --psd-name-align: center; --psd-name-vertical-align: bottom; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, -100%) !important; }',
        '.live-product[data-preview-artwork-slot^="arrow-"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple > span:last-child { position: absolute !important; display: block !important; left: 40.3% !important; top: 57.3% !important; width: 32.9% !important; --psd-name-height: 12.7; --psd-name-align: center; --psd-name-vertical-align: top; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, 0) !important; }',
        '.live-product[data-preview-artwork-slot="sticker"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple { position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important; margin: 0 !important; display: block !important; transform: none !important; }',
        '.live-product[data-preview-artwork-slot="sticker"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple > span:first-child { position: absolute !important; display: block !important; left: 18.2% !important; top: 52.8% !important; width: 46% !important; --psd-name-height: 10.3; --psd-name-align: center; --psd-name-vertical-align: bottom; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, -100%) !important; }',
        '.live-product[data-preview-artwork-slot="sticker"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) .desa-product-copy-couple > span:last-child { position: absolute !important; display: block !important; left: 25.7% !important; top: 55.6% !important; width: 32% !important; --psd-name-height: 10; --psd-name-align: center; --psd-name-vertical-align: top; margin: 0 !important; padding: 0 !important; text-align: center !important; line-height: 1 !important; transform: translate(-50%, 0) !important; }',
        '.wedding-card-front[data-font-design-code="CKM-011"][data-design-artwork]:not(.has-customer-photo) [data-preview-field="event_date"] { font-size: 2.5cqw !important; }',
        '.live-product--banner[data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) [data-preview-field="event_date"] { font-size: 2.2cqw !important; }',
        '.live-product--banting[data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) [data-preview-field="event_date"] { font-size: 2.8cqw !important; }',
        '.live-product[data-preview-artwork-slot^="arrow-"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) [data-preview-field="event_date"] { font-size: 3.4cqw !important; }',
        '.live-product[data-preview-artwork-slot="sticker"][data-font-design-code="CKM-011"][data-design-artwork]:not(.has-live-customer-photo) [data-preview-field="event_date"] { font-size: 2cqw !important; }',
    ])
    items.extend([
        "",
        "/* Font fitting measures full glyph bounds, so the letterforms must remain visible. */",
        ".wedding-card-front[data-design-artwork][data-font-design-code] .desa-card-custom-couple > span,",
        ".live-product[data-design-artwork][data-font-design-code] .desa-product-copy-couple > span {",
        "    overflow: visible;",
        "    text-overflow: clip;",
        "}",
    ])
    return "\n".join(common) + "\n", "\n".join(items) + "\n", report


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("audit", type=Path)
    parser.add_argument("--output-directory", type=Path, required=True)
    parser.add_argument("--font-directory", type=Path, required=True)
    parser.add_argument("--report", type=Path, required=True)
    arguments = parser.parse_args()
    audit = json.loads(arguments.audit.read_text(encoding="utf-8"))
    if audit["errors"]:
        raise ValueError("Resolve PSD read errors before generating CSS")
    for filename, _ in FONT_FILES.values():
        if not (arguments.font_directory / filename).is_file():
            raise ValueError("Missing font file: " + filename)
    common, items, report = build(audit["catalog"])
    arguments.output_directory.mkdir(parents=True, exist_ok=True)
    (arguments.output_directory / "customer-psd-fonts.css").write_text(common, encoding="utf-8")
    (arguments.output_directory / "customer-psd-item-fonts.css").write_text(items, encoding="utf-8")
    arguments.report.write_text(json.dumps(report, ensure_ascii=False), encoding="utf-8")
    print(json.dumps({"designs": len(audit["catalog"]), "rules": len(report), "font_assets": len(FONT_FILES)}))


if __name__ == "__main__":
    main()
