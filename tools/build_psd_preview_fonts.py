"""Build live-preview font CSS from the visibility-aware PSD audit."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any


FONT_FILES = {
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


def build(catalog: dict[str, Any]) -> tuple[str, str, list[dict[str, Any]]]:
    common = ["/* Generated from visible PSD text runs, including group visibility and duplicate-layer review. */"]
    for name, (filename, weight) in FONT_FILES.items():
        common.append('@font-face { font-family: "PSD ' + name + '"; src: url("../fonts/psd-preview/' + filename
                      + '") format("woff"); font-style: normal; font-weight: ' + weight + '; font-display: swap; }')
    items = ["/* Item font mappings from the source PSD layers. */"]
    report = []
    for code, design in sorted(catalog.items()):
        for item, product in design["items"].items():
            for conflict in product.get("conflicts", []):
                if conflict["reason"] == "mixed text fonts" and conflict["field"] in {
                    "singkatanlelaki", "singkatanperempuan", "namapengantinlelaki", "namapengantinperempuan",
                    "nama anak", "namaanak", "hari", "tarikh", "tarikh copy", "majlis perkahwinan",
                    "majlis perkahwinan copy", "walimatul urus", "ke majlis perkahwinan",
                }:
                    raise ValueError(f"Review mixed-font text before building: {code}/{item}/{conflict['field']}")
            slot = SLOTS[item]
            for field, source in fields_for_item(product["selected"], slot).items():
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
                rule += "; letter-spacing: " + format(tracking / 1000, ".6g") + "em; }"
                (common if slot in ("banner", "bunting") else items).append(rule)
                report.append({"code": code, "slot": slot, "field": field, "source_font": original,
                               "family": "PSD " + font, "weight": str(weight), "style": "italic" if italic else "normal",
                               "tracking": tracking,
                               "layer": source["layer"], "groups": source["groups"], "rect": source["rect"],
                               "selector": selector})
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
