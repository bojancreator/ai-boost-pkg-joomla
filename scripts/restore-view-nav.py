#!/usr/bin/env python3
"""v0.12.11 — Restore horizontal `ab-view-nav` in ALL 9 com_aiboost view templates.
v0.12.10 incorrectly removed it; Bojan confirmed it must stay AND must include
every newer tab (URL Checker, Integrations, Analyzers). Health tab is renamed
to "Health & Analyzers" per task #330 spec.
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TMPL = ROOT / "component/com_aiboost/admin/tmpl"

# (view_slug, label_token_or_text, icon_class)
NAV_ITEMS = [
    ("dashboard",    "COM_AIBOOST_NAV_DASHBOARD",    "icon-home"),
    ("settings",     "COM_AIBOOST_NAV_SETTINGS",     "icon-cog"),
    ("health",       "COM_AIBOOST_NAV_HEALTH",       "icon-heart"),
    ("redirects",    "COM_AIBOOST_NAV_REDIRECTS",    "icon-arrow-right"),
    ("urlchecker",   "COM_AIBOOST_NAV_URLCHECKER",   "icon-link"),
    ("import",       "COM_AIBOOST_NAV_IMPORT",       "icon-upload"),
    ("integrations", "COM_AIBOOST_NAV_INTEGRATIONS", "icon-puzzle-piece"),
    ("analyzer",     "COM_AIBOOST_NAV_ANALYZERS",    "icon-search"),
    ("help",         "COM_AIBOOST_NAV_HELP",         "icon-question"),
]

# Fallback English labels in case the language constants aren't present yet.
LABEL_FALLBACK = {}


def build_nav(active_view: str) -> str:
    lines = ['  <?php /* ── View navigation (horizontal) ── */ ?>',
             '  <ul class="nav ab-view-nav mb-4">']
    for slug, token, icon in NAV_ITEMS:
        active_cls = " active" if slug == active_view else ""
        fallback = LABEL_FALLBACK.get(token)
        if fallback:
            label_php = (
                f"<?php echo Text::_('{token}') === '{token}' "
                f"? '{fallback}' : Text::_('{token}'); ?>"
            )
        else:
            label_php = f"<?php echo Text::_('{token}'); ?>"
        lines += [
            '    <li class="nav-item">',
            f'      <a class="nav-link{active_cls}" '
            f'href="<?php echo Route::_(\'index.php?option=com_aiboost&view={slug}\'); ?>">',
            f'        <span class="{icon} me-1" aria-hidden="true"></span> {label_php}',
            '      </a>',
            '    </li>',
        ]
    lines.append('  </ul>')
    return "\n".join(lines) + "\n"


# Patterns to find the placeholder we left behind in v0.12.10 OR an existing block.
PH_COMMENT = re.compile(
    r"[ \t]*<\?php\s*/\*\s*Horizontal view nav removed[^*]*\*/\s*\?>\s*\n",
)
PH_VUE_COMMENT = re.compile(
    r"[ \t]*<\?php\s*/\*\s*Horizontalna view-nav uklonjena.*?\*/\s*\?>\s*\n",
    re.DOTALL,
)
EXISTING_NAV = re.compile(
    r"[ \t]*<\?php\s*/\*[^*]*View navigation[^*]*\*/\s*\?>\s*\n"
    r"[ \t]*<ul[^>]*ab-view-nav[^>]*>.*?</ul>\s*\n",
    re.DOTALL,
)


def update_file(view: str) -> str:
    p = TMPL / view / "default.php"
    if not p.exists():
        return f"!! missing: {view}"
    src = p.read_text(encoding="utf-8")
    nav = build_nav(view)

    if EXISTING_NAV.search(src):
        new = EXISTING_NAV.sub(nav, src, count=1)
        return f"OK {view} (replaced existing)" if new != src and p.write_text(new, encoding="utf-8") is None else f"-- {view}"

    if PH_COMMENT.search(src):
        new = PH_COMMENT.sub(nav, src, count=1)
        p.write_text(new, encoding="utf-8")
        return f"OK {view} (replaced placeholder)"

    if PH_VUE_COMMENT.search(src):
        new = PH_VUE_COMMENT.sub(nav, src, count=1)
        p.write_text(new, encoding="utf-8")
        return f"OK {view} (replaced vue-placeholder)"

    # Fallback: insert right after the opening `<div class="container-fluid mt-3">`
    m = re.search(r'<div class="container-fluid mt-3">\s*\n', src)
    if m:
        new = src[:m.end()] + "\n" + nav + "\n" + src[m.end():]
        p.write_text(new, encoding="utf-8")
        return f"OK {view} (inserted after container)"

    return f"!! could not place nav in {view}"


for slug, _t, _i in NAV_ITEMS:
    print(update_file(slug))
