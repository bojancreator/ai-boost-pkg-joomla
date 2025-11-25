#Requires -Version 5.1

<#
.SYNOPSIS
    Build OG Fix Fields Plugin for Joomla 4.x

.DESCRIPTION
    Creates installable ZIP package for plg_fields_ogfix plugin.

.PARAMETER Version
    Plugin version (optional, reads from XML if not provided)

.EXAMPLE
    .\build_ogfix.ps1 -Version "0.1.102"
#>

param(
    [string]$Version = ""
)

$ErrorActionPreference = "Stop"

# Paths
$sourceDir = Join-Path $PSScriptRoot "..\src\plugins\fields\ogfix"
$buildDir = Join-Path $PSScriptRoot "__build"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"

Write-Host "`n🔧 OG Fix Plugin Builder" -ForegroundColor Cyan
Write-Host "=========================" -ForegroundColor Cyan

# Validate source directory
if (-not (Test-Path $sourceDir)) {
    Write-Host "❌ Source directory not found: $sourceDir" -ForegroundColor Red
    exit 1
}

# Read version from XML if not provided
if ([string]::IsNullOrEmpty($Version)) {
    $xmlPath = Join-Path $sourceDir "ogfix.xml"
    if (Test-Path $xmlPath) {
        [xml]$xml = Get-Content $xmlPath
        $Version = $xml.extension.version
        Write-Host "📋 Version from XML: $Version" -ForegroundColor Green
    } else {
        Write-Host "❌ Cannot find ogfix.xml" -ForegroundColor Red
        exit 1
    }
}

# Create build directory
if (-not (Test-Path $buildDir)) {
    New-Item -ItemType Directory -Path $buildDir | Out-Null
}

# Package name
$zipName = "plg_fields_ogfix-$Version.zip"
$zipPath = Join-Path $buildDir $zipName

# Remove existing package
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
    Write-Host "🗑️ Removed existing: $zipName" -ForegroundColor Yellow
}

# Files to package
$files = @(
    "ogfix.xml",
    "src\Extension\Ogfix.php",
    "language\en-GB\plg_fields_ogfix.ini",
    "language\en-GB\plg_fields_ogfix.sys.ini"
)

Write-Host "`n📦 Creating ZIP archive..." -ForegroundColor Cyan

try {
    # Create ZIP using .NET
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

    foreach ($file in $files) {
        $fullPath = Join-Path $sourceDir $file
        if (Test-Path $fullPath) {
            # Use forward slashes in ZIP
            $entryName = $file -replace '\\', '/'
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $fullPath, $entryName) | Out-Null
            Write-Host "   ➕ $entryName" -ForegroundColor Gray
        } else {
            Write-Host "   ⚠️ Not found: $file" -ForegroundColor Yellow
        }
    }

    $zip.Dispose()

    $fileSize = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)

    Write-Host "`n✅ Package created successfully!" -ForegroundColor Green
    Write-Host "📊 File size: $fileSize KB" -ForegroundColor Cyan
    Write-Host "📂 Location: $zipPath" -ForegroundColor Cyan

    Write-Host "`n🎯 Installation Instructions:" -ForegroundColor Cyan
    Write-Host "=============================" -ForegroundColor Cyan
    Write-Host "1. 📥 Upload ZIP via Extensions > Manage > Install" -ForegroundColor White
    Write-Host "2. ✅ Enable plugin in System > Plugins" -ForegroundColor White
    Write-Host "3. ⚙️ Set plugin ordering ABOVE 'Fields - Media'" -ForegroundColor Yellow
    Write-Host "   (System > Plugins > Filter: fields group > Drag to reorder)" -ForegroundColor Gray
    Write-Host "4. 🧪 Test: Open article in admin without custom_og_image value" -ForegroundColor White
    Write-Host "5. ✨ No more json_decode(null) deprecation warnings!" -ForegroundColor Green

} catch {
    Write-Host "`n❌ Build failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
