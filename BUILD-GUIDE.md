# JoomlaBoost Build Guide

**Last Updated:** December 4, 2025  
**Current Version:** 0.2.14

---

## 🎯 Quick Reference

### ✅ Correct Build Location
```
/tools/__build/joomlaboost-{version}.zip
```
**Example:** `/tools/__build/joomlaboost-0.2.14.zip`

### 🔧 Build Script
```powershell
cd c:\POSLOVI\__JoomlaBoost\tools
.\build_joomlaboost_smart.ps1
```

### 📦 Build Characteristics
- **Size:** ~54-55 KB
- **Files:** 26 files
- **No backup files** (*.backup, *_OLD.php, *.v0.*)
- **Source:** `/src/plugins/system/joomlaboost/`

---

## 📋 Build Procedure

### Step 1: Verify Current Version
```powershell
# Check current version in XML
Select-String -Path "src\plugins\system\joomlaboost\joomlaboost.xml" -Pattern "<version>"
```

### Step 2: Update Version (Manual)
**IMPORTANT:** Version increments by **0.0.1** (patch only) until instructed otherwise.

Edit `src/plugins/system/joomlaboost/joomlaboost.xml`:
```xml
<version>0.2.14</version>  <!-- Change to 0.2.15 for next build -->
```

### Step 3: Run Build Script
```powershell
cd tools
.\build_joomlaboost_smart.ps1
```

### Step 4: Verify Build
```powershell
# Check file size (should be ~54KB)
Get-Item tools\__build\joomlaboost-*.zip | Select Name, Length

# Extract and verify file count (should be 26)
Expand-Archive -Path "tools\__build\joomlaboost-0.2.15.zip" -Dest "temp_verify"
(Get-ChildItem temp_verify\joomlaboost -Recurse -File).Count

# Check for backup files (should return nothing)
Get-ChildItem temp_verify\joomlaboost -Recurse | Where {$_.Name -like "*.backup"}

# Cleanup
Remove-Item temp_verify -Recurse -Force
```

### Step 5: Copy to Main Build Folder (Optional)
```powershell
Copy-Item tools\__build\joomlaboost-0.2.15.zip build\
```

### Step 6: Update CHANGELOG
Edit `CHANGELOG.md` and add entry:
```markdown
## [0.2.15] - 2025-12-XX

### Added
- Feature description

### Changed
- Change description

### Fixed
- Fix description
```

### Step 7: Commit Changes
```powershell
git add src/plugins/system/joomlaboost/joomlaboost.xml CHANGELOG.md
git commit -m "chore: bump version to 0.2.15"
```

---

## ⚠️ Common Issues

### ❌ Wrong Build Location
**Problem:** ZIP appears in `/build/` instead of `/tools/__build/`  
**Cause:** Using wrong build script (`build_joomlaboost.ps1`)  
**Solution:** Always use `build_joomlaboost_smart.ps1`

### ❌ Large File Size (76-80KB)
**Problem:** Build is larger than expected  
**Cause:** Backup files included  
**Solution:** Build script now excludes them automatically (as of v0.2.14)

### ❌ Installation Fails
**Problem:** Joomla rejects ZIP package  
**Cause:** Missing files referenced in XML manifest  
**Solution:** Verify all `<filename>` entries in XML exist in source

---

## 📂 Directory Structure

```
JoomlaBoost/
├── src/plugins/system/joomlaboost/   ← SOURCE (build from here)
│   ├── joomlaboost.xml                ← Main manifest (edit version here)
│   ├── joomlaboost.php
│   ├── script.php
│   └── src/Services/                  ← All services
│
├── tools/
│   ├── build_joomlaboost_smart.ps1   ← ✅ CORRECT build script
│   └── __build/                       ← ✅ BUILD OUTPUT (correct location)
│       └── joomlaboost-0.2.14.zip     ← Production-ready ZIP
│
├── build/                             ← Reference/archive only
│   ├── joomlaboost-0.2.14.zip         ← Copy of latest (optional)
│   └── old/                           ← Archive of previous versions
│
└── plugin/                            ← ❌ OLD/TEST folder (do NOT build from here)
```

---

## 🔢 Versioning Rules

### Patch Version (0.0.X)
**When:** Every build until instructed otherwise  
**Increment:** +0.0.1  
**Example:** 0.2.14 → 0.2.15 → 0.2.16

### Minor Version (0.X.0)
**When:** Only on explicit instruction  
**Example:** 0.2.99 → 0.3.0

### Major Version (X.0.0)
**When:** Only on explicit instruction  
**Example:** 0.9.99 → 1.0.0

**DEFAULT BEHAVIOR:** Always increment patch version (0.0.1) unless told otherwise.

---

## ✅ Pre-Release Checklist

- [ ] Version updated in `src/plugins/system/joomlaboost/joomlaboost.xml`
- [ ] Build script executed successfully
- [ ] ZIP file is ~54KB (not 76-80KB)
- [ ] File count is 26 (not 31)
- [ ] No backup files in ZIP
- [ ] CHANGELOG.md updated
- [ ] Changes committed to git

---

## 🚀 Quick Build Command

```powershell
# One-liner for experienced users
cd c:\POSLOVI\__JoomlaBoost\tools; .\build_joomlaboost_smart.ps1
```

**Output Location:** `c:\POSLOVI\__JoomlaBoost\tools\__build\joomlaboost-{version}.zip`

---

## 📝 Notes

- Build script automatically excludes backup files since v0.2.14
- Creation date in XML is auto-updated during build
- Source is always `/src/plugins/system/joomlaboost/`
- Never use `/plugin/` folder for builds (old test folder)
