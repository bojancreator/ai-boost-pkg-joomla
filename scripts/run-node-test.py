#!/usr/bin/env python3
"""Helper: run a Node.js test script with the same env vars that _creds_run.py injects.
Usage (from repo root via _creds_run.py):
  python _creds_run.py scripts/run-node-test.py scripts/test-all-settings.js --target staging
"""
import os
import subprocess
import sys

script = sys.argv[1] if len(sys.argv) > 1 else None
if not script:
    print("Usage: run-node-test.py <script.js> [args...]")
    sys.exit(1)

# Locate node in PATH or pnpm shim
cmd = ["node", script] + sys.argv[2:]
result = subprocess.run(cmd, env=os.environ)
sys.exit(result.returncode)
