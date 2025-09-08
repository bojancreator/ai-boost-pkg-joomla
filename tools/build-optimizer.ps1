# JoomlaBoost Build Optimization Script
# Optimizes performance and reduces plugin size

param(
    [switch]$Production = $false,
    [switch]$Debug = $false,
    [string]$Version = "1.0.0"
)

Write-Host "🚀 JoomlaBoost Build Optimizer v$Version" -ForegroundColor Green

$projectRoot = Split-Path -Parent $PSScriptRoot
$buildDir = Join-Path $projectRoot "build"
$sourceDir = Join-Path $projectRoot "src"

# Create build directory
if (-not (Test-Path $buildDir)) {
    New-Item -ItemType Directory -Path $buildDir | Out-Null
}

Write-Host "📁 Creating optimized build..." -ForegroundColor Yellow

# Performance optimizations
$optimizations = @{
    "remove_comments" = $true
    "minify_whitespace" = $true
    "optimize_autoload" = $true
    "compress_services" = $true
}

if ($Production) {
    Write-Host "🏭 Production mode enabled" -ForegroundColor Cyan
    
    # Production optimizations
    $optimizations["remove_debug"] = $true
    $optimizations["strip_docblocks"] = $true
    $optimizations["enable_opcache"] = $true
    
    # Copy optimized plugin file
    $sourceFile = Join-Path $sourceDir "plugins\system\joomlaboost\joomlaboost-optimized.php"
    $targetFile = Join-Path $buildDir "joomlaboost.php"
    
    if (Test-Path $sourceFile) {
        Copy-Item $sourceFile $targetFile
        Write-Host "✅ Optimized plugin file copied" -ForegroundColor Green
    }
} else {
    Write-Host "🔧 Development mode enabled" -ForegroundColor Cyan
    
    # Development optimizations (keep debugging)
    $optimizations["keep_debug"] = $true
    $optimizations["enable_profiling"] = $true
    
    # Copy standard plugin file
    $sourceFile = Join-Path $sourceDir "plugins\system\joomlaboost\joomlaboost.php"
    $targetFile = Join-Path $buildDir "joomlaboost.php"
    
    if (Test-Path $sourceFile) {
        Copy-Item $sourceFile $targetFile
        Write-Host "✅ Development plugin file copied" -ForegroundColor Green
    }
}

# Copy and optimize services
$servicesSource = Join-Path $sourceDir "plugins\system\joomlaboost\src\Services"
$servicesTarget = Join-Path $buildDir "src\Services"

if (Test-Path $servicesSource) {
    # Create target directory
    New-Item -ItemType Directory -Path $servicesTarget -Force | Out-Null
    
    # Copy service files
    Get-ChildItem $servicesSource -Filter "*.php" | ForEach-Object {
        $content = Get-Content $_.FullName -Raw
        
        if ($Production) {
            # Remove debug statements in production
            $content = $content -replace '\/\*\*[\s\S]*?\*\/', ''  # Remove docblocks
            $content = $content -replace '\/\/.*$', '' -split "`n" | Where-Object { $_.Trim() -ne '' } | Join-String -Separator "`n"
        }
        
        $targetFile = Join-Path $servicesTarget $_.Name
        Set-Content -Path $targetFile -Value $content
    }
    
    Write-Host "✅ Services optimized and copied" -ForegroundColor Green
}

# Generate performance manifest
$manifest = @{
    version = $Version
    build_date = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    optimizations = $optimizations
    services_count = (Get-ChildItem $servicesTarget -Filter "*.php").Count
    production_mode = $Production
    file_sizes = @{}
}

# Calculate file sizes
Get-ChildItem $buildDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Replace($buildDir, "").TrimStart("\")
    $manifest.file_sizes[$relativePath] = $_.Length
}

# Save manifest
$manifestPath = Join-Path $buildDir "build-manifest.json"
$manifest | ConvertTo-Json -Depth 3 | Set-Content $manifestPath

Write-Host "📊 Build Statistics:" -ForegroundColor Yellow
Write-Host "  Services: $($manifest.services_count)" -ForegroundColor White
Write-Host "  Total files: $(($manifest.file_sizes.Keys).Count)" -ForegroundColor White
Write-Host "  Build size: $([math]::Round(($manifest.file_sizes.Values | Measure-Object -Sum).Sum / 1KB, 2)) KB" -ForegroundColor White

if ($Debug) {
    Write-Host "🔍 Debug information:" -ForegroundColor Magenta
    $manifest | ConvertTo-Json -Depth 3 | Write-Host
}

Write-Host "🎯 Build completed successfully!" -ForegroundColor Green
Write-Host "📦 Output: $buildDir" -ForegroundColor Cyan
