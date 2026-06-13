#!/usr/bin/env python3
"""
AI Boost ops — safely flip Joomla "Debug System" (JDEBUG) on a target via the
browser's NATIVE Global-Configuration form submit (so no other config field is
disturbed). JDEBUG is required for the harness's HTTP Pro-simulator path
(settings.simulatorSave), used to verify Multilang/hreflang gating.

Usage (creds via the wrapper):
  python _creds_run.py scripts/toggle-debug.py --target j6pro --on
  python _creds_run.py scripts/toggle-debug.py --target j6pro --off
testmyweb targets also need TESTMYWEB_NO_SSL_VERIFY=1 (self-signed TLS).

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""

from __future__ import annotations

import argparse
import importlib.util
import os
import sys
import time

from playwright.sync_api import sync_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location("_qa_common", os.path.join(HERE, "_qa_common.py"))
qa = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(qa)

SET_DEBUG_JS = """(val) => {
    const radios = document.querySelectorAll('input[name="jform[debug]"]');
    radios.forEach(r => { r.checked = (r.value === val); });
    const sel = document.querySelector('input[name="jform[debug]"][value="' + val + '"]');
    if (sel) { sel.checked = true; sel.dispatchEvent(new Event('change', {bubbles:true})); }
    return radios.length;
}"""

READ_DEBUG_JS = """() => {
    const c = document.querySelector('input[name="jform[debug]"]:checked');
    return c ? c.value : null;
}"""


def main() -> int:
    qa.setup_console_utf8()
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", required=True, choices=list(qa.TARGETS))
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--on", action="store_true")
    g.add_argument("--off", action="store_true")
    args = ap.parse_args()

    want = "1" if args.on else "0"
    admin_url, user, pw = qa.env(args.target)
    verify = qa.ssl_verify_for(args.target)
    base = qa.base_url(admin_url)
    config_url = base + "/administrator/index.php?option=com_config"
    print(f"🔧 {args.target}: set Debug System = {'Yes' if args.on else 'No'} ({base})")

    with sync_playwright() as p:
        b = p.chromium.launch(headless=True, args=["--no-sandbox", "--disable-dev-shm-usage"])
        ctx = b.new_context(viewport={"width": 1366, "height": 900}, ignore_https_errors=not verify)
        pg = ctx.new_page()

        # login
        pg.goto(admin_url, timeout=45000, wait_until="domcontentloaded")
        pg.wait_for_selector("input[name=username]", timeout=30000)
        pg.fill("input[name=username]", user)
        pg.fill("input[name=passwd]", pw)
        pg.press("input[name=passwd]", "Enter")
        try:
            pg.wait_for_load_state("domcontentloaded", timeout=30000)
        except Exception:
            pass
        time.sleep(2.5)

        # global configuration
        pg.goto(config_url, timeout=45000, wait_until="domcontentloaded")
        pg.wait_for_selector('input[name="jform[debug]"]', timeout=30000, state='attached')
        before = pg.evaluate(READ_DEBUG_JS)
        n = pg.evaluate(SET_DEBUG_JS, want)
        print(f"   debug radios found: {n}; was {before} → setting {want}")

        # Click the REAL toolbar Save button (it carries the correct save task for
        # this Joomla version); the form serializes our JS-set debug radio. Only
        # `debug` changes — every other field keeps its current value.
        saved_via = None
        for sel in ("#toolbar-apply button", "button.button-apply",
                    "#toolbar-save button", 'joomla-toolbar-button button:has-text("Save")',
                    'button:has-text("Save")'):
            try:
                el = pg.query_selector(sel)
                if el and el.is_visible():
                    el.click()
                    saved_via = sel
                    break
            except Exception:
                pass
        if not saved_via:
            pg.evaluate("() => window.Joomla && Joomla.submitbutton "
                        "&& Joomla.submitbutton('config.save.application.apply')")
            saved_via = "Joomla.submitbutton"
        print(f"   saved via: {saved_via}")
        try:
            pg.wait_for_load_state("domcontentloaded", timeout=30000)
        except Exception:
            pass
        time.sleep(3.0)
        msg = ""
        for sel in (".alert-success", ".alert-message", ".alert-danger", "#system-message-container"):
            el = pg.query_selector(sel)
            if el and (el.inner_text() or "").strip():
                msg = el.inner_text().strip()[:120]
                break
        if msg:
            print(f"   save message: {msg}")

        # verify by re-reading the saved value
        pg.goto(config_url, timeout=45000, wait_until="domcontentloaded")
        pg.wait_for_selector('input[name="jform[debug]"]', timeout=30000, state='attached')
        after = pg.evaluate(READ_DEBUG_JS)
        ok = after == want
        print(f"   {'✅' if ok else '❌'} Debug System now = {after} (wanted {want})")
        ctx.close()
        b.close()
        return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
