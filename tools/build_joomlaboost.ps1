# JoomlaBoost Plugin Builder
# PowerShell script to create installable ZIP package

$ErrorActionPreference = "Stop"

Write-Host "📦 JoomlaBoost Plugin Builder" -ForegroundColor Green
Write-Host "============================" -ForegroundColor Green
Write-Host ""

$baseDir = Split-Path -Parent $PSScriptRoot
$sourceDir = Join-Path $baseDir "plugin"
$buildDir = Join-Path $baseDir "build"

# Read version from XML file
$xmlPath = Join-Path $sourceDir "joomlaboost.xml"
if (Test-Path $xmlPath) {
    $xmlContent = Get-Content $xmlPath -Raw
    if ($xmlContent -match '<version>([^<]+)</version>') {
        $currentVersion = $matches[1]
        
        # Auto-increment version (bump patch number)
        if ($currentVersion -match '(\d+)\.(\d+)\.(\d+)') {
            $major = [int]$matches[1]
            $minor = [int]$matches[2]
            $patch = [int]$matches[3]
            $patch++
            $version = "$major.$minor.$patch"
            
            # Update version in XML files
            $xmlContent = $xmlContent -replace '<version>[^<]+</version>', "<version>$version</version>"
            Set-Content -Path $xmlPath -Value $xmlContent -NoNewline
            
            # Also update in src directory
            $srcXmlPath = Join-Path $baseDir "src\plugins\system\joomlaboost\joomlaboost.xml"
            if (Test-Path $srcXmlPath) {
                $srcXmlContent = Get-Content $srcXmlPath -Raw
                $srcXmlContent = $srcXmlContent -replace '<version>[^<]+</version>', "<version>$version</version>"
                Set-Content -Path $srcXmlPath -Value $srcXmlContent -NoNewline
            }
            
            Write-Host "🔼 Version auto-incremented: $currentVersion → $version" -ForegroundColor Green
        }
        else {
            $version = $currentVersion
            Write-Host "🔍 Version read from XML: $version" -ForegroundColor Green
        }
    }
    else {
        $version = "0.2.5"
        Write-Host "⚠️  Could not read version from XML, using default: $version" -ForegroundColor Yellow
    }
}
else {
    $version = "0.2.5"
    Write-Host "⚠️  XML file not found, using default version: $version" -ForegroundColor Yellow
}

$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$zipName = "joomlaboost-$version.zip"

# Check source directory
if (-not (Test-Path $sourceDir)) {
    Write-Host "❌ Source directory not found: $sourceDir" -ForegroundColor Red
    exit 1
}

Write-Host "📁 Source directory: $sourceDir" -ForegroundColor Cyan
Write-Host "📦 Building version: $version" -ForegroundColor Cyan  
Write-Host "🕒 Timestamp: $timestamp" -ForegroundColor Cyan
Write-Host ""

# Archive old versions to build/old/
$oldDir = Join-Path $buildDir "old"
if (-not (Test-Path $oldDir)) {
    New-Item -Path $oldDir -ItemType Directory -Force | Out-Null
}

# Move existing ZIP files (except current version) to old/
$existingZips = Get-ChildItem -Path $buildDir -Filter "joomlaboost-*.zip" -File
if ($existingZips.Count -gt 0) {
    Write-Host "📦 Archiving old versions..." -ForegroundColor Yellow
    foreach ($zip in $existingZips) {
        Move-Item -Path $zip.FullName -Destination (Join-Path $oldDir $zip.Name) -Force
        Write-Host "   ➜ Moved $($zip.Name) to old/" -ForegroundColor Gray
    }
    Write-Host ""
}

# List all files that will be included
Write-Host "📋 Files to be packaged:" -ForegroundColor Yellow
Get-ChildItem $sourceDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
    Write-Host "   📄 $relativePath" -ForegroundColor Gray
}
Write-Host ""

# Create build directory
if (-not (Test-Path $buildDir)) {
    New-Item -ItemType Directory -Path $buildDir -Force | Out-Null
}

$zipPath = Join-Path $buildDir $zipName

# Remove existing ZIP
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
    Write-Host "🗑️ Removed existing build" -ForegroundColor Yellow
}

Write-Host "📦 Creating ZIP archive with proper folder structure..." -ForegroundColor Green

try {
    # Create ZIP manually using file-by-file approach with folder structure
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
    
    # First, add main plugin files explicitly
    $mainFiles = @("joomlaboost.php", "joomlaboost.xml")
    foreach ($mainFile in $mainFiles) {
        $mainFilePath = Join-Path $sourceDir $mainFile
        if (Test-Path $mainFilePath) {
            $zipEntryPath = "joomlaboost/" + $mainFile
            Write-Host "   ➕ Adding (main): $zipEntryPath" -ForegroundColor Yellow
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $mainFilePath, $zipEntryPath) | Out-Null
        }
    }
    
    # Then add all other files recursively
    Get-ChildItem $sourceDir -Recurse -File | Where-Object { $_.Name -notin $mainFiles } | ForEach-Object {
        $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
        $relativePath = $relativePath.Replace('\', '/')
        
        # Add joomlaboost/ prefix to create proper folder structure
        $zipEntryPath = "joomlaboost/" + $relativePath
        
        Write-Host "   ➕ Adding: $zipEntryPath" -ForegroundColor Cyan
        
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $zipEntryPath) | Out-Null
    }
    
    $zip.Dispose()
    
    Write-Host "✅ ZIP archive created successfully!" -ForegroundColor Green
    
    $fileSize = (Get-Item $zipPath).Length
    $fileSizeKB = [math]::Round($fileSize / 1024, 1)
    
    Write-Host "📊 File size: $fileSizeKB KB" -ForegroundColor Cyan
    Write-Host "📂 Location: $zipPath" -ForegroundColor Cyan
    Write-Host ""
    
    # List ZIP contents
    Write-Host "📋 Package contents:" -ForegroundColor Yellow
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    foreach ($entry in $zip.Entries) {
        Write-Host "   $($entry.FullName)" -ForegroundColor Gray
    }
    $zip.Dispose()
    
    Write-Host ""
    Write-Host "✨ Build completed successfully!" -ForegroundColor Green
    Write-Host "📦 Package: $zipName" -ForegroundColor Cyan
    Write-Host "📍 Location: $zipPath" -ForegroundColor Gray
    
}
catch {
    Write-Host "❌ Failed to create ZIP archive: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
