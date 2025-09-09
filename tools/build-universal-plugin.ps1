# Universal Joomla Plugin Builder
# Build any Joomla plugin with proper ZIP structure

param(
    [string]$PluginName = "joomlaboost",
    [string]$Version = "",
    [string]$SourceDir = "plugin",
    [switch]$Minimal = $false,
    [switch]$Debug = $false,
    [switch]$AutoTest = $false
)

$ErrorActionPreference = "Stop"

# Colors for output
$Green = "Green"
$Yellow = "Yellow" 
$Red = "Red"
$Cyan = "Cyan"
$White = "White"

Write-Host "📦 Universal Joomla Plugin Builder v2.0" -ForegroundColor $Green
Write-Host "=======================================" -ForegroundColor $Yellow

# Setup paths
$baseDir = Split-Path -Parent $PSScriptRoot
$sourceDir = Join-Path $baseDir $SourceDir
$buildDir = Join-Path $baseDir "build"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"

Write-Host "📁 Source directory: $sourceDir" -ForegroundColor $Cyan
Write-Host "🕒 Timestamp: $timestamp" -ForegroundColor $Cyan

# Version detection from XML
$xmlPath = Join-Path $sourceDir "$PluginName.xml"
if (Test-Path $xmlPath) {
    $xmlContent = Get-Content $xmlPath -Raw
    if ($xmlContent -match '<version>([^<]+)</version>') {
        $detectedVersion = $matches[1]
        if ([string]::IsNullOrEmpty($Version)) {
            $Version = $detectedVersion
        }
        Write-Host "🔍 Version detected from XML: $detectedVersion" -ForegroundColor $Green
        if ($Version -ne $detectedVersion) {
            Write-Host "⚠️  Using parameter version: $Version" -ForegroundColor $Yellow
        }
    } else {
        if ([string]::IsNullOrEmpty($Version)) {
            $Version = "1.0.0"
        }
        Write-Host "⚠️  No version in XML, using: $Version" -ForegroundColor $Yellow
    }
} else {
    Write-Host "❌ XML manifest not found: $xmlPath" -ForegroundColor $Red
    exit 1
}

# Build target determination
$suffix = ""
if ($Minimal) { $suffix += "-minimal" }
if ($Debug) { $suffix += "-debug" }
$zipName = "$PluginName-$Version$suffix.zip"

Write-Host "📦 Building: $zipName" -ForegroundColor $Green
Write-Host ""

# Validation phase
Write-Host "🔍 VALIDATION PHASE" -ForegroundColor $Yellow
Write-Host "===================" -ForegroundColor $Yellow

# Check source directory
if (-not (Test-Path $sourceDir)) {
    Write-Host "❌ Source directory not found: $sourceDir" -ForegroundColor $Red
    exit 1
}
Write-Host "✅ Source directory exists" -ForegroundColor $Green

# Check required files
$requiredFiles = @("$PluginName.php", "$PluginName.xml")
foreach ($file in $requiredFiles) {
    $filePath = Join-Path $sourceDir $file
    if (Test-Path $filePath) {
        Write-Host "✅ Required file found: $file" -ForegroundColor $Green
    } else {
        Write-Host "❌ Required file missing: $file" -ForegroundColor $Red
        exit 1
    }
}

# PHP syntax check (optional)
try {
    $phpFiles = Get-ChildItem -Path $sourceDir -Filter "*.php" -Recurse
    Write-Host "🔍 Checking PHP syntax for $($phpFiles.Count) files..." -ForegroundColor $Cyan
    
    foreach ($phpFile in $phpFiles) {
        $relativePath = $phpFile.FullName.Substring($sourceDir.Length + 1)
        try {
            $result = php -l $phpFile.FullName 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Host "   ✅ $relativePath" -ForegroundColor $Green
            } else {
                Write-Host "   ❌ $relativePath - $result" -ForegroundColor $Red
                exit 1
            }
        } catch {
            Write-Host "   ⚠️  $relativePath (php command not available)" -ForegroundColor $Yellow
        }
    }
} catch {
    Write-Host "⚠️  PHP syntax check skipped (php not available)" -ForegroundColor $Yellow
}

Write-Host ""
Write-Host "🔨 BUILD PHASE" -ForegroundColor $Yellow
Write-Host "===============" -ForegroundColor $Yellow

# Create build directory
if (-not (Test-Path $buildDir)) {
    New-Item -ItemType Directory -Path $buildDir -Force | Out-Null
    Write-Host "📁 Created build directory" -ForegroundColor $Green
}

# Remove existing build
$zipPath = Join-Path $buildDir $zipName
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
    Write-Host "🗑️ Removed existing build" -ForegroundColor $Yellow
}

# List files to be packaged
Write-Host "📋 Files to be packaged:" -ForegroundColor $Cyan
$allFiles = Get-ChildItem -Path $sourceDir -Recurse -File
foreach ($file in $allFiles) {
    $relativePath = $file.FullName.Substring($sourceDir.Length + 1)
    $fileSize = [math]::Round($file.Length / 1KB, 1)
    Write-Host "   📄 $relativePath ($fileSize KB)" -ForegroundColor $White
}
Write-Host ""

# Create ZIP archive with proper structure
Write-Host "📦 Creating ZIP archive with proper folder structure..." -ForegroundColor $Green

try {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
    
    foreach ($file in $allFiles) {
        $relativePath = $file.FullName.Substring($sourceDir.Length + 1)
        $zipEntryPath = "$PluginName/" + $relativePath.Replace('\', '/')
        
        # Highlight main files
        $prefix = if ($file.Name -eq "$PluginName.php" -or $file.Name -eq "$PluginName.xml") { 
            "MAIN" 
        } else { 
            "    " 
        }
        
        Write-Host "   ➕ [$prefix] Adding: $zipEntryPath" -ForegroundColor $Green
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $zipEntryPath) | Out-Null
    }
    
    $zip.Dispose()
    
} catch {
    Write-Host "❌ Failed to create ZIP archive: $($_.Exception.Message)" -ForegroundColor $Red
    exit 1
}

# Results and validation
$zipFileInfo = Get-Item $zipPath
$fileSize = [math]::Round($zipFileInfo.Length / 1KB, 1)

Write-Host ""
Write-Host "✅ ZIP archive created successfully!" -ForegroundColor $Green
Write-Host "📊 File size: $fileSize KB" -ForegroundColor $Cyan
Write-Host "📂 Location: $zipPath" -ForegroundColor $Cyan

# Validate ZIP contents
Write-Host ""
Write-Host "📋 Package contents verification:" -ForegroundColor $Yellow
try {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zipToRead = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    
    $entriesCount = $zipToRead.Entries.Count
    Write-Host "   📦 Total entries: $entriesCount" -ForegroundColor $Cyan
    
    foreach ($entry in $zipToRead.Entries) {
        $entrySize = [math]::Round($entry.Length / 1KB, 1)
        Write-Host "   📄 $($entry.FullName) ($entrySize KB)" -ForegroundColor $White
    }
    
    $zipToRead.Dispose()
    
    # Validate structure
    $hasMainFolder = $zipToRead.Entries | Where-Object { $_.FullName.StartsWith("$PluginName/") }
    if ($hasMainFolder) {
        Write-Host "   ✅ Proper folder structure confirmed" -ForegroundColor $Green
    } else {
        Write-Host "   ❌ Invalid folder structure" -ForegroundColor $Red
    }
    
} catch {
    Write-Host "   ❌ Error reading ZIP contents: $($_.Exception.Message)" -ForegroundColor $Red
}

# File size validation
if ($fileSize -lt 2) {
    Write-Host "⚠️  Warning: File size very small ($fileSize KB) - check if all files included" -ForegroundColor $Yellow
} elseif ($fileSize -gt 100) {
    Write-Host "⚠️  Warning: File size large ($fileSize KB) - consider optimizing" -ForegroundColor $Yellow
} else {
    Write-Host "✅ File size within normal range" -ForegroundColor $Green
}

# Generate build manifest
$manifest = @{
    PluginName = $PluginName
    Version = $Version
    BuildTimestamp = $timestamp
    ZipFile = $zipName
    FileSizeKB = $fileSize
    SourceDir = $SourceDir
    FileCount = $allFiles.Count
    BuildOptions = @{
        Minimal = $Minimal.IsPresent
        Debug = $Debug.IsPresent
    }
}

$manifestPath = Join-Path $buildDir "build-manifest.json"
$manifest | ConvertTo-Json -Depth 3 | Set-Content $manifestPath
Write-Host "📋 Build manifest saved: build-manifest.json" -ForegroundColor $Cyan

# Installation instructions
Write-Host ""
Write-Host "🎯 INSTALLATION INSTRUCTIONS" -ForegroundColor $Green
Write-Host "============================" -ForegroundColor $Green
Write-Host "1. 📥 Download: $zipName" -ForegroundColor $White
Write-Host "2. 🌐 Go to Joomla Administrator Panel" -ForegroundColor $White
Write-Host "3. 🔧 Navigate to Extensions > Manage > Install" -ForegroundColor $White
Write-Host "4. 📤 Select 'Upload Package File' tab" -ForegroundColor $White
Write-Host "5. 📎 Choose the ZIP file and click 'Upload & Install'" -ForegroundColor $White
Write-Host "6. ✅ Go to Extensions > Plugins > System" -ForegroundColor $White
Write-Host "7. 🔛 Find '$PluginName' and set Status to 'Enabled'" -ForegroundColor $White
Write-Host "8. ⚙️  Configure plugin settings as needed" -ForegroundColor $White

# Testing instructions
Write-Host ""
Write-Host "🧪 TESTING CHECKLIST" -ForegroundColor $Yellow
Write-Host "====================" -ForegroundColor $Yellow
Write-Host "□ Plugin installs without errors" -ForegroundColor $White
Write-Host "□ Plugin enables successfully" -ForegroundColor $White
Write-Host "□ Check plugin configuration options" -ForegroundColor $White
Write-Host "□ Test core functionality" -ForegroundColor $White
Write-Host "□ Verify domain detection (staging vs production)" -ForegroundColor $White
Write-Host "□ Test robots.txt endpoint: /robots.txt" -ForegroundColor $White
Write-Host "□ Test sitemap.xml endpoint: /sitemap.xml" -ForegroundColor $White
Write-Host "□ Check browser console for errors" -ForegroundColor $White
Write-Host "□ Verify SEO meta tags injection" -ForegroundColor $White

# Auto-test option
if ($AutoTest) {
    Write-Host ""
    Write-Host "🧪 RUNNING AUTO-TEST" -ForegroundColor $Yellow
    Write-Host "===================" -ForegroundColor $Yellow
    
    $testScript = Join-Path $PSScriptRoot "test-after-install.ps1"
    if (Test-Path $testScript) {
        & $testScript
    } else {
        Write-Host "⚠️  Auto-test script not found: $testScript" -ForegroundColor $Yellow
    }
}

Write-Host ""
Write-Host "🎉 BUILD COMPLETED SUCCESSFULLY!" -ForegroundColor $Green
Write-Host "=================================" -ForegroundColor $Green
Write-Host "Plugin: $PluginName v$Version" -ForegroundColor $Cyan
Write-Host "Package: $zipName" -ForegroundColor $Cyan
Write-Host "Size: $fileSize KB" -ForegroundColor $Cyan
Write-Host "Ready for deployment! 🚀" -ForegroundColor $Green
