#!/usr/bin/env python3
"""Quick login + GET/POST against staging admin endpoints. Debug only."""
import os, sys, re, requests

ADMIN = "https://staging.offroadserbia.com/administrator/index.php"
USER  = os.environ.get("STAGING_ADMIN_USER", "aiadmin")
PASS  = os.environ.get("STAGING_ADMIN_PASS", "")

def extract_csrf(html):
    m = re.search(r'name="([0-9a-f]{32})"\s+value="1"', html)
    return m.group(1) if m else None

def login():
    s = requests.Session()
    s.headers["User-Agent"] = "Mozilla/5.0"
    r = s.get(ADMIN, timeout=20)
    tok = extract_csrf(r.text)
    if not tok:
        sys.exit(f"no token, html starts: {r.text[:200]}")
    r = s.post(ADMIN, data={
        "username": USER, "passwd": PASS, "option": "com_login",
        "task": "login", "return": "aW5kZXgucGhw", tok: 1,
    }, allow_redirects=True, timeout=20)
    if "logout" not in r.text.lower():
        sys.exit(f"login fail, status={r.status_code}")
    return s, tok

def main():
    s, tok = login()
    print(f"login OK, token={tok[:8]}…")
    for task in ["redirects.listJson", "urlchecker.getSitemapUrls"]:
        url = f"{ADMIN}?option=com_aiboost&task={task}&format=json"
        r = s.post(url, data={tok: 1}, timeout=30)
        body = r.text[:600].replace("\n", " ")
        print(f"\n=== {task} (HTTP {r.status_code}, ct={r.headers.get('content-type','?')}) ===")
        print(body)

if __name__ == "__main__":
    main()
