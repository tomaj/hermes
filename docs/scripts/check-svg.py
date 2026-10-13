#!/usr/bin/env python3
"""Fail when a docs SVG is not well-formed UTF-8 XML.

Scans docs/public. Extra directories can be passed as arguments so the built
site can be checked with the same rules.
"""

from __future__ import annotations

import sys
import xml.etree.ElementTree as ET
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
DEFAULT_ROOTS = (REPO / "docs" / "public",)


def svg_files(roots: list[Path]) -> list[Path]:
    files: list[Path] = []
    for root in roots:
        if not root.exists():
            continue
        if root.is_file() and root.suffix.lower() == ".svg":
            files.append(root)
            continue
        files.extend(sorted(path for path in root.rglob("*") if path.suffix.lower() == ".svg"))
    return files


def check(path: Path) -> list[str]:
    errors: list[str] = []
    data = path.read_bytes()
    try:
        text = data.decode("utf-8")
    except UnicodeDecodeError as exc:
        return [f"{path}: not valid UTF-8 ({exc})"]

    if "\ufffd" in text:
        errors.append(f"{path}: contains U+FFFD, which browsers will not treat as a currency sign")

    try:
        ET.fromstring(text)
    except ET.ParseError as exc:
        errors.append(f"{path}: not well-formed XML ({exc})")

    return errors


def main(argv: list[str]) -> int:
    roots = [Path(arg) for arg in argv[1:]] or list(DEFAULT_ROOTS)
    files = svg_files(roots)
    if not files:
        print("No SVG files found in: " + ", ".join(str(root) for root in roots), file=sys.stderr)
        return 1

    failed = False
    for path in files:
        errors = check(path)
        if errors:
            failed = True
            for error in errors:
                print(error, file=sys.stderr)
        else:
            print(f"ok {path}")

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
