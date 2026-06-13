#!/usr/bin/env python3
"""
AI Boost ops — ensure every AI Boost plugin is ENABLED in Joomla on a target.

Standalone system plugins install DISABLED by default, so the integration
bridges (aiboost_int_falang / aiboost_int_yootheme) and even some core plugins
may be off after a package install — which silently stops their front-end
output (e.g. Multilang hreflang). This batch-enables them via the native
com_plugins toolbar (select-all → Enable).

Usage (creds via wrapper; testmyweb also needs TESTMYWEB_NO_SSL_VERIFY=1):
  python _creds_run.py scripts/enable-aiboost-plugins.py --target j6pro
"""
from __future__ import annotations

import argparse
import importlib.util
import os
import sys
import time
import urllib.parse

from playwright.sync_api import sync_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location("_qa_common", os.path.join(HERE, "_qa_common.py"))
qa = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(qa)

# Read each AI Boost row's element + enabled state from the plugins grid.
STATE_JS = """() => {
    const out = [];
    document.querySelectorAll('table tbody tr').forEach(tr => {
        const txt = tr.innerText || '';
        if (!/ai\\s*boost/i.test(txt)) return;
        // status button: aria-label / title says Enabled/Disabled, or icon class
        const btn = tr.querySelector('button, a.tbody-icon, .tbody-icon');
        const lbl = (btn && (btn.getAttribute('aria-label') || btn.getAttribute('title') || '')) || '';
        const ic = tr.querySelector('.icon-publish, .icon-unpublish, span.icon-publish, span.icon-unpublish');
        const cls = ic ? ic.className : '';
        const enabled = /enabled/i.test(lbl) || /icon-publish(\\s|$)/.test(cls);
        const name = (tr.querySelector('th a, td a') || {}).innerText || txt.split('\\n')[0];
        out.push({ name: (name||'').trim().slice(0,60), label: lbl, enabled });
    });
    return out;
}"""


def main() -> int:
    qa.setup_console_utf8()
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", required=True, choices=list(qa.TARGETS))
    args = ap.parse_args()
    admin_url, user, pw = qa.env(args.target)
    verify = qa.ssl_verify_for(args.target)
    base = qa.base_url(args.target if False else admin_url)
    search = urllib.parse.quote("AI Boost")
    list_url = f"{base}/administrator/index.php?option=com_plugins&view=plugins&filter[search]={search}&list[limit]=100"
    print(f"🔌 {args.target}: enabling AI Boost plugins ({base})")

    with sync_playwright() as p:
        b = p.chromium.launch(headless=True, args=["--no-sandbox", "--disable-dev-shm-usage"])
        ctx = b.new_context(viewport={"width": 1366, "height": 900}, ignore_https_errors=not verify)
        pg = ctx.new_page()
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

        pg.goto(list_url, timeout=45000, wait_until="domcontentloaded")
        time.sleep(2.0)
        before = pg.evaluate(STATE_JS)
        print(f"   found {len(before)} AI Boost plugin rows:")
        for r in before:
            print(f"     {'✅' if r['enabled'] else '⛔'} {r['name']}  ({r['label']})")

        # Check the real row checkboxes (cid[]) for every AI Boost row, then Enable.
        nchecked = pg.evaluate("""() => {
            let n = 0;
            document.querySelectorAll('table tbody tr').forEach(tr => {
                const txt = (tr.innerText || '');
                if (!/ai\\s*boost|aiboost/i.test(txt)) return;
                const cb = tr.querySelector('input[type=checkbox][name="cid[]"], input[type=checkbox][name^="cid"]');
                if (cb) { cb.checked = true; cb.dispatchEvent(new Event('change', {bubbles:true})); n++; }
            });
            return n;
        }""")
        print(f"   checked {nchecked} row checkboxes")
        time.sleep(0.5)
        clicked = None
        for sel in ("#toolbar-publish button", "button.button-publish",
                    'joomla-toolbar-button button:has-text("Enable")', 'button:has-text("Enable")'):
            try:
                el = pg.query_selector(sel)
                if el and el.is_visible():
                    el.click(); clicked = sel; break
            except Exception:
                pass
        if not clicked:
            pg.evaluate("() => window.Joomla && Joomla.submitbutton && Joomla.submitbutton('plugins.publish')")
            clicked = "Joomla.submitbutton(plugins.publish)"
        print(f"   enabled via: {clicked}")
        try:
            pg.wait_for_load_state("domcontentloaded", timeout=30000)
        except Exception:
            pass
        time.sleep(2.5)

        pg.goto(list_url, timeout=45000, wait_until="domcontentloaded")
        time.sleep(2.0)
        after = pg.evaluate(STATE_JS)
        en = sum(1 for r in after if r["enabled"])
        print(f"   after: {en}/{len(after)} enabled")
        for r in after:
            if not r["enabled"]:
                print(f"     ⛔ still disabled: {r['name']} ({r['label']})")
        ctx.close(); b.close()
        return 0


if __name__ == "__main__":
    sys.exit(main())
