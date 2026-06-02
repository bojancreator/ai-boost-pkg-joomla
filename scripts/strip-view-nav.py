#!/usr/bin/env python3
"""v0.12.10 — Strip horizontal `ab-view-nav` block from all com_aiboost view templates.
Navigation moved into Vue sidebar; the leftover horizontal strip caused user confusion.
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TMPL = ROOT / "component/com_aiboost/admin/tmpl"
VIEWS = ["dashboard", "settings", "redirects", "analyzer", "integrations", "import", "help"]

PATTERN = re.compile(
    r"[ \t]*<\?php\s*/\*[^*]*View navigation[^*]*\*/\s*\?>\s*\n"
    r"[ \t]*<ul[^>]*ab-view-nav[^>]*>.*?</ul>\s*\n",
    re.DOTALL,
)

REPLACEMENT = (
    "  <?php /* Horizontal view nav removed in v0.12.10 — moved into Vue sidebar / Joomla submenu. */ ?>\n"
)

changed = 0
for v in VIEWS:
    p = TMPL / v / "default.php"
    if not p.exists():
        print(f"!! missing: {p}", file=sys.stderr)
        continue
    src = p.read_text(encoding="utf-8")
    new, n = PATTERN.subn(REPLACEMENT, src, count=1)
    if n == 0:
        print(f"-- no match: {v}")
        continue
    p.write_text(new, encoding="utf-8")
    print(f"OK {v}")
    changed += 1

print(f"\n{changed}/{len(VIEWS)} templates stripped")
