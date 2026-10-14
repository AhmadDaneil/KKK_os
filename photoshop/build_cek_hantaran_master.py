"""Build the clean Cek Hantaran background from the supplied Illustrator PDF/.ps.

Only sample customer text is removed; the vector ornament and fixed labels stay.
The resulting PNG is the immutable background used by the Photoshop JSX.
"""

from __future__ import annotations

import argparse
import subprocess
from pathlib import Path

from pypdf import PdfReader, PdfWriter
from pypdf.generic import ContentStream, DecodedStreamObject, RectangleObject


def displayed_text(operands: list, operator: bytes) -> str:
    if operator == b"Tj":
        return str(operands[0])
    if operator == b"TJ":
        return "".join(part for part in operands[0] if isinstance(part, str))
    return ""


def is_sample_text(operands: list, operator: bytes) -> bool:
    text = displayed_text(operands, operator).strip()
    return text in {"R", "M", "100.00", "JUMAAT", "23 OKTOBER 2026"} or (
        "HASY" in text and "BINTI" in text
    ) or ("AHMAD" in text and "AZIZ" in text)


def build(source: Path, output: Path) -> None:
    reader = PdfReader(source)
    if len(reader.pages) != 1:
        raise ValueError("The Cek Hantaran master must have exactly one page")

    writer = PdfWriter()
    writer.clone_document_from_reader(reader)
    page = writer.pages[0]
    content = ContentStream(page.get_contents(), writer)
    sample_count = sum(is_sample_text(operands, operator) for operands, operator in content.operations)
    if sample_count != 7:
        raise ValueError(f"Expected 7 sample text operations; found {sample_count}")
    content.operations = [
        (operands, operator)
        for operands, operator in content.operations
        if not is_sample_text(operands, operator)
    ]
    cleaned = DecodedStreamObject()
    cleaned.set_data(content.get_data())
    page.replace_contents(cleaned)

    # The supplied PDF has a large white artboard; keep only the cheque artwork.
    page.cropbox = RectangleObject([151, 309, 663, 568])
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("wb") as destination:
        writer.write(destination)

    subprocess.run(
        [
            "pdftoppm",
            "-f", "1", "-singlefile", "-cropbox", "-r", "300", "-png",
            str(output), str(output.with_suffix("")),
        ],
        check=True,
    )


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("source", type=Path, help="Original .ps Illustrator PDF")
    parser.add_argument("--output", type=Path, default=Path(__file__).with_name("cek_hantaran_master_clean.pdf"))
    arguments = parser.parse_args()
    build(arguments.source, arguments.output)
