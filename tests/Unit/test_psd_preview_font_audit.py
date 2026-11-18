import importlib.util
import struct
import tempfile
import unittest
from pathlib import Path


spec = importlib.util.spec_from_file_location("psd_font_audit", Path(__file__).parents[2] / "tools/audit_psd_preview_fonts.py")
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)
builder_spec = importlib.util.spec_from_file_location("psd_font_builder", Path(__file__).parents[2] / "tools/build_psd_preview_fonts.py")
builder = importlib.util.module_from_spec(builder_spec)
builder_spec.loader.exec_module(builder)


def text_engine() -> bytes:
    return b"""<< /EngineDict <<
        /Editor << /Text (Hairi\r) >>
        /StyleRun <<
            /RunArray [
                << /StyleSheet << /StyleSheetData << /Font 1 /FontSize 30 >> >> >>
                << /StyleSheet << /StyleSheetData << /Font 2 >> >> >>
            ]
            /RunLengthArray [ 5 1 ]
        >>
    >> /ResourceDict << /FontSet [
        << /Name (UnusedFont) >> << /Name (Belladonia) >> << /Name (MyriadPro-Regular) >>
    ] >> >>"""


def layer(index: int, name: str, font: str = "", flags: int = 0, section=None) -> dict:
    return {"index": index, "name": name, "flags": flags, "opacity": 255, "section": section,
            "fonts": {font: 5} if font else {}, "runs": [], "rect": [0, 0, 30, 100]}


class PsdPreviewFontAuditTest(unittest.TestCase):
    def test_vip_card_uses_full_names_and_split_date_fields(self):
        names = {"font": "MonotypeCorsiva"}
        dates = {"font": "CenturyGothic"}

        fields = builder.fields_for_item({"namapengantinlelaki": names, "tarikhhari": dates,
                                         "bulan": dates}, "vip-card")

        self.assertIs(names, fields["groom_name"])
        self.assertIs(dates, fields["event_date_day"])
        self.assertIs(dates, fields["event_date_month_year"])
        self.assertNotIn("groom_display", fields)
        self.assertIn('[data-preview-field="groom_name"]', builder.field_selector("CKP-001", "vip-card", "groom_name"))

    def test_main_caption_is_preferred_to_a_different_template_caption(self):
        primary = {"font": "Montserrat-Medium"}
        template = {"font": "TimesNewRomanPSMT"}

        result = builder.fields_for_item({"majlis perkahwinan copy": primary, "ke majlis perkahwinan": template})

        self.assertIs(primary, result["card_title_malay"])

    def test_css_build_requires_review_of_mixed_font_names(self):
        product = {"selected": {}, "conflicts": [{"field": "singkatanlelaki", "reason": "mixed text fonts"}]}

        with self.assertRaisesRegex(ValueError, "Review mixed-font text"):
            builder.build({"CKG-001": {"items": {"banner": product}}})

    def test_only_approved_source_font_replacements_are_applied(self):
        self.assertEqual("Acardia-Regular", builder.resolve_font("Heartway"))
        self.assertEqual("GreatVibes-Regular", builder.resolve_font("BreathingPersonalUseOnly-Reg"))
        self.assertEqual("Belladonia", builder.resolve_font("Belladonia"))

    def test_regular_and_semibold_source_weights_are_preserved(self):
        self.assertEqual(500, builder.font_weight("MontserratRoman-Medium", []))
        self.assertEqual(600, builder.font_weight("Baskerville-SemiBold", []))
        self.assertEqual(700, builder.font_weight("MyriadPro-Bold", []))

    def test_banner_font_css_uses_the_selected_visible_layer(self):
        records = [layer(0, "singkatanlelaki", "Belladonia"),
                   layer(1, "singkatanlelaki", "AmalfiCoast", flags=2)]
        audit.assign_visibility(records)
        selected, _ = audit.select_visible_fonts(records)

        common, _, report = builder.build({"CKG-001": {"items": {"banner": {"selected": selected}}}})

        self.assertEqual("PSD Belladonia", report[0]["family"])
        self.assertIn('.desa-product-copy-couple > span:first-child { font-family: "PSD Belladonia"', common)

    def test_source_tracking_is_preserved_in_font_rules(self):
        record = layer(0, "singkatanlelaki", "Belladonia")
        record["runs"] = [{"text": "Syafiq", "tracking": -30}]
        audit.assign_visibility([record])
        selected, _ = audit.select_visible_fonts([record])

        common, _, report = builder.build({"CKG-001": {"items": {"banner": {"selected": selected}}}})

        self.assertEqual(-30, report[0]["tracking"])
        self.assertIn("letter-spacing: -0.03em", common)

    def test_hidden_duplicate_cannot_replace_visible_font(self):
        records = [layer(0, "singkatanlelaki", "Belladonia"), layer(1, "singkatanlelaki", "AmalfiCoast", flags=2)]

        audit.assign_visibility(records)
        selected, conflicts = audit.select_visible_fonts(records)

        self.assertEqual("Belladonia", selected["singkatanlelaki"]["font"])
        self.assertEqual([], conflicts)

    def test_off_canvas_copy_cannot_replace_visible_font(self):
        records = [layer(0, "singkatanlelaki", "Belladonia"), layer(1, "singkatanlelaki", "AmalfiCoast")]
        records[1]["rect"] = [200, 0, 230, 100]

        audit.assign_visibility(records, 100, 100)
        selected, conflicts = audit.select_visible_fonts(records)

        self.assertEqual("Belladonia", selected["singkatanlelaki"]["font"])
        self.assertFalse(records[1]["on_canvas"])
        self.assertEqual([], conflicts)

    def test_child_of_hidden_ancestor_is_not_selected(self):
        records = [layer(0, "divider", section=3), layer(1, "divider", section=3),
                   layer(2, "singkatanlelaki", "AmalfiCoast"), layer(3, "Inner", section=1),
                   layer(4, "Old copy", flags=2, section=1)]

        audit.assign_visibility(records)
        selected, conflicts = audit.select_visible_fonts(records)

        self.assertTrue(records[2]["own_visible"])
        self.assertFalse(records[2]["effective_visible"])
        self.assertEqual(["Old copy", "Inner"], records[2]["groups"])
        self.assertEqual({}, selected)

    def test_collapsed_visible_group_remains_visible(self):
        records = [layer(0, "divider", section=3), layer(1, "singkatanlelaki", "Belladonia"),
                   layer(2, "Collapsed group", section=2)]

        audit.assign_visibility(records)
        selected, _ = audit.select_visible_fonts(records)

        self.assertEqual("Belladonia", selected["singkatanlelaki"]["font"])

    def test_font_is_taken_from_text_run_not_first_font_resource(self):
        result = audit.text_fonts(text_engine())

        self.assertEqual({"Belladonia": 5}, result["fonts"])
        self.assertEqual("Hairi", result["runs"][0]["text"])

    def test_multiple_visible_copies_are_reported_for_review(self):
        records = [layer(0, "singkatanlelaki", "Belladonia"), layer(1, "singkatanlelaki", "AmalfiCoast")]

        audit.assign_visibility(records)
        selected, conflicts = audit.select_visible_fonts(records)

        self.assertEqual([0, 1], conflicts[0]["layers"])
        self.assertEqual("multiple visible text layers", conflicts[0]["reason"])
        self.assertEqual("AmalfiCoast", selected["singkatanlelaki"]["font"])

    def test_misnamed_ampersand_does_not_replace_date(self):
        records = [layer(0, "tarikh", "Montserrat-Medium"), layer(1, "tarikh", "Heartway")]
        records[0]["text"] = "20 DISEMBER 2025"
        records[1]["text"] = "&"

        audit.assign_visibility(records)
        selected, conflicts = audit.select_visible_fonts(records)

        self.assertEqual("Montserrat-Medium", selected["tarikh"]["font"])
        self.assertEqual([], conflicts)

    def test_mixed_visible_fonts_are_not_silently_reduced_to_one_font(self):
        record = layer(0, "singkatanlelaki", "Belladonia")
        record["fonts"]["AmalfiCoast"] = 3

        audit.assign_visibility([record])
        selected, conflicts = audit.select_visible_fonts([record])

        self.assertEqual({}, selected)
        self.assertEqual("mixed text fonts", conflicts[0]["reason"])

    def test_reads_text_layers_in_16_bit_layer_info(self):
        engine = text_engine()
        block = b"8BIMTySh" + struct.pack(">I", len(engine)) + engine + b"\x00" * (len(engine) & 1)
        value = b"singkatanlelaki"
        name = bytes([len(value)]) + value
        name += b"\x00" * ((-len(name)) % 4)
        extra = struct.pack(">II", 0, 0) + name + block
        record = struct.pack(">iiiiH", 0, 0, 30, 100, 0) + b"8BIMnorm\xff\x00\x00\x00" + struct.pack(">I", len(extra)) + extra
        payload = struct.pack(">h", 1) + record
        section = struct.pack(">II", 0, 0) + b"8BIMLr16" + struct.pack(">I", len(payload)) + payload
        header = b"8BPS" + struct.pack(">H", 1) + b"\x00" * 6 + struct.pack(">HIIHH", 3, 1800, 1200, 16, 3)
        data = header + struct.pack(">II", 0, 0) + struct.pack(">I", len(section)) + section
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "front.psd"
            path.write_bytes(data)

            result = audit.inspect_psd(path)

        self.assertEqual("Belladonia", result["selected"]["singkatanlelaki"]["font"])


if __name__ == "__main__":
    unittest.main()
