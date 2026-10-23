#!/usr/bin/env python3
"""Every English page needs a Slovak page under sk/ with the same path, and the other way round."""
import sys
from pathlib import Path

root = Path(__file__).resolve().parent.parent
skip = {"node_modules", ".vitepress", "public", "diagrams", "scripts"}


def pages(base: Path, exclude_sk: bool) -> set[str]:
    found = set()
    for page in base.rglob("*.md"):
        relative = page.relative_to(base)
        if relative.parts[0] in skip or (exclude_sk and relative.parts[0] == "sk"):
            continue
        found.add(relative.as_posix())
    return found


english = pages(root, exclude_sk=True)
slovak = pages(root / "sk", exclude_sk=False)
problems = [f"missing sk/{p}" for p in sorted(english - slovak)]
problems += [f"sk/{p} has no English page" for p in sorted(slovak - english)]
if problems:
    print("English and Slovak pages differ:", file=sys.stderr)
    print("\n".join(problems), file=sys.stderr)
    sys.exit(1)
print(f"ok {len(english)} pages in English and Slovak")
