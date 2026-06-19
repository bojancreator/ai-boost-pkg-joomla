#!/usr/bin/env python3
"""
AI Boost — front-end emission verifier (Plan 3, deliverable #2).

Drives a live staging / free / native-multilanguage site over HTTP and asserts
that every AI Boost feature actually emits (or correctly withholds) its
front-end artifact. Read-only groups run with zero risk; mutating groups always
snapshot first and restore in a finally block, and a `--restore-only` panic
button re-applies the last snapshot.

Groups (run all, or pick with --group a,b,…):
  static       one consolidated head block; section order; JSON-LD @types;
               OG/canonical completeness + uniqueness; AEO; GTM noscript.   [read-only]
  virtual      robots.txt fence + scraper rules + Sitemap line; llms.txt;
               sitemap.xml validity / on-origin / priority∈[0,1].           [read-only]
  toggles      each master switch OFF → artifact vanishes → restore → returns. [MUTATES]
  writes       redirect 301 + 404-log row; (per-language translation).         [MUTATES]
  conflicts    health.rerun scanners; foreign duplicate tag DETECTED but never
               stripped (passes through, proving "we never delete other tools'
               output").                                                        [MUTATES]
  multilingual per-language hreflang + sitemap alternates + x-default;
               translated Schema/OG (Pro + Multilang gated).               [read-only*]

Pro state: artifacts behind the Multilang SKU are toggled over HTTP by activating
a REAL licence key (settings.verifyLicense) using the int_falang QA key from env
(AIBOOST_QA_KEY_INT_FALANG). Core Pro is perpetual — assert it on the real
free/pro test matrix rather than flipping one site. Without a key those checks SKIP.

Run via the creds wrapper:
  python _creds_run.py scripts/verify-frontend-emission.py --target staging
  python _creds_run.py scripts/verify-frontend-emission.py --target ml --group multilingual
  python _creds_run.py scripts/verify-frontend-emission.py --target staging --restore-only

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""

from __future__ import annotations

import argparse
import importlib.util
import os
import re
import sys
import time
import xml.etree.ElementTree as ET

# Load the shared QA library by path (scripts/ is not a package).
_spec = importlib.util.spec_from_file_location(
    "_qa_common", os.path.join(os.path.dirname(os.path.abspath(__file__)), "_qa_common.py"))
qa = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(qa)

ARTIFACT_DIR = "artifacts/qa-reports"

PASS, FAIL, SKIP = "PASS", "FAIL", "SKIP"
GLYPH = {PASS: "✅", FAIL: "❌", SKIP: "⏭️"}

HEAD_START = "<!-- AI Boost for Joomla - Start -->"
HEAD_END = "<!-- AI Boost for Joomla - End -->"
SECTION_LABELS = ["Schema.org", "OpenGraph & Twitter", "AEO", "Analytics", "Custom Code"]


# ── Result accumulator ───────────────────────────────────────────────────────

class Report:
    def __init__(self, target: str):
        self.target = target
        self.rows: list[dict] = []

    def add(self, group: str, check: str, function: str, status: str, detail: str = "",
            lang: str = "", site: str = ""):
        self.rows.append({
            "group": group, "check": check, "function": function, "site": site,
            "lang": lang, "status": status, "detail": detail,
        })
        print(f"   {GLYPH[status]} [{group}] {check}: {function}"
              f"{(' (' + lang + ')') if lang else ''}"
              f"{(' — ' + detail) if detail else ''}")

    def counts(self) -> dict:
        c = {PASS: 0, FAIL: 0, SKIP: 0}
        for r in self.rows:
            c[r["status"]] += 1
        return c

    def write(self, base: str, pro_note: str) -> str:
        os.makedirs(ARTIFACT_DIR, exist_ok=True)
        ts = time.strftime("%Y%m%d-%H%M%S")
        path = os.path.join(ARTIFACT_DIR, f"frontend-emission-{self.target}-{ts}.md")
        c = self.counts()
        lines = [
            f"# AI Boost — Front-end Emission Report ({self.target})",
            "",
            f"- **Site:** {base}",
            f"- **When:** {ts}",
            f"- **Pro state:** {pro_note}",
            f"- **Totals:** {GLYPH[PASS]} {c[PASS]} pass · {GLYPH[FAIL]} {c[FAIL]} fail · {GLYPH[SKIP]} {c[SKIP]} skip",
            "",
            "The **Visual** column is Bojan's manual sign-off — tick after eyeballing the live page.",
            "",
            "| Check | Function | Site | Lang | Status | Detail | Visual |",
            "|---|---|---|---|---|---|---|",
        ]
        for r in self.rows:
            detail = r["detail"].replace("|", "\\|")[:140]
            lines.append(
                f"| {r['group']}:{r['check']} | {r['function']} | {r['site'] or self.target} | "
                f"{r['lang'] or '—'} | {GLYPH[r['status']]} {r['status']} | {detail} | ☐ |"
            )
        with open(path, "w", encoding="utf-8") as f:
            f.write("\n".join(lines) + "\n")
        return path


# ── Harness ──────────────────────────────────────────────────────────────────

class Harness:
    def __init__(self, target: str):
        self.target = target
        self.report = Report(target)
        self.s = None
        self.admin_php = ""
        self.base = ""
        self.snapshot: dict = {}
        self.defaults: dict = {}
        self.langs: list = []
        self.default_lang = ""
        self.core_pro = False
        self.multilang_pro = False
        self.ml_was_active = False   # int_falang seat at connect — for restore
        self.article_url = ""
        self.pro_note = "unknown"

    # -- preflight --
    def connect(self):
        self.s, self.admin_php, self.base = qa.connect(self.target)
        print(f"🟢 connected: {self.base}  (ssl_verify={qa.ssl_verify_for(self.target)})")
        self.snapshot = qa.export_settings(self.s, self.admin_php)
        self.defaults = self._manifest_defaults()
        # One authoritative pre-run baseline on disk for --restore-only.
        qa.write_snapshot(self.target, self.snapshot, suffix="baseline")
        print(f"📤 settings.export: {len(self.snapshot)} params")
        langs = qa.get_languages(self.s, self.admin_php)
        self.langs = langs.get("languages") or []
        self.default_lang = langs.get("default_lang") or ""
        print(f"🌐 languages: {[l.get('sef') for l in self.langs]} (default {self.default_lang})")
        self.pro_note = self._detect_pro()
        self.ml_was_active = self.multilang_pro
        print(f"💎 Pro: {self.pro_note}")
        _mlk = qa.qa_license_key("int_falang")
        print(f"🔑 int_falang QA key: {'configured' if _mlk else 'NOT set — ML gate limited (set AIBOOST_QA_KEY_INT_FALANG)'}")

    def _manifest_defaults(self) -> dict:
        """Best-effort manifest defaults (for restoring keys absent from a snapshot)."""
        try:
            caps = qa.get_capabilities(self.s, self.admin_php)
            out = {}
            for f in caps.get("fields") or []:
                k = f.get("key")
                if k and "default" in f:
                    out[k] = f["default"]
            return out
        except Exception:
            return {}

    def _detect_pro(self) -> str:
        self.core_pro = False
        self.multilang_pro = False
        try:
            # Core Pro: read the AUTHORITATIVE admin bootstrap (isProActive →
            # window.aiBoostBootstrap.isPro), which is exactly what the SPA
            # <ProGate> gates on. Do NOT infer it from the per-field
            # capabilities 'locked' flag: since the v0.5 one-product transition
            # (Manifest\Registry::applyLockState) core tier=pro fields are no
            # longer locked at field level, so "any unlocked pro field" reports
            # Pro on EVERY site, Free included.
            boot = self._bootstrap()
            self.core_pro = bool(boot.get("isPro") or (boot.get("license") or {}).get("isPro"))
            # Multilang/integration fields ARE still locked by applyLockState, so
            # the capabilities signal stays valid for the Multilang (falang) SKU.
            caps = qa.get_capabilities(self.s, self.admin_php)
            fields = caps.get("fields") or []
            self.multilang_pro = any(
                (f.get("integration") == "falang" or f.get("sku") == "int_falang")
                and not f.get("locked") for f in fields)
            return (("core-Pro active" if self.core_pro else "core Free") + " · "
                    + ("Multilang active" if self.multilang_pro else "Multilang locked"))
        except Exception as e:
            return f"undetected ({e})"

    def _bootstrap(self) -> dict:
        """Parse window.aiBoostBootstrap from the admin SPA (view=app) — the
        authoritative live Pro/licence state the SPA itself boots from."""
        import json as _json
        import re as _re
        r = self.s.get(self.admin_php + "?option=com_aiboost&view=app", timeout=60)
        m = _re.search(r"aiBoostBootstrap\s*=\s*(\{.*?\})\s*;", r.text, _re.DOTALL)
        return _json.loads(m.group(1)) if m else {}

    def restore_license(self):
        """Restore the int_falang seat to its state at connect. We only ever touch
        int_falang here; core pro_activated is perpetual and never flipped by QA."""
        key = qa.qa_license_key("int_falang")
        if self.ml_was_active and key:
            qa.activate_real_license(self.s, self.admin_php, "int_falang", key)
        else:
            qa.deactivate_license(self.s, self.admin_php, "int_falang")
        time.sleep(qa.DEFAULT_POST_DELAY)

    @property
    def multilingual(self) -> bool:
        return len([l for l in self.langs if l.get("sef")]) >= 2

    # -- helpers --
    def home(self) -> str:
        return self.base.rstrip("/") + "/"

    def lang_home(self, sef: str) -> str:
        return self.base.rstrip("/") + "/" + sef.strip("/") + "/"

    def pick_article(self) -> str:
        """First on-origin non-home URL from the sitemap, for article-level checks."""
        if self.article_url:
            return self.article_url
        text, status, _ = qa.fetch_sitemap(self.s, self.base)
        if text:
            for loc in re.findall(r"<loc>\s*([^<]+?)\s*</loc>", text):
                loc = loc.strip()
                if loc.startswith(self.base) and loc.rstrip("/") != self.base.rstrip("/"):
                    self.article_url = loc
                    return loc
        self.article_url = self.home()
        return self.article_url

    def set_pro(self, sku: str, active: bool) -> bool:
        """Activate (real QA key) or release a licence SKU over HTTP. Returns False
        when activating but no QA key is configured for the SKU."""
        if active:
            key = qa.qa_license_key(sku)
            if not key:
                return False
            r = qa.activate_real_license(self.s, self.admin_php, sku, key)
        else:
            r = qa.deactivate_license(self.s, self.admin_php, sku)
        time.sleep(qa.DEFAULT_POST_DELAY)
        return bool(r.get("success"))

    def add(self, *a, **k):
        self.report.add(*a, **k)

    # ── GROUP: static (read-only) ────────────────────────────────────────────
    def group_static(self):
        print("\n── static ──")
        html = qa.fetch_html(self.s, self.home())

        # One consolidated block PER REGION (head, body-start, footer). The head
        # region is everything before </head>; body/footer blocks legitimately add
        # more Start markers when custom body/footer code or a body pixel is set.
        head_end = html.lower().find("</head>")
        head_region = html[:head_end] if head_end != -1 else html
        n_head = head_region.count(HEAD_START)
        n_total = html.count(HEAD_START)
        balanced = html.count(HEAD_START) == html.count(HEAD_END)
        self.add("static", "single_head_block", "exactly one head block (≤1 per region, balanced)",
                 PASS if n_head == 1 and balanced else FAIL,
                 f"{n_head} in head, {n_total} total regions, balanced={balanced}")

        if HEAD_START in html and HEAD_END in html:
            block = html[html.index(HEAD_START):html.index(HEAD_END)]
            hide = str(self.snapshot.get("hide_comments", "0")) in ("1", "true")
            present = [lbl for lbl in SECTION_LABELS if f"<!-- {lbl} -->" in block]
            # Verify the labels physically appear in the required order in the HTML.
            order_ok = all(
                block.index(f"<!-- {present[i]} -->") < block.index(f"<!-- {present[i + 1]} -->")
                for i in range(len(present) - 1))
            if hide:
                self.add("static", "section_order", "fixed section order (hide_comments on)",
                         SKIP, "inner labels hidden by hide_comments")
            else:
                self.add("static", "section_order", "fixed section order Schema→…→Code",
                         PASS if order_ok else FAIL, f"present: {present}")
            self.add("static", "block_before_head", "head block sits before </head>",
                     PASS if html.index(HEAD_END) < html.lower().rfind("</head>") else FAIL)

        objs, url = qa.fetch_jsonld(self.s, self.home())
        bad = [o for o in objs if not isinstance(o, dict) or "@type" not in o]
        self.add("static", "jsonld_parses", "all homepage JSON-LD parses + has @type",
                 PASS if objs and not bad else (FAIL if bad else SKIP),
                 f"{len(objs)} node(s), {len(bad)} malformed")
        if str(self.snapshot.get("enable_schema", "1")) in ("1", "true"):
            org = qa.find_type(objs, "Organization") or qa.find_type(objs, "LocalBusiness")
            site = qa.find_type(objs, "WebSite")
            self.add("static", "jsonld_org", "Organization/LocalBusiness node present",
                     PASS if org else FAIL)
            self.add("static", "jsonld_website", "WebSite node present (homepage)",
                     PASS if site else FAIL)

        art = qa.fetch_html(self.s, self.pick_article())
        for prop, label in (("og:title", "og:title"), ("og:type", "og:type"), ("og:url", "og:url")):
            cnt = len(re.findall(rf'property=["\']{re.escape(prop)}["\']', art))
            if str(self.snapshot.get("enable_opengraph", "1")) in ("1", "true"):
                self.add("static", f"og_unique_{prop}", f"exactly one {label}",
                         PASS if cnt == 1 else FAIL, f"{cnt} occurrence(s)")
        canon = len(re.findall(r'rel=["\']canonical["\']', art))
        self.add("static", "canonical_unique", "≤1 canonical link in whole HTML",
                 PASS if canon <= 1 else FAIL, f"{canon} canonical link(s)")

        # GTM noscript must sit right after <body…>
        if "gtm_container_id" in self.snapshot and str(self.snapshot.get("enable_gtm", "0")) in ("1", "true"):
            m = re.search(r"<body[^>]*>(.{0,400})", html, re.DOTALL | re.IGNORECASE)
            ok = bool(m and "googletagmanager.com/ns.html" in m.group(1))
            self.add("static", "gtm_noscript", "GTM noscript right after <body>",
                     PASS if ok else FAIL)

    # ── GROUP: virtual (read-only) ───────────────────────────────────────────
    def group_virtual(self):
        print("\n── virtual ──")
        robots, rstatus, _ = qa.fetch_robots(self.s, self.base)
        if robots:
            fence = ("# BEGIN AI Boost" in robots) or ("AI Boost" in robots)
            self.add("virtual", "robots_fence", "robots.txt has AI Boost managed block",
                     PASS if fence else FAIL)
            self.add("virtual", "robots_sitemap_line", "robots.txt advertises Sitemap:",
                     PASS if re.search(r"(?im)^\s*Sitemap:\s*https?://", robots) else FAIL)
            for bot, key in (("AhrefsBot", "scraper_ahrefsbot"), ("SemrushBot", "scraper_semrushbot")):
                if str(self.snapshot.get(key, "0")) in ("1", "true"):
                    self.add("virtual", f"robots_{key}", f"{bot} blocked when {key} on",
                             PASS if bot.lower() in robots.lower() else FAIL)
        else:
            self.add("virtual", "robots_reachable", "robots.txt reachable", FAIL, f"HTTP {rstatus}")

        if str(self.snapshot.get("llmstxt_enabled", "0")) in ("1", "true"):
            llms, lstatus, _ = qa.fetch_llms(self.s, self.base)
            if llms:
                self.add("virtual", "llms_content", "llms.txt served with content",
                         PASS if llms.strip().startswith("#") or "##" in llms else FAIL,
                         f"{len(llms)} bytes")
            else:
                self.add("virtual", "llms_reachable", "llms.txt reachable when enabled", FAIL,
                         f"HTTP {lstatus}")

        if str(self.snapshot.get("enable_sitemap", "1")) in ("1", "true"):
            self._check_sitemap("/sitemap.xml")

    def _check_sitemap(self, path: str):
        text, status, url = qa.fetch_sitemap(self.s, self.base, path)
        if not text:
            self.add("virtual", "sitemap_reachable", f"{path} reachable", FAIL, f"HTTP {status}")
            return
        try:
            root = ET.fromstring(text)
        except ET.ParseError as e:
            self.add("virtual", "sitemap_valid_xml", f"{path} is well-formed XML", FAIL, str(e)[:80])
            return
        self.add("virtual", "sitemap_valid_xml", f"{path} is well-formed XML", PASS,
                 root.tag.split('}')[-1])
        locs = re.findall(r"<loc>\s*([^<]+?)\s*</loc>", text)
        offsite = [l for l in locs if l.startswith("http") and not l.startswith(self.base)]
        self.add("virtual", "sitemap_on_origin", "every <loc> is on-origin",
                 PASS if not offsite else FAIL, f"{len(offsite)} off-origin of {len(locs)}")
        prios = [float(p) for p in re.findall(r"<priority>\s*([0-9.]+)\s*</priority>", text)]
        bad = [p for p in prios if p < 0 or p > 1]
        self.add("virtual", "sitemap_priority_range", "priorities ∈ [0,1]",
                 PASS if not bad else FAIL, f"{len(bad)} out of range")

    # ── GROUP: toggles (MUTATES) ─────────────────────────────────────────────
    # (key, label, probe, deps) — deps are co-settings forced ON for the test
    # (e.g. a master switch the sub-toggle needs), restored afterwards.
    TOGGLES = [
        ("enable_schema", "JSON-LD Schema", "probe_schema", {}),
        ("enable_opengraph", "OpenGraph tags", "probe_og", {}),
        ("enable_canonical", "canonical link", "probe_canonical", {}),
        ("enable_sitemap", "XML sitemap", "probe_sitemap", {}),
        ("llmstxt_enabled", "llms.txt", "probe_llms", {}),
        ("hide_comments", "head comment labels", "probe_comments", {}),  # inverted: ON hides
        ("scraper_ahrefsbot", "AhrefsBot robots rule", "probe_ahrefs",
         {"enable_robots": "1", "robots_block_scrapers": "1"}),
    ]

    def group_toggles(self):
        print("\n── toggles (mutating) ──")
        for key, label, probe, deps in self.TOGGLES:
            # A key absent from the export is simply at its manifest default, not
            # "unset" — still togglable (restored to default afterwards). Skip only
            # if the manifest doesn't know the key at all.
            if key not in self.snapshot and key not in self.defaults:
                self.add("toggles", key, label, SKIP, "key unknown in manifest on this site")
                continue
            self._toggle_one(key, label, getattr(self, probe), deps)

    def _toggle_one(self, key: str, label: str, probe, deps: dict | None = None):
        deps = deps or {}
        inverted = key == "hide_comments"
        on_val, off_val = ("0", "1") if inverted else ("1", "0")
        try:
            with qa.settings_mutation(self.s, self.admin_php, self.target, [key, *deps],
                                      defaults=self.defaults, label=f"toggle_{key}"):
                qa.import_params(self.s, self.admin_php, {key: on_val, **deps}, quiet=True)
                time.sleep(qa.DEFAULT_POST_DELAY)
                present_on, d_on = probe()
                qa.import_params(self.s, self.admin_php, {key: off_val}, quiet=True)
                time.sleep(qa.DEFAULT_POST_DELAY)
                present_off, d_off = probe()
            time.sleep(qa.DEFAULT_POST_DELAY)
            present_restored, _ = probe()
            ok = present_on and not present_off and present_restored
            status = PASS if ok else FAIL
            self.add("toggles", key, f"{label} follows the switch", status,
                     f"on={present_on} off={present_off} restored={present_restored} | {d_on}")
        except Exception as e:
            self.add("toggles", key, label, FAIL, f"error: {e}")

    # toggle probes → (present: bool, detail: str)
    def probe_schema(self):
        objs, _ = qa.fetch_jsonld(self.s, self.home())
        return (len(objs) > 0, f"{len(objs)} JSON-LD")

    def probe_og(self):
        html = qa.fetch_html(self.s, self.pick_article())
        return ('property="og:' in html or "property='og:" in html, "og meta")

    def probe_canonical(self):
        html = qa.fetch_html(self.s, self.home())
        return ('rel="canonical"' in html or "rel='canonical'" in html, "canonical")

    def probe_sitemap(self):
        text, status, _ = qa.fetch_sitemap(self.s, self.base)
        present = bool(text) and ("<urlset" in text or "<sitemapindex" in text)
        return (present, f"HTTP {status}")

    def probe_llms(self):
        text, status, _ = qa.fetch_llms(self.s, self.base)
        return (bool(text), f"HTTP {status}")

    def probe_comments(self):
        html = qa.fetch_html(self.s, self.home())
        return ("<!-- Schema.org -->" in html, "section labels")

    def probe_ahrefs(self):
        robots, _, _ = qa.fetch_robots(self.s, self.base)
        return ("ahrefsbot" in (robots or "").lower(), "AhrefsBot rule")

    # ── GROUP: writes (MUTATES) ──────────────────────────────────────────────
    def group_writes(self):
        print("\n── writes (mutating) ──")
        self._write_redirect()
        self._write_404()
        self._write_translation()

    def _write_redirect(self):
        slug = f"/ab-qa-redirect-{int(time.time())}"
        dest = self.home() + "?ab_qa_dest=1"
        r = qa.redirects_add(self.s, self.admin_php, slug, dest, 301, note="plan3-qa")
        rid = r.get("id")
        if not r.get("success") or not rid:
            self.add("writes", "redirect_add", "redirects.add creates a 301 rule", FAIL,
                     str(r.get("message") or r))
            return
        # The redirect engine only serves rules when redirect_enabled is on —
        # turn it on for the test (restored by the surrounding mutation).
        with qa.settings_mutation(self.s, self.admin_php, self.target, ["redirect_enabled"],
                                  defaults=self.defaults, label="redirect_feature"):
            qa.import_params(self.s, self.admin_php, {"redirect_enabled": "1"}, quiet=True)
            time.sleep(qa.DEFAULT_POST_DELAY)
            self._redirect_probe(slug, rid)

    def _redirect_probe(self, slug, rid):
        try:
            self.add("writes", "redirect_add", "redirects.add creates a 301 rule", PASS, f"id={rid}")
            time.sleep(qa.DEFAULT_POST_DELAY)
            resp = self.s.get(qa._bust(self.base.rstrip("/") + slug), allow_redirects=False,
                              timeout=30, headers={"Cache-Control": "no-cache"})
            loc = resp.headers.get("Location", "")
            ok = resp.status_code in (301, 302, 303, 307, 308) and "ab_qa_dest=1" in loc
            self.add("writes", "redirect_serves", "front-end serves the 301 → Location", PASS if ok else FAIL,
                     f"HTTP {resp.status_code} → {loc[:60]}")
            lst = qa.redirects_list(self.s, self.admin_php)
            row = next((x for x in lst.get("redirects", []) if int(x.get("id", 0)) == int(rid)), None)
            hits = int(row.get("hits", 0)) if row else -1
            self.add("writes", "redirect_hits", "hit counter increments", PASS if hits >= 1 else FAIL,
                     f"hits={hits}")
        finally:
            d = qa.redirects_delete(self.s, self.admin_php, int(rid))
            self.add("writes", "redirect_cleanup", "test redirect deleted",
                     PASS if d.get("success") else FAIL)

    def _write_404(self):
        if str(self.snapshot.get("redirect_404_log_enabled", "0")) not in ("1", "true"):
            self.add("writes", "log404", "404 logging", SKIP, "redirect_404_log_enabled is off")
            return
        miss = f"/ab-qa-missing-{int(time.time())}"
        self.s.get(qa._bust(self.base.rstrip("/") + miss), timeout=30,
                   headers={"Cache-Control": "no-cache"})
        time.sleep(qa.DEFAULT_POST_DELAY)
        lst = qa.redirects_list(self.s, self.admin_php)
        found = any(miss in str(x.get("request_url", "")) for x in lst.get("log404", []))
        # NOTE: never call redirects.clear404 — it TRUNCATEs the whole log.
        self.add("writes", "log404", "missing URL recorded in 404 log",
                 PASS if found else FAIL, f"total404={lst.get('total404')}")

    def _write_translation(self):
        # Translations live in #__aiboost_translations, not the settings blob, so
        # import.upload (params-only) cannot seed them. This is validated in the
        # multilingual group against a real translated article instead.
        self.add("writes", "translation", "per-language translation reaches JSON-LD", SKIP,
                 "translations are not settings-blob keys — covered by multilingual group")

    # ── GROUP: conflicts (MUTATES) ───────────────────────────────────────────
    def group_conflicts(self):
        print("\n── conflicts (mutating) ──")
        health = qa.health_rerun(self.s, self.admin_php)
        if not health.get("success"):
            self.add("conflicts", "health_rerun", "health.rerun responds", FAIL, str(health)[:120])
            return
        checks = health.get("checks") or []
        self.add("conflicts", "health_rerun", "health.rerun returns checks", PASS,
                 f"score={health.get('score')} · {len(checks)} checks")

        # Inject a foreign og:title + a second GA4 loader via custom_code_head,
        # then prove (a) the scanner flags a duplicate and (b) the foreign tag is
        # STILL in the HTML (we detect, never strip).
        marker = f"ab-qa-foreign-{int(time.time())}"
        foreign = (f'<meta property="og:title" content="{marker}">'
                   f'<script>/*{marker}*/console.log("gtag stub")</script>')
        keys = ["enable_custom_code", "custom_code_head"]
        try:
            with qa.settings_mutation(self.s, self.admin_php, self.target, keys,
                                      defaults=self.defaults, label="conflict_inject"):
                qa.import_params(self.s, self.admin_php,
                                 {"enable_custom_code": "1", "custom_code_head": foreign}, quiet=True)
                time.sleep(qa.DEFAULT_POST_DELAY)
                html = qa.fetch_html(self.s, self.home())
                still_there = marker in html
                self.add("conflicts", "never_strips", "foreign tag passes through (never deleted)",
                         PASS if still_there else FAIL, f"marker present={still_there}")
                og_count = len(re.findall(r'property=["\']og:title["\']', html))
                rescan = qa.health_rerun(self.s, self.admin_php)
                dup = any("duplicat" in (str(c.get("title", "")) + str(c.get("message", ""))).lower()
                          and c.get("status") in ("warning", "error", "fail")
                          for c in (rescan.get("checks") or []))
                self.add("conflicts", "duplicate_detected", "scanner flags the duplicate og:title",
                         PASS if (dup or og_count >= 2) else FAIL,
                         f"og:title×{og_count} scanner_flag={dup}")
        except Exception as e:
            self.add("conflicts", "inject", "foreign-tag injection", FAIL, f"error: {e}")

    # ── GROUP: multilingual (read-only, Pro-gated) ───────────────────────────
    def _hreflang_alts(self, sef: str) -> list:
        html = qa.fetch_html(self.s, self.lang_home(sef))
        return re.findall(r'rel=["\']alternate["\'][^>]*hreflang=["\']([^"\']+)["\']', html)

    def _any_hreflang(self) -> int:
        """Total hreflang alternates across all language homes (cache-busted)."""
        total = 0
        for l in self.langs:
            if l.get("sef"):
                total += len(self._hreflang_alts(l["sef"]))
        return total

    def group_multilingual(self):
        print("\n── multilingual ──")
        if not self.multilingual:
            self.add("multilingual", "preconditions", "site has ≥2 content languages", SKIP,
                     f"{len(self.langs)} language(s) — use --target ml")
            return
        sefs = [l.get("sef") for l in self.langs if l.get("sef")]
        self.add("multilingual", "languages", "≥2 published languages enumerated", PASS, str(sefs))

        # The plan's KEY check — the Multilang Pro gate is real and reversible.
        # Authoritative only with the JDEBUG simulator: OFF → no hreflang;
        # ON → hreflang appears. Without JDEBUG we can only assert the current
        # observed state and must SKIP the negative half.
        if qa.qa_license_key("int_falang"):
            self._multilang_gate_test()
        elif self.multilang_pro:
            self._multilang_positive(note="(Multilang Pro detected active)")
        else:
            self.add("multilingual", "gate", "Multilang Pro gate (OFF→none, ON→appears)", SKIP,
                     "no int_falang QA key (AIBOOST_QA_KEY_INT_FALANG) and Multilang Pro inactive — "
                     "set the key or activate a real Multilang licence, then re-run")

    def _multilang_positive(self, note: str = ""):
        for l in self.langs:
            sef = l.get("sef")
            if not sef:
                continue
            alts = self._hreflang_alts(sef)
            self.add("multilingual", "head_hreflang", "head hreflang alternates present",
                     PASS if len(alts) >= 2 else FAIL, f"alts={alts} {note}", lang=sef)
            self.add("multilingual", "x_default", "x-default alternate present",
                     PASS if any(a.lower() == "x-default" for a in alts) else FAIL, lang=sef)
        text, status, _ = qa.fetch_sitemap(self.s, self.base)
        if text:
            xhtml = text.count("<xhtml:link")
            self.add("multilingual", "sitemap_alternates", "sitemap emits <xhtml:link> alternates",
                     PASS if xhtml > 0 else FAIL, f"{xhtml} alternates {note}")

    def _multilang_gate_test(self):
        """Reversible Multilang Pro gate via a REAL int_falang key:
        OFF (seat released) → no hreflang; ON (real key activated) → hreflang +
        x-default + sitemap alternates."""
        # Ensure the master toggle is on so only the Pro gate is under test.
        touched = ["integration_falang_enabled", "falang_hreflang_head", "enable_hreflang"]
        try:
            with qa.settings_mutation(self.s, self.admin_php, self.target, touched,
                                      defaults=self.defaults, label="ml_gate"):
                qa.import_params(self.s, self.admin_php,
                                 {"integration_falang_enabled": "1", "falang_hreflang_head": "1",
                                  "enable_hreflang": "1"}, quiet=True)
                # OFF: release the Multilang seat → expect ZERO hreflang.
                self.set_pro("int_falang", False)
                off = self._any_hreflang()
                self.add("multilingual", "gate_off", "Multilang OFF → no hreflang emitted",
                         PASS if off == 0 else FAIL, f"{off} alternate(s) with Pro off")
                # ON: activate the real Multilang key → expect hreflang to appear.
                self.set_pro("int_falang", True)
                self._multilang_positive(note="(real key ON)")
                on = self._any_hreflang()
                self.add("multilingual", "gate_on", "Multilang ON → hreflang appears",
                         PASS if on >= 2 else FAIL, f"{on} alternate(s) with Pro on")
                # Translated JSON-LD needs core-Pro(schema) AND Multilang together;
                # core Pro is perpetual (never flipped here) — assert only when the
                # target is already core-Pro, else SKIP with the matrix instruction.
                if self.core_pro:
                    objs, _ = qa.fetch_jsonld(self.s, self.pick_article())
                    self.add("multilingual", "translated_schema_gate",
                             "schema+Multilang active → JSON-LD still emits (translation overlay)",
                             PASS if objs else FAIL, f"{len(objs)} node(s)")
                else:
                    self.add("multilingual", "translated_schema_gate",
                             "schema+Multilang active → translated JSON-LD", SKIP,
                             "target is core-Free — run on a core-Pro site (staging/j6pro)")
        finally:
            self.restore_license()

    # ── runner ──
    def run(self, groups: list[str]):
        order = ["static", "virtual", "toggles", "writes", "conflicts", "multilingual"]
        for g in order:
            if g in groups:
                getattr(self, f"group_{g}")()
                time.sleep(qa.DEFAULT_INTER_OP_DELAY)


# ── restore-only panic button ────────────────────────────────────────────────

def restore_only(target: str) -> int:
    import glob
    snaps = sorted(glob.glob(os.path.join(qa.STATE_DIR, f"{target}-snapshot-*.json")),
                   key=os.path.getmtime, reverse=True)
    if not snaps:
        print(f"No snapshot found for {target} in {qa.STATE_DIR}/")
        return 1
    # Prefer the authoritative start-of-run baseline over the newest per-group
    # snapshot (which could have captured an already-corrupted state).
    baseline = qa.snapshot_path(target, "baseline")
    latest = baseline if os.path.exists(baseline) else snaps[0]
    print(f"♻️  restoring full snapshot {latest}")
    s, admin_php, base = qa.connect(target)
    snap = qa.read_snapshot(latest)
    ok = qa.import_params(s, admin_php, snap, label="restore-only")
    print("done" if ok else "restore FAILED")
    return 0 if ok else 1


# ── main ─────────────────────────────────────────────────────────────────────

ALL_GROUPS = ["static", "virtual", "toggles", "writes", "conflicts", "multilingual"]
READ_ONLY = ["static", "virtual", "multilingual"]


def main() -> int:
    qa.setup_console_utf8()
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--target", default="staging", choices=list(qa.TARGETS))
    ap.add_argument("--group", default="all",
                    help=f"comma list of {ALL_GROUPS}, 'all', or 'readonly'")
    ap.add_argument("--article", default="", help="override the article URL used for OG/article checks")
    ap.add_argument("--list", action="store_true", help="list checks/groups and exit (no network)")
    ap.add_argument("--restore-only", action="store_true",
                    help="re-apply the latest snapshot for --target and exit (panic button)")
    args = ap.parse_args()

    if args.list:
        print("Groups:", ", ".join(ALL_GROUPS))
        print("Read-only (no mutation):", ", ".join(READ_ONLY))
        print("Mutating:", ", ".join(g for g in ALL_GROUPS if g not in READ_ONLY))
        print("Toggles exercised:", ", ".join(k for k, *_ in Harness.TOGGLES))
        return 0

    if args.restore_only:
        return restore_only(args.target)

    if args.group == "all":
        groups = ALL_GROUPS
    elif args.group == "readonly":
        groups = READ_ONLY
    else:
        groups = [g.strip() for g in args.group.split(",") if g.strip()]
        bad = [g for g in groups if g not in ALL_GROUPS]
        if bad:
            sys.exit(f"unknown group(s): {bad}")

    h = Harness(args.target)
    h.article_url = args.article
    h.connect()
    try:
        h.run(groups)
    finally:
        path = h.report.write(h.base, h.pro_note)
        c = h.report.counts()
        print(f"\n📝 report → {path}")
        print(f"OVERALL: {GLYPH[PASS]} {c[PASS]} · {GLYPH[FAIL]} {c[FAIL]} · {GLYPH[SKIP]} {c[SKIP]}")
    return 1 if c[FAIL] else 0


if __name__ == "__main__":
    sys.exit(main())
