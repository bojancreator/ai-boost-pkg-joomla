#!/usr/bin/env python3
"""
AI Boost for Joomla — Free site installer (offroadbalkans.com).

Thin wrapper that re-maps FREE_* secrets to STAGING_* env vars expected
by install-to-staging.py, then delegates to it.

Required secrets:
  FREE_URL          — admin URL (e.g. https://offroadbalkans.com/administrator/)
  FREE_ADMIN_USER   — admin username
  FREE_ADMIN_PASS   — admin password

Usage (identical flags to install-to-staging.py):
  python3 scripts/install-to-free.py                     # latest pkg_aiboost-*.zip
  python3 scripts/install-to-free.py --zip path/to.zip
  python3 scripts/install-to-free.py --all-plugins
"""

import os
import sys
import runpy

required = ("FREE_URL", "FREE_ADMIN_USER", "FREE_ADMIN_PASS")
missing = [k for k in required if not os.environ.get(k)]
if missing:
    print(f"❌ Missing secrets: {', '.join(missing)}")
    sys.exit(1)

os.environ["STAGING_URL"]        = os.environ["FREE_URL"]
os.environ["STAGING_ADMIN_USER"] = os.environ["FREE_ADMIN_USER"]
os.environ["STAGING_ADMIN_PASS"] = os.environ["FREE_ADMIN_PASS"]

print("🟢 Target: Free site (offroadbalkans.com)")
script = os.path.join(os.path.dirname(__file__), "install-to-staging.py")
runpy.run_path(script, run_name="__main__")
