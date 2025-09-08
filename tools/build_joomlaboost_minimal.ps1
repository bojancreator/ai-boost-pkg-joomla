# Build JoomlaBoost Minimal Test Plugin
# Usage: .\tools\build_joomlaboost_minimal.ps1

param(
    [string]$Version = "0.1.17-minimal"
)

Write-Host "🧪 Building JoomlaBoost MINIMAL Test Plugin" -ForegroundColor Green
Write-Host "===========================================" -ForegroundColor Green

$baseDir = Split-Path -Parent $PSScriptRoot
$sourceDir = Join-Path $baseDir "plugin"
$buildDir = Join-Path $baseDir "build"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"

Write-Host "📁 Source directory: $sourceDir" -ForegroundColor Yellow
Write-Host "📦 Building version: $Version" -ForegroundColor Yellow
Write-Host "🕒 Timestamp: $timestamp" -ForegroundColor Yellow
Write-Host ""

# Create build directory
if (!(Test-Path $buildDir)) {
    New-Item -ItemType Directory -Path $buildDir -Force | Out-Null
}

# Define minimal files
$filesToInclude = @(
    "joomlaboost-test-minimal.php",
    "joomlaboost-test-minimal.xml",
    "language\en-GB\plg_system_joomlaboost.ini",
    "language\en-GB\plg_system_joomlaboost.sys.ini"
)

Write-Host "📋 Minimal files to be packaged:" -ForegroundColor Cyan
foreach ($file in $filesToInclude) {
    $fullPath = Join-Path $sourceDir $file
    if (Test-Path $fullPath) {
        Write-Host "   ✅ $file" -ForegroundColor Green
    } else {
        Write-Host "   ❌ $file (missing)" -ForegroundColor Red
    }
}
Write-Host ""

# Remove existing build
$zipPath = Join-Path $buildDir "joomlaboost-$Version-test.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
    Write-Host "🗑️ Removed existing test build" -ForegroundColor Yellow
}

# Create ZIP archive
Write-Host "📦 Creating minimal ZIP archive..." -ForegroundColor Green

try {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
    
    foreach ($file in $filesToInclude) {
        $sourcePath = Join-Path $sourceDir $file
        
        if (Test-Path $sourcePath) {
            $entryName = if ($file -eq "joomlaboost-test-minimal.php") {
                "joomlaboost/joomlaboost.php"  # Rename main file
            } elseif ($file -eq "joomlaboost-test-minimal.xml") {
                "joomlaboost/joomlaboost.xml"  # Rename XML file
            } else {
                "joomlaboost/$file"
            }
            
            $entry = $zip.CreateEntry($entryName)
            $entryStream = $entry.Open()
            $fileStream = [System.IO.File]::OpenRead($sourcePath)
            $fileStream.CopyTo($entryStream)
            $fileStream.Close()
            $entryStream.Close()
            
            Write-Host "   ➕ Adding: $entryName" -ForegroundColor Green
        }
    }
    
    $zip.Dispose()
    
    $zipSize = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
    Write-Host "✅ Minimal ZIP archive created successfully!" -ForegroundColor Green
    Write-Host "📊 File size: $zipSize KB" -ForegroundColor Cyan
    Write-Host "📂 Location: $zipPath" -ForegroundColor Cyan
    
} catch {
    Write-Host "❌ Error creating ZIP: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎯 MINIMAL TEST Installation Instructions:" -ForegroundColor Yellow
Write-Host "=========================================" -ForegroundColor Yellow
Write-Host "1. 📥 Download: joomlaboost-$Version-test.zip" -ForegroundColor White
Write-Host "2. 🌐 Go to Joomla Administrator" -ForegroundColor White
Write-Host "3. 🔧 Navigate to Extensions > Manage > Install" -ForegroundColor White
Write-Host "4. 📤 Upload the ZIP file" -ForegroundColor White
Write-Host "5. ✅ Enable the plugin in System Plugins" -ForegroundColor White
Write-Host "6. 🧪 Test robots.txt and sitemap.xml" -ForegroundColor White
Write-Host ""

Write-Host "🧪 Testing URLs:" -ForegroundColor Cyan
Write-Host "   - https://staging.offroadserbia.com/robots.txt" -ForegroundColor White
Write-Host "   - https://staging.offroadserbia.com/sitemap.xml" -ForegroundColor White
Write-Host ""

Write-Host "✨ Minimal build completed successfully!" -ForegroundColor Green
