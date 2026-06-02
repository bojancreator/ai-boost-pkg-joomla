#!/usr/bin/env python3
"""One-off admin walk for the 3-state visual audit (Free state).

Logs into a Joomla admin, walks every AI Boost SPA route + Settings sub-tab,
and saves a screenshot per surface. Credentials come from env only and are
never printed. Target is selected by --target {free,pro}; defaults to free.
"""
import os
import sys
import json
import time
import argparse
import urllib.parse
from playwright.sync_api import sync_playwright

CHROME = "/nix/store/qa9cnw4v5xkxyip6mb9kxqfq1z4x2dx1-chromium-138.0.7204.100/bin/chromium"

TOP_ROUTES = [
    ("dashboard", "#/dashboard"),
    ("health", "#/health"),
    ("integrations", "#/integrations"),
    ("licenses", "#/licenses"),
    ("analyzers", "#/analyzers"),
    ("redirects", "#/redirects"),
    ("urlchecker", "#/urlchecker"),
    ("import", "#/import"),
    ("help", "#/help"),
]
SETTINGS_TABS = ["general", "org", "schema", "sitemap", "social",
                 "analytics", "aeo", "code", "debug"]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", default="free", choices=["free", "pro"])
    ap.add_argument("--outdir", required=True)
    ap.add_argument("--only", default="",
                    help="comma-separated screenshot names to capture (default all)")
    args = ap.parse_args()
    only = {s.strip() for s in args.only.split(",") if s.strip()}

    if args.target == "free":
        secret_url = os.environ["FREE_URL"]
        user = os.environ["FREE_ADMIN_USER"]
        pw = os.environ["FREE_ADMIN_PASS"]
    else:
        secret_url = os.environ["STAGING_URL"]
        user = os.environ["STAGING_ADMIN_USER"]
        pw = os.environ["STAGING_ADMIN_PASS"]

    parsed = urllib.parse.urlparse(secret_url)
    base = f"{parsed.scheme}://{parsed.netloc}"
    os.makedirs(args.outdir, exist_ok=True)
    admin = base + "/administrator/index.php"
    results = {}

    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=CHROME, headless=True,
                              args=["--no-sandbox", "--disable-dev-shm-usage"])
        ctx = b.new_context(viewport={"width": 1440, "height": 900})
        pg = ctx.new_page()

        # ---- login (enter via Admin Tools secret URL; JS redirect → login form) ----
        pg.goto(secret_url, timeout=45000, wait_until="domcontentloaded")
        pg.wait_for_selector("input[name=username]", timeout=30000)
        pg.fill("input[name=username]", user)
        pg.fill("input[name=passwd]", pw)
        pg.press("input[name=passwd]", "Enter")
        try:
            pg.wait_for_load_state("domcontentloaded", timeout=30000)
        except Exception:
            pass
        time.sleep(3.0)
        title = pg.title()
        results["_login_title"] = title
        print("login -> title:", title)

        # Dismiss the Joomla 6.1 guided-tour / "What's New" modal. While it is
        # active the tour system hard-redirects com_aiboost back to the control
        # panel after ~2s, killing the SPA. "Hide Forever" disables it for good.
        time.sleep(2.0)
        for sel in ["button:has-text('Hide Forever')", "text=Hide Forever",
                    "button.tour-button-cancel",
                    ".joomla-dialog-container button[aria-label*=close i]"]:
            try:
                el = pg.query_selector(sel)
                if el and el.is_visible():
                    el.click()
                    print("dismissed tour modal via", sel)
                    time.sleep(1.0)
                    break
            except Exception:
                pass

        # Load the com_aiboost SPA ONCE. Appending the hash to the goto URL makes
        # Chromium treat it as a same-document hash change on the control panel and
        # never loads the component, so we drive the hash router via JS instead.
        pg.goto(admin + "?option=com_aiboost", timeout=45000,
                wait_until="domcontentloaded")
        try:
            pg.wait_for_selector("#ab-app", timeout=30000)
        except Exception:
            pass
        time.sleep(2.0)
        results["_spa_url"] = pg.url

        def snap(name, frag):
            try:
                pg.evaluate("(h) => { window.location.hash = h; }", frag)
                # networkidle never settles on some staging tabs (polling/widgets);
                # wait on DOM settle + a fixed render delay instead of hanging.
                try:
                    pg.wait_for_load_state("domcontentloaded", timeout=15000)
                except Exception:
                    pass
                time.sleep(3.0)
                path = os.path.join(args.outdir, name + ".png")
                pg.screenshot(path=path, full_page=True)
                txt = (pg.inner_text("body") or "")
                low = txt.lower()
                info = {
                    "file": os.path.basename(path),
                    "bytes": os.path.getsize(path),
                    "has_unlock": ("unlock pro" in low or "unlock the pro" in low
                                   or "unlock" in low),
                    "has_locked": ("locked" in low),
                    "has_upgrade": ("upgrade" in low or "go pro" in low
                                    or "pro version" in low),
                }
                results[name] = info
                print(f"  {name}: {info['bytes']}B unlock={info['has_unlock']} "
                      f"locked={info['has_locked']} upgrade={info['has_upgrade']}")
            except Exception as e:
                results[name] = {"error": str(e)[:200]}
                print(f"  {name}: ERROR {str(e)[:120]}")

        # top-level routes (drive the hash router via JS)
        for name, frag in TOP_ROUTES:
            if only and name not in only:
                continue
            snap(name, frag)

        # settings sub-tabs
        for tab in SETTINGS_TABS:
            name = "settings-" + tab
            if only and name not in only:
                continue
            snap(name, "#/settings?tab=" + tab)

        ctx.close()
        b.close()

    results_path = os.path.join(args.outdir, "_walk-results.json")
    if only and os.path.exists(results_path):
        try:
            with open(results_path) as f:
                prev = json.load(f)
            prev.update(results)
            results = prev
        except Exception:
            pass
    with open(results_path, "w") as f:
        json.dump(results, f, indent=2)
    print("\nDONE. results ->", os.path.join(args.outdir, "_walk-results.json"))


if __name__ == "__main__":
    main()
