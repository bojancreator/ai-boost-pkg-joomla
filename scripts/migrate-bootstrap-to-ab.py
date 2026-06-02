#!/usr/bin/env python3
"""Bulk migrate Bootstrap component classes to ab-* design system classes
across AI Boost Vue admin source files.

Operates only on the contents of class="..." attribute values within
.vue files. Excludes TopNav.vue (router-link nav-link kept) and any
files already migrated where it doesn't matter (idempotent on ab-* tokens).
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "component" / "com_aiboost" / "vue-admin" / "src"

# Files NOT to touch (TopNav uses nav-link for router-link with custom CSS)
EXCLUDE = {"TopNav.vue", "main.js", "router.js"}

# Token-level mapping. Applied in order; first match wins per token.
# We tokenise the class string on whitespace and replace token-for-token.
TOKEN_MAP = {
    # Card
    "card-header": "ab-card__header",
    "card-body": "ab-card__body",
    "card-footer": "ab-card__footer",
    "card-title": "ab-card__title",
    "card": "ab-card",

    # Button variants (must come BEFORE 'btn')
    "btn-primary": "ab-btn--primary",
    "btn-success": "ab-btn--success",
    "btn-danger": "ab-btn--danger",
    "btn-warning": "ab-btn--warning",
    "btn-info": "ab-btn--ghost",
    "btn-secondary": "ab-btn--ghost",
    "btn-light": "ab-btn--ghost",
    "btn-outline-primary": "ab-btn--ghost",
    "btn-outline-secondary": "ab-btn--ghost",
    "btn-outline-success": "ab-btn--ghost",
    "btn-outline-danger": "ab-btn--ghost ab-btn--danger-ghost",
    "btn-outline-warning": "ab-btn--ghost",
    "btn-outline-info": "ab-btn--ghost",
    "btn-outline-light": "ab-btn--ghost",
    "btn-outline-dark": "ab-btn--ghost",
    "btn-sm": "ab-btn--sm",
    "btn-lg": "ab-btn--lg",
    "btn-block": "ab-btn--block",
    "btn": "ab-btn",

    # Badge backgrounds (only when paired with `badge` token in same class)
    # handled in post-processing below; here we just map `badge` → `ab-badge`
    "badge": "ab-badge",

    # Alert
    "alert-success": "ab-alert--success",
    "alert-warning": "ab-alert--warning",
    "alert-danger": "ab-alert--danger",
    "alert-info": "ab-alert--info",
    "alert-primary": "ab-alert--info",
    "alert-secondary": "ab-alert",
    "alert": "ab-alert",

    # Nav tabs
    "nav-tabs": "ab-tabs",
    "nav-link": "ab-tab",

    # Forms
    "form-control": "ab-input",
    "form-select": "ab-select",
    "form-label": "ab-label",
    "form-text": "ab-help",
    "form-check-input": "ab-toggle__input",
    "form-switch": "ab-toggle",
    "form-check": "ab-check",
    "form-check-label": "ab-check__label",

    # List group → ab-list-group (new utility added to CSS)
    "list-group-item-action": "ab-list-group__item",
    "list-group-item": "ab-list-group__item",
    "list-group-flush": "ab-list-group--flush",
    "list-group": "ab-list-group",

    # Accordion → use ab-accordion (already exists in tokens for HealthApp)
    "accordion-item": "ab-accordion__item",
    "accordion-header": "ab-accordion__header",
    "accordion-button": "ab-accordion__button",
    "accordion-collapse": "ab-accordion__collapse",
    "accordion-body": "ab-accordion__body",
    "accordion-flush": "ab-accordion--flush",
    "accordion": "ab-accordion",

    # Table → keep as-is mostly, but add ab-table marker for our hook
    # (Joomla's .table already works; we just remove table-hover which
    # breaks in dark mode and replace with ab-table for our overrides)
    "table-hover": "ab-table--hover",

    # Misc
    "spinner-border": "ab-spinner",
    "spinner-border-sm": "ab-spinner--sm",
}

# Tokens that map to a removable noise word when preceding ab-tabs/ab-card etc.
DROP_WHEN_WITH = {
    # token : neighbour that justifies dropping
    "nav": "ab-tabs",       # "nav nav-tabs" → "nav-tabs" → "ab-tabs"
}

# bg-* tokens that should become ab-badge--X when paired with `badge`
# (handled by mapping badge first, then scanning for bg-*)
BG_TO_BADGE_VARIANT = {
    "bg-primary": "ab-badge--primary",
    "bg-success": "ab-badge--success",
    "bg-warning": "ab-badge--warning",
    "bg-danger": "ab-badge--danger",
    "bg-info": "ab-badge--info",
    "bg-secondary": "",  # drop, default ab-badge is neutral
    "bg-dark": "",
    "bg-light": "",
}

# text-* color helpers when paired with badge — drop (handled by variant)
TEXT_DROP_WITH_BADGE = {"text-white", "text-dark", "text-light"}


def migrate_class_tokens(tokens: list[str]) -> list[str]:
    """Apply token-level mapping and contextual cleanups."""
    out: list[str] = []
    has_badge = any(t == "badge" for t in tokens) or any(
        t == "ab-badge" for t in tokens
    )

    # First pass: map every token via TOKEN_MAP (longest token first so
    # btn-outline-primary beats btn-primary etc. — we already ordered the
    # dict carefully, but iterate matched-longest-key-first to be safe).
    # Since dict iteration order is preserved, and our literal map keys
    # are explicit, simple direct lookup is fine.
    for t in tokens:
        if not t:
            continue

        # bg-* paired with badge → ab-badge--variant
        if has_badge and t in BG_TO_BADGE_VARIANT:
            mapped = BG_TO_BADGE_VARIANT[t]
            if mapped:
                out.append(mapped)
            continue

        # text-white etc paired with badge → drop
        if has_badge and t in TEXT_DROP_WITH_BADGE:
            continue

        if t in TOKEN_MAP:
            mapped = TOKEN_MAP[t]
            # multi-token expansion ("btn-outline-danger" → 2 tokens)
            for m in mapped.split():
                out.append(m)
            continue

        out.append(t)

    # Second pass: drop noise tokens
    cleaned: list[str] = []
    out_set = set(out)
    for t in out:
        if t in DROP_WHEN_WITH and DROP_WHEN_WITH[t] in out_set:
            continue
        # dedupe consecutive duplicates from expansions
        if cleaned and cleaned[-1] == t:
            continue
        cleaned.append(t)

    # Global dedupe while preserving order
    seen = set()
    final: list[str] = []
    for t in cleaned:
        if t in seen:
            continue
        seen.add(t)
        final.append(t)

    return final


# Match class="..." or :class="'...'"  attribute literals
CLASS_ATTR_RE = re.compile(
    r"""(\s(?:class|:class)\s*=\s*)(["'])(.*?)\2""",
    re.DOTALL,
)


def migrate_class_attr(match: re.Match) -> str:
    prefix, quote, value = match.group(1), match.group(2), match.group(3)
    # Don't try to parse Vue expressions like :class="{ foo: bar }"
    if value.strip().startswith("{") or value.strip().startswith("["):
        return match.group(0)
    tokens = value.split()
    new_tokens = migrate_class_tokens(tokens)
    return f"{prefix}{quote}{' '.join(new_tokens)}{quote}"


def migrate_file(path: Path) -> int:
    text = path.read_text(encoding="utf-8")
    new_text = CLASS_ATTR_RE.sub(migrate_class_attr, text)

    # Also handle :class="{ 'foo': cond }" patterns for `active` next to ab-tab
    # Replace "{ active: ... }" hash literals when neighbour ab-tab exists.
    # Pattern: class="ab-tab" :class="{ active: cond }"  →
    #          :class="['ab-tab', cond && 'ab-tab--active']"
    # Too risky to rewrite generically; instead do a targeted swap for the
    # common Vue inline pattern:  { active: ... }  →  { 'ab-tab--active': ... }
    # ONLY in files that already contain ab-tabs (so we know they're tab nav).
    if "ab-tabs" in new_text:
        new_text = re.sub(
            r"\{\s*active\s*:\s*([^}]+?)\s*\}",
            r"{ 'ab-tab--active': \1 }",
            new_text,
        )

    if new_text != text:
        path.write_text(new_text, encoding="utf-8")
        return 1
    return 0


def main() -> int:
    files = []
    for p in SRC.rglob("*.vue"):
        if p.name in EXCLUDE:
            continue
        files.append(p)

    changed = 0
    for f in sorted(files):
        if migrate_file(f):
            changed += 1
            print(f"  ✓ migrated {f.relative_to(ROOT)}")
        else:
            print(f"  · unchanged {f.relative_to(ROOT)}")

    print(f"\n{changed}/{len(files)} files modified")
    return 0


if __name__ == "__main__":
    sys.exit(main())
