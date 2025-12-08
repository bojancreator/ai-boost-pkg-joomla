# CI Workflow Quick Reference

**One-page guide for fixing CI failures**

---

## 🚀 Quick Start

```bash
# 1. Check CI status
gh run list --limit 3

# 2. If failed, view errors
gh run view --log-failed

# 3. Run checks locally
composer lint && composer stan

# 4. Fix and push
git add -A
git commit -m "fix: CI errors"
git push origin main

# 5. Watch result
gh run watch
```

---

## 📊 Error Types

### PHPCS (Code Style)
```bash
composer lint        # Check
composer lint-fix    # Auto-fix (if available)
vendor/bin/phpcbf --standard=config/phpcs.xml plugin/
```

### PHPStan (Static Analysis)
```bash
composer stan        # Check

# Common fixes in config/phpstan.neon:
# - Lower level (6 → 4)
# - Change paths (plugin → src)
# - Add ignoreErrors for stubs
```

---

## ✅ Pre-Push Checklist

- [ ] `composer validate --no-check-publish --strict`
- [ ] `composer lint`
- [ ] `composer stan`
- [ ] `php -l <changed-file>.php`
- [ ] All pass ✅ → Safe to push

---

## 🔗 Links

- **CI Runs:** https://github.com/bojancreator/JoomlaBoost/actions
- **Full Guide:** [docs/CI-DEBUGGING-GUIDE.md](../docs/CI-DEBUGGING-GUIDE.md)
- **GitHub CLI:** https://cli.github.com/

---

**Last CI Status:** ✅ PASSING (2025-12-08)
