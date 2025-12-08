# GitHub CI Debugging Guide

**Quick Reference:** How to diagnose and fix GitHub Actions CI failures

---

## 🚨 When CI Fails

### Step 1: Check GitHub Actions

```bash
# View recent runs
gh run list --limit 5

# View specific run details
gh run view <RUN_ID>

# View failed logs
gh run view <RUN_ID> --log-failed
```

**GitHub Web UI:** https://github.com/bojancreator/JoomlaBoost/actions

---

## 🔍 Common Failure Types

### 1. **PHPCS Failures** (Code Style)

**Symptoms:**
```
PHPCS: Line indented incorrectly
PHPCS: Class name not in PascalCase
```

**Quick Fix:**
```bash
# Run locally first
composer lint

# Auto-fix most issues
composer lint-fix  # (if available)
# OR manually
vendor/bin/phpcbf --standard=config/phpcs.xml plugin/

# Verify fix
composer lint
```

**Common Issues:**
- ✅ **Indentation errors** - Run PHPCBF auto-fix
- ✅ **Missing newline at EOF** - Add blank line at end
- ✅ **Class naming** - Check if it's Joomla convention (add exclusion)

**Config:** `config/phpcs.xml`

---

### 2. **PHPStan Failures** (Static Analysis)

**Symptoms:**
```
PHPStan: Class X not found
PHPStan: Call to undefined method
PHPStan: Invalid return type
```

**Quick Fix:**
```bash
# Run locally first
composer stan

# Check which files are analyzed
cat config/phpstan.neon | grep "paths:"

# Check ignore rules
cat config/phpstan.neon | grep -A 10 "ignoreErrors:"
```

**Common Issues:**

#### Issue: "Unknown class Joomla\..."
**Cause:** Joomla stubs incomplete  
**Fix:** Add to `ignoreErrors` in `config/phpstan.neon`:
```yaml
ignoreErrors:
  - '#unknown class Joomla\\#'
  - '#Class Joomla\\Database\\DatabaseInterface not found#'
```

#### Issue: "Analyzing wrong folder"
**Cause:** `paths` pointing to legacy code  
**Fix:** Update `config/phpstan.neon`:
```yaml
parameters:
  paths:
    - ../src/plugins/system/joomlaboost/src  # ✅ Correct
    # NOT:
    # - ../plugin  # ❌ Legacy folder
```

#### Issue: "Too many errors from stubs"
**Cause:** PHPStan level too high  
**Fix:** Lower level temporarily:
```yaml
parameters:
  level: 4  # Start here, increase gradually
```

---

### 3. **composer.json Validation**

**Symptoms:**
```
composer.json is invalid
```

**Quick Fix:**
```bash
composer validate --no-check-publish --strict
```

---

## 📋 Standard Debugging Checklist

When CI fails, follow this order:

### ✅ **Checklist:**

1. **Pull latest changes**
   ```bash
   git pull origin main
   ```

2. **Run all checks locally**
   ```bash
   composer validate --no-check-publish --strict
   composer lint
   composer stan
   ```

3. **Fix issues one by one**
   - Start with PHPCS (easiest)
   - Then PHPStan
   - Finally composer issues

4. **Verify fixes locally**
   ```bash
   # All should pass
   composer lint && composer stan
   ```

5. **Commit and push**
   ```bash
   git add -A
   git commit -m "fix: resolve CI failures"
   git push origin main
   ```

6. **Monitor CI**
   ```bash
   gh run watch
   # OR check web: https://github.com/bojancreator/JoomlaBoost/actions
   ```

---

## 🛠️ Quick Fixes Reference

### PHPCS: Indentation Errors

**Problem:** 260+ indentation errors  
**Cause:** Editing files outside of build process  
**Solution:**
```bash
# Copy clean version
cp src/plugins/system/joomlaboost/joomlaboost.php plugin/joomlaboost.php

# OR run auto-fix
vendor/bin/phpcbf --standard=config/phpcs.xml plugin/joomlaboost.php
```

---

### PHPCS: Class Naming Convention

**Problem:** `plgSystemJoomlaboostInstallerScript` not PascalCase  
**Cause:** Joomla naming convention (valid)  
**Solution:** Exclude in `config/phpcs.xml`:
```xml
<rule ref="Squiz.Classes.ValidClassName">
    <exclude-pattern>*/script.php</exclude-pattern>
</rule>
```

---

### PHPStan: Joomla Stubs Missing

**Problem:** 50+ "unknown class Joomla\..." errors  
**Cause:** Incomplete Joomla stubs  
**Solution:** Add broad ignore pattern in `config/phpstan.neon`:
```yaml
parameters:
  ignoreErrors:
    - '#unknown class Joomla\\#'
    - '#Class Joomla\\Database\\DatabaseInterface not found#'
    - '#Constant JPATH_#'
    - '#Call to an undefined (static )?method Joomla\\#'
```

---

### PHPStan: EnvironmentType Not Found

**Problem:** Custom enum not recognized  
**Cause:** PHP 8.1 enums not in stubs  
**Solution:** Add to ignoreErrors:
```yaml
ignoreErrors:
  - '#unknown class .*EnvironmentType#'
  - '#invalid return type .*EnvironmentType#'
```

---

### PHPStan: Too Many Errors

**Problem:** 95+ errors on first run  
**Cause:** Analyzing wrong folders or level too high  
**Solutions:**

1. **Narrow scope:**
   ```yaml
   parameters:
     paths:
       - ../src/plugins/system/joomlaboost/src  # Only services
   ```

2. **Lower level:**
   ```yaml
   parameters:
     level: 4  # Down from 6
   ```

3. **Exclude legacy:**
   ```yaml
   excludePaths:
     - ../plugin/  # Legacy folder
   ```

---

## 🎯 Current Working Configuration

### `config/phpcs.xml`
```xml
<ruleset name="JoomlaBoost QA">
  <rule ref="PSR12">
    <exclude name="PSR1.Classes.ClassDeclaration.MissingNamespace"/>
    <exclude name="PSR1.Files.SideEffects"/>
    <exclude name="Generic.Files.LineLength"/>
  </rule>
  
  <!-- Joomla installer script naming convention -->
  <rule ref="Squiz.Classes.ValidClassName">
    <exclude-pattern>*/script.php</exclude-pattern>
  </rule>
  
  <exclude-pattern>vendor/*</exclude-pattern>
  <exclude-pattern>plugin/joomlaboost-*.php</exclude-pattern>
</ruleset>
```

### `config/phpstan.neon`
```yaml
parameters:
  paths:
    - ../src/plugins/system/joomlaboost/src
  level: 4
  ignoreErrors:
    - '#unknown class Joomla\\#'
    - '#unknown class .*EnvironmentType#'
    - '#Class Joomla\\Database\\DatabaseInterface not found#'
    - '#invalid return type .*EnvironmentType#'
    - '#Constant JPATH_#'
    - '#Call to an undefined (static )?method Joomla\\#'
    - '#Static method Joomla\\CMS\\Factory::getUser\(\) invoked with 0 parameters#'
    - '#Access to an undefined property object#'
    - '#Template type T.*is not referenced in a parameter#'
  excludePaths:
    - ../plugin/joomlaboost-*.php
  bootstrapFiles:
    - ../stubs/autoload.php
    - ../stubs/bootstrap.php
```

---

## 🔄 Workflow: From Failure to Success

```
1. CI Fails (GitHub email/notification)
   ↓
2. gh run view --log-failed
   ↓
3. Identify error type (PHPCS/PHPStan)
   ↓
4. Run locally: composer lint / composer stan
   ↓
5. Fix issues (see Quick Fixes above)
   ↓
6. Verify locally: all checks pass
   ↓
7. git commit -m "fix: resolve CI"
   ↓
8. git push origin main
   ↓
9. gh run watch (monitor)
   ↓
10. ✅ CI PASSES!
```

---

## 💡 Pro Tips

### 1. **Always Test Locally First**
```bash
# Run before every push
composer lint && composer stan
```

### 2. **Use Amend for CI Fixes**
```bash
# Multiple attempts on same commit
git add config/phpstan.neon
git commit --amend --no-edit
git push origin main --force
```

### 3. **Monitor CI in Real-Time**
```bash
gh run watch  # Auto-selects latest run
```

### 4. **Keep Ignore Rules Minimal**
Only ignore what's truly unavoidable (stub issues). Don't ignore real code problems!

### 5. **Document Changes**
When adding ignore rules, add comment:
```yaml
ignoreErrors:
  # Joomla stubs incomplete - will fix in future
  - '#unknown class Joomla\\#'
```

---

## 📚 Resources

- **PHPCS Docs:** https://github.com/squizlabs/PHP_CodeSniffer/wiki
- **PHPStan Docs:** https://phpstan.org/user-guide/getting-started
- **GitHub CLI:** https://cli.github.com/manual/
- **Joomla Coding Standards:** https://developer.joomla.org/coding-standards/

---

## ⚠️ Common Mistakes to Avoid

| Mistake | Why Bad | Correct Approach |
|---------|---------|------------------|
| Analyzing `plugin/` folder | Legacy code, stub issues | Analyze only `src/` |
| PHPStan level too high | 95+ false positives | Start at level 4 |
| Ignoring real errors | Hides bugs | Only ignore stub issues |
| Not testing locally | Wastes CI time | Always run locally first |
| Too broad ignore patterns | Misses real issues | Be specific |

---

**Last Updated:** 2025-12-08  
**CI Status:** ✅ PASSING  
**Current Config:** PHPStan Level 4, focused on `/src` services only
