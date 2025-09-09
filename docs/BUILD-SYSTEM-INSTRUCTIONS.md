# 📦 JoomlaBoost Plugin Build System - Universal Instructions

## 🎯 **Overview**

Universal build system za kreiranje ZIP instalacijskih paketa za Joomla plugin-e koji rade na bilo kom domenu.

## 🏗️ **Build System Structure**

### **1. Core Build Logic**

```powershell
# Path setup
$baseDir = Split-Path -Parent $PSScriptRoot
$sourceDir = Join-Path $baseDir "plugin"     # Main source folder
$buildDir = Join-Path $baseDir "build"       # Output folder
```

### **2. Version Detection**

```powershell
# Auto-detect version from XML manifest
$xmlPath = Join-Path $sourceDir "joomlaboost.xml"
if ($xmlContent -match '<version>([^<]+)</version>') {
    $version = $matches[1]
} else {
    $version = "0.1.17"  # fallback
}
```

### **3. ZIP Package Creation**

```powershell
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

# Add files with proper folder structure
Get-ChildItem $sourceDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
    $zipEntryPath = "joomlaboost/" + $relativePath.Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $zipEntryPath)
}
$zip.Dispose()
```

## 📋 **Build Script Template**

### **Universal Builder (build_plugin.ps1)**

```powershell
param(
    [string]$PluginName = "joomlaboost",
    [string]$Version = "",
    [string]$SourceDir = "plugin",
    [switch]$Minimal = $false
)

$ErrorActionPreference = "Stop"

Write-Host "📦 Universal Joomla Plugin Builder" -ForegroundColor Green
Write-Host "=================================" -ForegroundColor Green

# Setup paths
$baseDir = Split-Path -Parent $PSScriptRoot
$sourceDir = Join-Path $baseDir $SourceDir
$buildDir = Join-Path $baseDir "build"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"

# Version detection
$xmlPath = Join-Path $sourceDir "$PluginName.xml"
if (Test-Path $xmlPath) {
    $xmlContent = Get-Content $xmlPath -Raw
    if ($xmlContent -match '<version>([^<]+)</version>') {
        $detectedVersion = $matches[1]
        if ([string]::IsNullOrEmpty($Version)) {
            $Version = $detectedVersion
        }
        Write-Host "🔍 Version: $Version" -ForegroundColor Green
    }
}

# Build target determination
$suffix = if ($Minimal) { "-minimal-test" } else { "" }
$zipName = "$PluginName-$Version$suffix.zip"
$zipPath = Join-Path $buildDir $zipName

# Validation
if (-not (Test-Path $sourceDir)) {
    Write-Host "❌ Source directory not found: $sourceDir" -ForegroundColor Red
    exit 1
}

# Required files check
$requiredFiles = @("$PluginName.php", "$PluginName.xml")
foreach ($file in $requiredFiles) {
    if (-not (Test-Path (Join-Path $sourceDir $file))) {
        Write-Host "❌ Required file missing: $file" -ForegroundColor Red
        exit 1
    }
}

# Create build directory
if (-not (Test-Path $buildDir)) {
    New-Item -ItemType Directory -Path $buildDir -Force | Out-Null
}

# File listing
Write-Host "📋 Files to be packaged:" -ForegroundColor Yellow
Get-ChildItem $sourceDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
    Write-Host "   📄 $relativePath" -ForegroundColor Gray
}

# ZIP creation
Write-Host "📦 Creating ZIP archive..." -ForegroundColor Green
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

try {
    Get-ChildItem $sourceDir -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
        $zipEntryPath = "$PluginName/" + $relativePath.Replace('\', '/')

        Write-Host "   ➕ Adding: $zipEntryPath" -ForegroundColor Cyan
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $zipEntryPath) | Out-Null
    }
} finally {
    $zip.Dispose()
}

# Results
$fileSize = [math]::Round((Get-Item $zipPath).Length / 1024, 1)
Write-Host "✅ ZIP archive created successfully!" -ForegroundColor Green
Write-Host "📊 File size: $fileSize KB" -ForegroundColor Cyan
Write-Host "📂 Location: $zipPath" -ForegroundColor Cyan

# Installation instructions
Write-Host ""
Write-Host "🎯 Installation Instructions:" -ForegroundColor Green
Write-Host "1. 📥 Download: $zipName"
Write-Host "2. 🌐 Go to Joomla Administrator"
Write-Host "3. 🔧 Extensions > Manage > Install"
Write-Host "4. 📤 Upload ZIP file"
Write-Host "5. ✅ Enable plugin in System Plugins"

Write-Host ""
Write-Host "✨ Build completed successfully!" -ForegroundColor Green
```

## 🛠️ **Usage Examples**

### **Standard Build**

```powershell
.\tools\build_plugin.ps1
# Creates: joomlaboost-0.1.17.zip
```

### **Custom Plugin**

```powershell
.\tools\build_plugin.ps1 -PluginName "myseoboster" -Version "1.0.0"
# Creates: myseoboster-1.0.0.zip
```

### **Minimal Test Version**

```powershell
.\tools\build_plugin.ps1 -Minimal
# Creates: joomlaboost-0.1.17-minimal-test.zip
```

### **Different Source Directory**

```powershell
.\tools\build_plugin.ps1 -SourceDir "src\plugins\system\joomlaboost"
```

## 📁 **Required Folder Structure**

```
ProjectRoot/
├── plugin/                    # Main source folder
│   ├── joomlaboost.php       # Main plugin file
│   ├── joomlaboost.xml       # Plugin manifest
│   ├── src/                  # PHP classes
│   │   └── Services/
│   ├── language/             # Language files
│   │   └── en-GB/
│   └── media/                # CSS, JS, images
├── build/                    # Output folder (auto-created)
│   ├── joomlaboost-0.1.17.zip
│   └── build-manifest.json
└── tools/                    # Build scripts
    ├── build_plugin.ps1
    ├── build_joomlaboost.ps1
    └── test-after-install.ps1
```

## 🎯 **Key Features**

### **1. Universal Domain Support**

```php
// Plugin automatically detects domain
private function getCurrentDomain(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/';
}

private function isStatingSite(string $domain): bool {
    $stagingKeywords = ['staging', 'stage', 'dev', 'test', 'localhost'];
    foreach ($stagingKeywords as $keyword) {
        if (stripos($domain, $keyword) !== false) {
            return true;
        }
    }
    return false;
}
```

### **2. Proper ZIP Structure**

```
joomlaboost.zip/
└── joomlaboost/              # Plugin folder name
    ├── joomlaboost.php       # Main file
    ├── joomlaboost.xml       # Manifest
    ├── src/                  # Classes
    ├── language/             # Translations
    └── media/                # Assets
```

### **3. Build Validation**

- ✅ XML manifest validation
- ✅ Required file checks
- ✅ PHP syntax validation
- ✅ Folder structure verification
- ✅ File size verification

## 🧪 **Testing Integration**

### **Post-Build Test Script**

```powershell
# test-after-install.ps1
param([string]$Domain = "https://staging.offroadserbia.com")

Write-Host "🧪 Testing JoomlaBoost Installation..." -ForegroundColor Cyan

# Test robots.txt
$robotsResponse = curl -s "$Domain/robots.txt"
if ($robotsResponse -match "JoomlaBoost") {
    Write-Host "✅ robots.txt: WORKING" -ForegroundColor Green
} else {
    Write-Host "❌ robots.txt: FAILED" -ForegroundColor Red
}

# Test sitemap.xml
$sitemapResponse = curl -s "$Domain/sitemap.xml"
if ($sitemapResponse -match "JoomlaBoost") {
    Write-Host "✅ sitemap.xml: WORKING" -ForegroundColor Green
} else {
    Write-Host "❌ sitemap.xml: FAILED" -ForegroundColor Red
}
```

## 📊 **Build Automation**

### **Batch Build Script**

```powershell
# build-all-versions.ps1
param([string]$Version = "0.1.17")

Write-Host "🏭 Building all plugin versions..." -ForegroundColor Cyan

# Standard version
.\tools\build_plugin.ps1 -Version $Version

# Minimal test version
.\tools\build_plugin.ps1 -Version $Version -Minimal

# Debug version (if debug files exist)
if (Test-Path "plugin\joomlaboost-debug.php") {
    .\tools\build_plugin.ps1 -Version "$Version-debug" -SourceDir "plugin"
}

Write-Host "🎉 All builds completed!" -ForegroundColor Green
```

## 🔧 **Advanced Features**

### **1. Automatic Codacy Analysis**

```powershell
# Add to end of build script
Write-Host "🔍 Running code quality analysis..." -ForegroundColor Yellow
$codacyResult = .\tools\run-codacy-analysis.ps1 -File $zipPath
if ($codacyResult -eq "PASSED") {
    Write-Host "✅ Code quality: PASSED" -ForegroundColor Green
} else {
    Write-Host "⚠️  Code quality: Issues found" -ForegroundColor Yellow
}
```

### **2. Deployment Integration**

```powershell
# Auto-deploy to staging after successful build
if ($AutoDeploy) {
    Write-Host "🚀 Deploying to staging..." -ForegroundColor Yellow
    .\tools\deploy-to-staging.ps1 -ZipPath $zipPath
}
```

### **3. Version Tagging**

```powershell
# Auto-create git tag after successful build
if ($CreateTag) {
    git tag -a "v$Version" -m "Release v$Version"
    git push origin "v$Version"
    Write-Host "🏷️ Created git tag: v$Version" -ForegroundColor Green
}
```

## 📋 **Quality Checklist**

### **Pre-Build**

- [ ] XML manifest syntax valid
- [ ] All required files present
- [ ] PHP syntax check passed
- [ ] Class naming convention followed
- [ ] Version number updated

### **Post-Build**

- [ ] ZIP file size reasonable (5-50 KB)
- [ ] ZIP structure correct
- [ ] Installation on test site successful
- [ ] Plugin enables without errors
- [ ] Core functionality works
- [ ] Domain detection accurate

### **Testing**

- [ ] robots.txt endpoint returns proper content
- [ ] sitemap.xml endpoint returns valid XML
- [ ] Environment detection (staging vs production)
- [ ] SEO meta tags injection works
- [ ] Error handling graceful

## 🎯 **Best Practices**

1. **Always test on clean Joomla installation first**
2. **Use semantic versioning (MAJOR.MINOR.PATCH)**
3. **Keep build logs for troubleshooting**
4. **Validate ZIP contents before distribution**
5. **Test on multiple Joomla versions if possible**
6. **Include rollback procedure in deployment**
7. **Document all configuration options**
8. **Maintain changelog for version tracking**

---

_This universal build system ensures consistent, reliable plugin packaging for any Joomla environment._
