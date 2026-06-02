#!/usr/bin/env python3
"""
AI Boost — Manifest-driven Codegen (Tasks #462, #469)

Reads the per-tab manifests in `component/lib/src/Manifest/*.php` and
generates the artifacts that used to be hand-written for every option:

  1. PHP feature handler stubs for Pro fields with `feature_class`,
     written to `component/plugins/system/aiboost_{sku}_pro/src/Features/{Class}.php`.
     Idempotent — NEVER overwrites an existing file (so manual logic survives).

  2. en-GB `.ini` placeholder keys for the per-plugin language file when
     a manifest field declares an `i18n.label_key` / `i18n.description_key`
     and the key is missing from the .ini. Appends; never edits or removes.

  3. Vue form-field partials (Task #469) for every field of a supported
     simple type (toggle / text / textarea / select / number), written to
     `component/com_aiboost/vue-admin/src/tabs/generated/{tab}/{key}.vue`.
     These files are pure derivations of the manifest — they are
     OVERWRITTEN on every run. Hand-written tabs continue to render the
     same fields manually; the generated partials are an opt-in
     scaffold devs can import as refactors land. Complex types
     (json, media) are skipped — those stay hand-written.

  4. PHP Health override stubs (Task #469) for every field with a
     `health` block, written to
     `component/lib/src/Manifest/Health/{HealthClass}.php`. Idempotent —
     NEVER overwrites. `HealthCheckService::registerFromManifest()`
     looks up the class at runtime and calls `evaluate($settings, $ctx)`
     for richer pass/fail logic; the default stub just reports the
     option as enabled.

  5. A diagnostic report of every field's Health-check binding plus a
     hand-written-field coverage report for the complex (un-codegenned)
     types. In `--check` mode the build fails if any complex-typed
     field is missing both a generated partial and a hand-written
     `data-ab-field="{key}"` occurrence in the existing tabs.

Usage:
    python3 scripts/codegen-from-manifest.py
    python3 scripts/codegen-from-manifest.py --check     # exit 1 on missing artifacts
    python3 scripts/codegen-from-manifest.py --verbose
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

WORKSPACE   = Path(__file__).resolve().parent.parent
COMPONENT   = WORKSPACE / "component"
PLUGINS_DIR = COMPONENT / "plugins" / "system"
LIB_DIR     = COMPONENT / "lib" / "src"
VUE_TABS    = COMPONENT / "com_aiboost" / "vue-admin" / "src" / "tabs"
VUE_GEN     = VUE_TABS / "generated"
HEALTH_DIR  = LIB_DIR / "Manifest" / "Health"
DUMP_PHP    = Path(__file__).with_name("dump-manifest.php")

# Map manifest sku → physical Pro plugin folder name
SKU_TO_PRO_DIR = {
    "schema":   "aiboost_schema_pro",
    "aeo":      "aiboost_aeo_pro",
    "og":       "aiboost_social_pro",
    "hreflang": "aiboost_hreflang_pro",
    "code":     "aiboost_code_pro",
}

# Map manifest tab → free plugin folder whose .ini hosts the placeholder
# labels (Pro plugins ship their own .ini, but the SPA reads the parent
# tab's label set, which lives in the free plugin's language file).
TAB_TO_FREE_DIR = {
    "schema":   "aiboost_schema",
    "aeo":      "aiboost_aeo",
    "og":       "aiboost_social",
    "social":   "aiboost_social",   # manifest uses tab=social for OG fields
    "hreflang": "aiboost_aeo",      # hreflang is rendered inside AEO tab today
    "code":     "aiboost_code",
    "core":     "aiboost_core",
}

# Tabs that host hand-written controls for each manifest tab. Used by the
# coverage check for complex types (json / media) the codegen can't emit.
TAB_TO_VUE_FILES = {
    "schema":   ["SchemaTab.vue"],
    "aeo":      ["AeoTab.vue"],
    "og":       ["SocialTab.vue"],
    "social":   ["SocialTab.vue", "AnalyticsTab.vue"],
    "hreflang": ["AeoTab.vue", "SitemapTab.vue"],
    "code":     ["CodeTab.vue"],
    "core":     ["GeneralTab.vue", "OrgTab.vue", "AnalyticsTab.vue", "SitemapTab.vue"],
}

# Field types the Vue codegen knows how to render. Everything else is
# treated as a "complex" type that must have a hand-written control.
SIMPLE_VUE_TYPES = {"toggle", "text", "textarea", "select", "number"}

# Manifest keys whose complex (json/media) UI is intentionally not yet
# implemented. The build guard skips them. Remove an entry from here in
# the same commit that adds its hand-written control.
COMPLEX_COVERAGE_ALLOWLIST = {
    "meta_pixel_standard_events",  # advanced Meta Pixel events (Pro, future task)
    "meta_custom_events",          # custom Meta Pixel events (Pro, future task)
}


def load_manifest() -> list[dict]:
    if not DUMP_PHP.exists():
        sys.exit(f"ERROR: {DUMP_PHP} not found")
    out = subprocess.run(
        ["php", str(DUMP_PHP)], capture_output=True, text=True, check=False
    )
    if out.returncode != 0:
        sys.exit(f"ERROR: dump-manifest.php failed:\n{out.stderr}")
    try:
        return json.loads(out.stdout)
    except json.JSONDecodeError as e:
        sys.exit(f"ERROR: manifest is not valid JSON: {e}\n--- raw ---\n{out.stdout[:500]}")


def gen_feature_stub(field: dict, write: bool) -> tuple[str, str]:
    """Return (status, path). status ∈ {'generated', 'exists', 'skip', 'error'}."""
    sku   = field.get("sku") or ""
    cls   = field.get("feature_class") or ""
    if not cls:
        return ("skip", "")
    pro_dir = SKU_TO_PRO_DIR.get(sku)
    if not pro_dir:
        return ("error", f"unknown sku '{sku}' for feature_class={cls}")
    target = PLUGINS_DIR / pro_dir / "src" / "Features" / f"{cls}.php"
    if target.exists():
        return ("exists", str(target.relative_to(WORKSPACE)))

    ns = {
        "aiboost_schema_pro":   "AiBoost\\Plugin\\System\\AiBoostSchemaPro\\Features",
        "aiboost_aeo_pro":      "AiBoost\\Plugin\\System\\AiBoostAeoPro\\Features",
        "aiboost_social_pro":   "AiBoost\\Plugin\\System\\AiBoostSocialPro\\Features",
        "aiboost_hreflang_pro": "AiBoost\\Plugin\\System\\AiBoostHreflangPro\\Features",
        "aiboost_code_pro":     "AiBoost\\Plugin\\System\\AiBoostCodePro\\Features",
    }[pro_dir]

    key   = field.get("key", "")
    label = field.get("label", "")
    desc  = field.get("description", "")
    stub = f"""<?php
/**
 * AI Boost — Pro feature handler: {cls}
 *
 * Auto-generated stub by scripts/codegen-from-manifest.py for manifest
 * key `{key}` (tier=pro, sku={sku}). The codegen script will NEVER
 * overwrite this file once it exists, so it is safe to fill in real
 * Pro logic below the `// @pro:start` marker.
 *
 * Label  : {label}
 * Purpose: {desc}
 *
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace {ns};

defined('_JEXEC') or die;

final class {cls}
{{
    /** Manifest key that gates this handler. */
    public const SETTING_KEY = '{key}';

    /**
     * Return true if this Pro feature is enabled in #__aiboost_settings.
     *
     * @param array<string,mixed> $settings  Decoded settings_json blob.
     */
    public static function isEnabled(array $settings): bool
    {{
        return !empty($settings[self::SETTING_KEY]);
    }}

    // @pro:start
    /**
     * Apply this feature's effect. Called by the parent Pro plugin's
     * event handler (e.g. onBeforeCompileHead). Replace this stub with
     * real logic; the // @pro:start ... // @pro:end markers guarantee
     * the block is stripped out of the free package by build-package-zip.py.
     *
     * @param array<string,mixed> $settings
     */
    public static function apply(array $settings): void
    {{
        // TODO: implement Pro logic for {key}.
    }}
    // @pro:end
}}
"""

    if write:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(stub, encoding="utf-8")
    return ("generated", str(target.relative_to(WORKSPACE)))


# ── INI placeholder generation ─────────────────────────────────────────

_INI_KEY_RE = re.compile(r'^\s*([A-Z0-9_]+)\s*=', re.MULTILINE)


def existing_ini_keys(ini_path: Path) -> set[str]:
    if not ini_path.exists():
        return set()
    return set(_INI_KEY_RE.findall(ini_path.read_text(encoding="utf-8", errors="replace")))


def gen_ini_keys(field: dict, write: bool) -> list[tuple[str, str, str]]:
    """Return list of (status, ini_path, key). status ∈ {'added','exists','skip'}."""
    i18n = field.get("i18n") or {}
    if not i18n:
        return [("skip", "", "")]

    tab = field.get("tab") or "core"
    free_dir = TAB_TO_FREE_DIR.get(tab)
    if not free_dir:
        return [("skip", "", "")]
    ini_path = PLUGINS_DIR / free_dir / "language" / "en-GB" / f"plg_system_{free_dir}.ini"
    if not ini_path.exists():
        return [("skip", str(ini_path.relative_to(WORKSPACE) if ini_path.is_absolute() else ini_path), "ini missing")]

    keys = existing_ini_keys(ini_path)
    results: list[tuple[str, str, str]] = []
    pending: list[tuple[str, str]] = []  # (key, value)

    label_key = i18n.get("label_key")
    desc_key  = i18n.get("description_key")
    if label_key:
        if label_key in keys:
            results.append(("exists", str(ini_path.relative_to(WORKSPACE)), label_key))
        else:
            pending.append((label_key, field.get("label", "")))
            results.append(("added", str(ini_path.relative_to(WORKSPACE)), label_key))
    if desc_key:
        if desc_key in keys:
            results.append(("exists", str(ini_path.relative_to(WORKSPACE)), desc_key))
        else:
            pending.append((desc_key, field.get("description", "")))
            results.append(("added", str(ini_path.relative_to(WORKSPACE)), desc_key))

    if pending and write:
        block_lines = ["", f"; Codegen — manifest key {field.get('key','')}"]
        for k, v in pending:
            esc = v.replace('"', '\\"')
            block_lines.append(f'{k}="{esc}"')
        block_lines.append("")
        with ini_path.open("a", encoding="utf-8") as fh:
            fh.write("\n".join(block_lines))
    return results


# ── Vue field-partial codegen (Task #469) ──────────────────────────────

def _vue_html_attr_escape(v: str) -> str:
    return v.replace("&", "&amp;").replace('"', "&quot;").replace("<", "&lt;").replace(">", "&gt;")


def _vue_body_for(field: dict) -> str:
    """Render the field's body without the outer card / ProGate wrapper."""
    key   = field["key"]
    label = _vue_html_attr_escape(field.get("label") or key)
    desc  = _vue_html_attr_escape(field.get("description") or "")
    typ   = field.get("type")
    el_id = "ab-gen-" + key.replace("_", "-")

    help_block = f'      <div class="ab-help">{desc}</div>\n' if desc else ""

    if typ == "toggle":
        return (
            '    <div class="ab-check ab-toggle mb-3">\n'
            f'      <input v-model="s.{key}" data-ab-field="{key}"\n'
            '             true-value="1" false-value="0"\n'
            f'             type="checkbox" class="ab-toggle__input" id="{el_id}" />\n'
            f'      <label class="ab-check__label" for="{el_id}">{label}</label>\n'
            f'{help_block}'
            '    </div>\n'
        )

    if typ == "text":
        return (
            '    <div class="mb-3">\n'
            f'      <label class="ab-label" for="{el_id}">{label}</label>\n'
            f'      <input v-model="s.{key}" data-ab-field="{key}"\n'
            f'             type="text" class="ab-input" id="{el_id}" />\n'
            f'{help_block}'
            '    </div>\n'
        )

    if typ == "number":
        return (
            '    <div class="mb-3">\n'
            f'      <label class="ab-label" for="{el_id}">{label}</label>\n'
            f'      <input v-model="s.{key}" data-ab-field="{key}"\n'
            f'             type="number" class="ab-input" id="{el_id}" style="max-width:160px" />\n'
            f'{help_block}'
            '    </div>\n'
        )

    if typ == "textarea":
        return (
            '    <div class="mb-3">\n'
            f'      <label class="ab-label" for="{el_id}">{label}</label>\n'
            f'      <textarea v-model="s.{key}" data-ab-field="{key}"\n'
            f'                class="ab-input" rows="4" id="{el_id}"></textarea>\n'
            f'{help_block}'
            '    </div>\n'
        )

    if typ == "select":
        opts = field.get("options") or {}
        opt_lines = []
        for ov, ol in opts.items():
            opt_lines.append(
                f'        <option value="{_vue_html_attr_escape(str(ov))}">{_vue_html_attr_escape(str(ol))}</option>'
            )
        opts_html = "\n".join(opt_lines) if opt_lines else ""
        return (
            '    <div class="mb-3">\n'
            f'      <label class="ab-label" for="{el_id}">{label}</label>\n'
            f'      <select v-model="s.{key}" data-ab-field="{key}"\n'
            f'              class="ab-select" id="{el_id}">\n'
            f'{opts_html}\n'
            '      </select>\n'
            f'{help_block}'
            '    </div>\n'
        )

    raise ValueError(f"unsupported vue type {typ!r}")


def gen_vue_partial(field: dict, write: bool) -> tuple[str, str]:
    """Generate Vue partial. status ∈ {'generated','skip','unchanged'}.

    Always overwrites — these files are pure derivations of the manifest.
    """
    typ = field.get("type")
    if typ not in SIMPLE_VUE_TYPES:
        return ("skip", "")

    tab = field.get("tab") or "core"
    key = field["key"]
    target = VUE_GEN / tab / f"{key}.vue"

    tier = field.get("tier") or "free"
    body = _vue_body_for(field)
    if tier == "pro":
        gate_key = key  # ProGate field-level gating uses the settings key
        body = (
            f'    <ProGate gate-key="{gate_key}" mode="field">\n'
            + "".join("  " + ln if ln.strip() else ln for ln in body.splitlines(keepends=True))
            + '    </ProGate>\n'
        )

    component_name = "Generated" + "".join(p.capitalize() for p in key.split("_"))

    template = (
        "<!--\n"
        "  AI Boost — auto-generated form field.\n"
        f"  Source : component/lib/src/Manifest/{tab}.php (key={key})\n"
        "  Codegen: scripts/codegen-from-manifest.py\n"
        "  DO NOT EDIT — re-run codegen to regenerate.\n"
        "-->\n"
        '<template>\n'
        '  <div class="ab-gen-field">\n'
        f'{body}'
        '  </div>\n'
        '</template>\n'
        '\n'
        '<script>\n'
        'export default {\n'
        f"  name: '{component_name}',\n"
        '  props: { s: { type: Object, required: true } },\n'
        '}\n'
        '</script>\n'
    )

    if target.exists() and target.read_text(encoding="utf-8") == template:
        return ("unchanged", str(target.relative_to(WORKSPACE)))

    if write:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(template, encoding="utf-8")
    return ("generated", str(target.relative_to(WORKSPACE)))


# ── Health override stub codegen (Task #469) ───────────────────────────

def health_class_name(health_id: str) -> str:
    parts = re.split(r"[^A-Za-z0-9]+", health_id)
    return "".join(p.capitalize() for p in parts if p)


def gen_health_stub(field: dict, write: bool) -> tuple[str, str]:
    """Generate a Health override stub. status ∈ {'generated','exists','skip'}.

    Idempotent — NEVER overwrites. Devs fill in real evaluate() logic.
    """
    h = field.get("health") or None
    if not h or not h.get("id"):
        return ("skip", "")
    cls = health_class_name(h["id"])
    target = HEALTH_DIR / f"{cls}.php"
    if target.exists():
        return ("exists", str(target.relative_to(WORKSPACE)))

    key       = field.get("key", "")
    label     = field.get("label", "")
    hid       = h["id"]
    category  = h.get("category", "General")
    message   = (h.get("message") or "").replace("'", "\\'")
    expected  = (h.get("expected_artifact") or "").replace("'", "\\'")

    stub = f"""<?php
/**
 * AI Boost — Health override: {cls}
 *
 * Auto-generated stub by scripts/codegen-from-manifest.py for manifest
 * health id `{hid}` (field key=`{key}`). NEVER overwritten, so it's safe
 * to replace the default `evaluate()` with real pass/fail logic.
 *
 * When this class exists, HealthCheckService::registerFromManifest()
 * calls evaluate($settings, $ctx) and uses the returned struct in place
 * of the always-pass default. Return an associative array with keys:
 *   pass (bool), message (string|null), fix_actions (array|null).
 * Any key omitted falls back to the manifest declaration.
 *
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\\Lib\\Manifest\\Health;

defined('_JEXEC') or die;

use AiBoost\\Lib\\AppContextInterface;

final class {cls}
{{
    public const HEALTH_ID   = '{hid}';
    public const SETTING_KEY = '{key}';
    public const CATEGORY    = '{category}';
    public const LABEL       = '{label}';

    /**
     * Evaluate the check. Default: report pass=true whenever the option
     * is enabled (same as the manifest-driven runtime fallback). Replace
     * with real probing logic to validate the expected HTML artifact.
     *
     * @param array<string,mixed>  $settings
     * @return array{{pass?: bool, message?: string, fix_actions?: array<int,array<string,string>>}}
     */
    public static function evaluate(array $settings, AppContextInterface $ctx): array
    {{
        $on = !empty($settings[self::SETTING_KEY]);
        return [
            'pass'    => $on,
            'message' => $on
                ? 'Expected artifact: {expected}.'
                : 'Option is disabled — no artifact emitted.',
        ];
    }}
}}
"""
    if write:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(stub, encoding="utf-8")
    return ("generated", str(target.relative_to(WORKSPACE)))


# ── Coverage / verification ────────────────────────────────────────────

def verify_health_coverage(fields: list[dict]) -> list[str]:
    """Advisory: list Pro fields with no `health` block."""
    return [
        f"  Pro field '{f.get('key')}' (sku={f.get('sku')}) has no `health` block"
        for f in fields
        if (f.get("tier") or "free") == "pro" and not f.get("health")
    ]


def _read_tab_source(tab: str) -> str:
    out = []
    for fname in TAB_TO_VUE_FILES.get(tab, []):
        p = VUE_TABS / fname
        if p.exists():
            out.append(p.read_text(encoding="utf-8", errors="replace"))
    return "\n".join(out)


def verify_complex_field_coverage(fields: list[dict]) -> list[str]:
    """STRICT: every complex-typed field (json/media) must appear in a
    hand-written tab as data-ab-field="key". Simple types are always
    covered by the generated partial."""
    errors: list[str] = []
    cache: dict[str, str] = {}
    for f in fields:
        if f.get("type") in SIMPLE_VUE_TYPES:
            continue
        key = f["key"]
        if key in COMPLEX_COVERAGE_ALLOWLIST:
            continue
        tab = f.get("tab") or "core"
        if tab not in cache:
            cache[tab] = _read_tab_source(tab)
        if f'data-ab-field="{key}"' not in cache[tab]:
            errors.append(
                f"  field '{key}' (type={f.get('type')}, tab={tab}) has neither a "
                f"codegen-able type nor a hand-written data-ab-field control"
            )
    return errors


# ── Main ───────────────────────────────────────────────────────────────

def main() -> int:
    p = argparse.ArgumentParser(description="AI Boost manifest-driven codegen")
    p.add_argument("--check", action="store_true",
                   help="Exit 1 if any artifact is missing; do not write files.")
    p.add_argument("--verbose", action="store_true", help="Print every status line.")
    args = p.parse_args()

    write = not args.check

    print("── AI Boost codegen ────────────────────────────────────")
    print(f"  Workspace: {WORKSPACE}")
    print(f"  Mode     : {'check (read-only)' if args.check else 'write'}\n")

    fields = load_manifest()
    print(f"  Manifest fields loaded: {len(fields)}\n")

    stub_gen = stub_exists = stub_err = 0
    ini_added = ini_exists = 0
    vue_gen = vue_unchanged = vue_skip = 0
    health_gen = health_exists = 0

    for f in fields:
        # 1. PHP feature handler stub
        status, info = gen_feature_stub(f, write=write)
        if status == "generated":
            stub_gen += 1
            print(f"  [stub]   + {info}")
        elif status == "exists":
            stub_exists += 1
            if args.verbose:
                print(f"  [stub]   = {info} (exists)")
        elif status == "error":
            stub_err += 1
            print(f"  [stub]   ! {info}")

        # 2. .ini placeholder keys
        for st, pth, key in gen_ini_keys(f, write=write):
            if st == "added":
                ini_added += 1
                print(f"  [ini]    + {pth}  {key}")
            elif st == "exists":
                ini_exists += 1
                if args.verbose:
                    print(f"  [ini]    = {pth}  {key}")

        # 3. Vue field partial (Task #469)
        status, info = gen_vue_partial(f, write=write)
        if status == "generated":
            vue_gen += 1
            print(f"  [vue]    + {info}")
        elif status == "unchanged":
            vue_unchanged += 1
            if args.verbose:
                print(f"  [vue]    = {info} (unchanged)")
        elif status == "skip":
            vue_skip += 1

        # 4. Health override stub (Task #469)
        status, info = gen_health_stub(f, write=write)
        if status == "generated":
            health_gen += 1
            print(f"  [health] + {info}")
        elif status == "exists":
            health_exists += 1
            if args.verbose:
                print(f"  [health] = {info} (exists)")

    warnings = verify_health_coverage(fields)
    if warnings:
        print("\n  Health coverage warnings:")
        for w in warnings:
            print(w)

    complex_errors = verify_complex_field_coverage(fields)
    if complex_errors:
        print("\n  ❌ Complex-field coverage errors:")
        for e in complex_errors:
            print(e)

    print("\n── Summary ──")
    print(f"  Feature stubs : {stub_gen} generated, {stub_exists} existed, {stub_err} errors")
    print(f"  INI keys      : {ini_added} added, {ini_exists} present")
    print(f"  Vue partials  : {vue_gen} generated, {vue_unchanged} unchanged, {vue_skip} skipped (complex)")
    print(f"  Health stubs  : {health_gen} generated, {health_exists} existed")
    print(f"  Complex cover : {len(complex_errors)} errors, {len(warnings)} health gaps (advisory)")

    if args.check:
        # STRICT in --check: any artifact that needs writing or any
        # complex-typed field without a hand-written control fails.
        # Health gaps remain advisory (pre-existing Pro fields are
        # being wrapped incrementally, separately tracked).
        if stub_gen or ini_added or stub_err or vue_gen or health_gen or complex_errors:
            print("\n  CHECK FAILED — missing artifacts or coverage gaps.")
            return 1
        if warnings:
            print(f"\n  ✓ All manifest artifacts present ({len(warnings)} health gaps — advisory).")
        else:
            print("\n  ✓ All manifest artifacts present.")
        return 0

    if stub_err:
        return 1
    print("\n  ✓ Codegen complete.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
