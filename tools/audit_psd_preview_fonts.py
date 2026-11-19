"""Inspect PSD text fonts without allowing hidden copies to replace visible text."""

from __future__ import annotations

import argparse
import collections
import json
import math
import struct
from pathlib import Path
from typing import Any, BinaryIO


class EngineReader:
    def __init__(self, data: bytes) -> None:
        self.data = data
        self.position = 0

    def skip_space(self) -> None:
        while self.position < len(self.data) and self.data[self.position] in b" \t\r\n\x00":
            self.position += 1

    def value(self) -> Any:
        self.skip_space()
        if self.data.startswith(b"<<", self.position):
            self.position += 2
            result = {}
            while True:
                self.skip_space()
                if self.data.startswith(b">>", self.position):
                    self.position += 2
                    return result
                key = self.value()
                result[key] = self.value()
        if self.data[self.position:self.position + 1] == b"[":
            self.position += 1
            result = []
            while True:
                self.skip_space()
                if self.data[self.position:self.position + 1] == b"]":
                    self.position += 1
                    return result
                result.append(self.value())
        if self.data[self.position:self.position + 1] == b"(":
            self.position += 1
            result = bytearray()
            depth = 1
            while depth:
                char = self.data[self.position]
                self.position += 1
                if char == 92:
                    escaped = self.data[self.position]
                    self.position += 1
                    if 48 <= escaped <= 55:
                        octal = bytes([escaped])
                        while len(octal) < 3 and self.position < len(self.data) and 48 <= self.data[self.position] <= 55:
                            octal += self.data[self.position:self.position + 1]
                            self.position += 1
                        result.append(int(octal, 8))
                    elif escaped not in (10, 13):
                        result.append({110: 10, 114: 13, 116: 9, 98: 8, 102: 12}.get(escaped, escaped))
                    elif escaped == 13 and self.data[self.position:self.position + 1] == b"\n":
                        self.position += 1
                elif char == 40:
                    depth += 1
                    result.append(char)
                elif char == 41:
                    depth -= 1
                    if depth:
                        result.append(char)
                else:
                    result.append(char)
            raw = bytes(result)
            return raw[2:].decode("utf-16-be") if raw.startswith(b"\xfe\xff") else raw.decode("utf-8", errors="replace")
        start = self.position
        while self.position < len(self.data) and self.data[self.position] not in b" \t\r\n[]<>()":
            self.position += 1
        if start == self.position:
            raise ValueError(f"Unexpected EngineData token at {start}")
        token = self.data[start:self.position].decode("ascii")
        if token.startswith("/"):
            return token[1:]
        if token in ("true", "false"):
            return token == "true"
        if token == "null":
            return None
        return float(token) if "." in token else int(token)


def text_fonts(type_data: bytes) -> dict[str, Any]:
    index = type_data.find(b"/EngineDict")
    if index < 0:
        raise ValueError("Text layer has no EngineData")
    start = type_data.rfind(b"<<", 0, index)
    document = EngineReader(type_data[start:]).value()
    engine = document["EngineDict"]
    resources = document.get("ResourceDict", document.get("DocumentResources", {}))
    font_set = resources["FontSet"]
    text = engine.get("Editor", {}).get("Text", "")
    style_run = engine["StyleRun"]
    default = style_run.get("DefaultRunData", {}).get("StyleSheet", {}).get("StyleSheetData", {})
    cursor = 0
    used: collections.Counter[str] = collections.Counter()
    runs = []
    for run, length in zip(style_run["RunArray"], style_run["RunLengthArray"]):
        style = default | run.get("StyleSheet", {}).get("StyleSheetData", {})
        font = font_set[int(style.get("Font", 0))]["Name"]
        fragment = text[cursor:cursor + int(length)]
        cursor += int(length)
        characters = sum(not character.isspace() and character != "\x00" for character in fragment)
        if characters:
            used[font] += characters
            runs.append({"font": font, "text": fragment, "weight": "bold" if style.get("FauxBold") else "normal",
                         "italic": bool(style.get("FauxItalic")), "size": style.get("FontSize"),
                         "tracking": style.get("Tracking", 0),
                         "horizontal_scale": style.get("HorizontalScale", 1),
                         "vertical_scale": style.get("VerticalScale", 1)})
    transform = struct.unpack_from(">6d", type_data, 2) if type_data[:2] == b"\x00\x01" else (1, 0, 0, 1, 0, 0)
    for run in runs:
        if run["size"] is not None:
            run["pixel_size"] = run["size"] * math.hypot(transform[2], transform[3]) * run["vertical_scale"]
    return {"text": text, "fonts": dict(used), "runs": runs}


def assign_visibility(records: list[dict[str, Any]], width: int | None = None, height: int | None = None) -> None:
    stack = []
    for record in reversed(records):
        section = record.get("section")
        if section == 3:
            if not stack:
                raise ValueError("Unbalanced PSD group boundary")
            stack.pop()
            continue
        own_visible = not record["flags"] & 2 and record["opacity"] > 0
        record["own_visible"] = own_visible
        record["groups"] = [group["name"] for group in stack]
        top, left, bottom, right = record["rect"]
        on_canvas = width is None or height is None or (right > 0 and bottom > 0 and left < width and top < height)
        record["on_canvas"] = on_canvas
        record["effective_visible"] = own_visible and all(group["own_visible"] for group in stack) and on_canvas
        if section in (1, 2):
            stack.append(record)
    if stack:
        raise ValueError("Unclosed PSD groups")


def select_visible_fonts(records: list[dict[str, Any]]) -> tuple[dict[str, Any], list[dict[str, Any]]]:
    selected = {}
    conflicts = []
    for record in records:
        if not record.get("effective_visible") or not record.get("fonts"):
            continue
        name = record["name"].strip().lower()
        text = record.get("text")
        if name.startswith("tarikh") and text is not None and not any(character.isalnum() for character in text):
            continue
        fonts = list(record["fonts"])
        if len(fonts) != 1:
            conflicts.append({"field": name, "reason": "mixed text fonts", "layer": record["index"], "fonts": fonts})
            continue
        value = {"font": fonts[0], "layer": record["index"], "rect": record["rect"], "groups": record["groups"],
                 "runs": record["runs"]}
        if name in selected:
            conflicts.append({"field": name, "reason": "multiple visible text layers",
                              "layers": [selected[name]["layer"], record["index"]],
                              "fonts": [selected[name]["font"], value["font"]]})
        selected[name] = value
    return selected, conflicts


def read_u32(stream: BinaryIO) -> int:
    return struct.unpack(">I", stream.read(4))[0]


def read_records(stream: BinaryIO) -> list[dict[str, Any]]:
    count = abs(struct.unpack(">h", stream.read(2))[0])
    result = []
    for index in range(count):
        rect = struct.unpack(">iiii", stream.read(16))
        channels = struct.unpack(">H", stream.read(2))[0]
        stream.seek(channels * 6, 1)
        blend = stream.read(12)
        extra = stream.read(read_u32(stream))
        offset = 0
        for _ in range(2):
            size = struct.unpack_from(">I", extra, offset)[0]
            offset += 4 + size
        size = 1 + extra[offset]
        name = extra[offset + 1:offset + size].decode("mac_roman", errors="replace")
        offset += size + (-size) % 4
        blocks = {}
        while offset + 12 <= len(extra):
            key = extra[offset + 4:offset + 8]
            size = struct.unpack_from(">I", extra, offset + 8)[0]
            blocks[key] = extra[offset + 12:offset + 12 + size]
            offset += 12 + size + (size & 1)
        if b"luni" in blocks:
            raw = blocks[b"luni"]
            name = raw[4:4 + struct.unpack_from(">I", raw)[0] * 2].decode("utf-16-be")
        divider = blocks.get(b"lsdk", blocks.get(b"lsct"))
        record = {"index": index, "name": name, "rect": rect, "opacity": blend[8],
                  "flags": blend[10], "section": struct.unpack_from(">I", divider)[0] if divider else None}
        if b"TySh" in blocks:
            record.update(text_fonts(blocks[b"TySh"]))
        result.append(record)
    return result


def inspect_psd(path: Path) -> dict[str, Any]:
    with path.open("rb") as stream:
        header = stream.read(26)
        if header[:4] != b"8BPS" or struct.unpack_from(">H", header, 4)[0] != 1:
            raise ValueError("Expected a PSD version 1 document")
        height, width = struct.unpack_from(">II", header, 14)
        for _ in range(2):
            stream.seek(read_u32(stream), 1)
        section_end = stream.tell() + 4 + read_u32(stream)
        size = read_u32(stream)
        if size:
            records = read_records(stream)
        else:
            records = []
            stream.seek(read_u32(stream), 1)
            while stream.tell() + 12 <= section_end:
                signature = stream.read(4)
                key = stream.read(4)
                size = int.from_bytes(stream.read(8 if signature == b"8B64" else 4), "big")
                start = stream.tell()
                if key in (b"Lr16", b"Lr32", b"Layr"):
                    records = read_records(stream)
                    break
                stream.seek(start + size + (-size) % 4)
    assign_visibility(records, width, height)
    selected, conflicts = select_visible_fonts(records)
    return {"path": str(path), "width": width, "height": height, "selected": selected,
            "conflicts": conflicts, "layers": [record for record in records if "fonts" in record]}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source_root", type=Path)
    parser.add_argument("--output", type=Path, required=True)
    arguments = parser.parse_args()
    catalog = {}
    errors = []
    for folder in sorted(arguments.source_root.glob("*/*")):
        if not folder.is_dir():
            continue
        items = {}
        for item in ("KAD DEPAN", "BANNER", "BANTING", "STICKER", "ARROW KIRI", "ARROW KANAN", "WOODEN HANGER", "VIP CARD", "KAD BELAKANG"):
            path = folder / (item + ".psd")
            if path.exists():
                try:
                    items[item.lower()] = inspect_psd(path)
                except Exception as error:
                    errors.append({"path": str(path), "error": str(error)})
        if items:
            catalog[folder.name] = {"theme": folder.parent.name, "items": items}
    arguments.output.write_text(json.dumps({"catalog": catalog, "errors": errors}, ensure_ascii=False), encoding="utf-8")
    print(json.dumps({"designs": len(catalog), "products": sum(len(value["items"]) for value in catalog.values()),
                      "duplicate_or_mixed_groups": sum(len(item["conflicts"]) for value in catalog.values() for item in value["items"].values()),
                      "errors": errors}))


if __name__ == "__main__":
    main()
