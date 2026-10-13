"""Recipe code must come from examples/, never be pasted into the page.

- Every `<<< @/...` snippet import must point at an existing file.
- A fenced block titled with a file of the recipe's project (```php [src/X.php])
  is a pasted copy that can drift; import the file instead.
"""
import re
import sys
from pathlib import Path

docs = Path(__file__).resolve().parent.parent
errors = []
for page in sorted(docs.rglob("*.md")):
    if "node_modules" in page.parts or ".vitepress" in page.parts:
        continue
    text = page.read_text(encoding="utf-8")
    for target in re.findall(r"^<<< @/(\S+?)(?:\{[^}]*\})?(?:\s|$)", text, re.M):
        path = target.split("#", 1)[0]
        if not (docs / path).is_file():
            errors.append(f"{page.relative_to(docs)}: snippet {path} does not exist")
    if page.parent.name == "examples":
        project = docs.parent / "examples" / page.stem
        for title in re.findall(r"^```\w*\s*\[([^\]]+)\]", text, re.M):
            if (project / title).is_file():
                errors.append(f"{page.relative_to(docs)}: pasted copy of examples/{page.stem}/{title}, use <<< @/../examples/{page.stem}/{title}")

for error in errors:
    print(error, file=sys.stderr)
if errors:
    sys.exit(1)
print("ok recipe code is imported from examples/")
