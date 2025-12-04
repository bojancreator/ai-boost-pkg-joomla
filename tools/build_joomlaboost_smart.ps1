param(
    [string]$Version = "",
    [switch]$Force = $false,
    [switch]$ValidateOnly = $false
)

# Colors for console output
$Red = "`e[31m"
$Green = "`e[32m"
$Yellow = "`e[33m"
$Blue = "`e[34m"
$Magenta = "`e[35m"
$Cyan = "`e[36m"
$White = "`e[37m"
$Reset = "`e[0m"

Write-Host "${Cyan}🔧 JoomlaBoost Smart Builder v2.0${Reset}" -ForegroundColor Cyan
Write-Host "${Yellow}====================================${Reset}" -ForegroundColor Yellow

# Set paths
$scriptDir = $PSScriptRoot
$projectRoot = Split-Path $scriptDir -Parent
$sourceDir = Join-Path $projectRoot "src\plugins\system\joomlaboost"
$buildDir = Join-Path $scriptDir "__build"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"

# Validation flags
$validationErrors = @()
$validationWarnings = @()

Write-Host "${Blue}📁 Source directory: $sourceDir${Reset}"
Write-Host "${Cyan}🕒 Timestamp: $timestamp${Reset}"
Write-Host ""

# ================================
# VALIDATION PHASE
# ================================

Write-Host "${Magenta}🔍 VALIDATION PHASE${Reset}" -ForegroundColor Magenta
Write-Host "${Yellow}===================${Reset}"

# 1. Check XML Manifest
Write-Host "${Blue}1. Checking XML manifest...${Reset}"
$xmlPath = Join-Path $sourceDir "joomlaboost.xml"
if (-not (Test-Path $xmlPath)) {
    $validationErrors += "❌ XML manifest not found: $xmlPath"
}
else {
    $xmlContent = Get-Content $xmlPath -Raw
    
    # Extract version from XML
    if ($xmlContent -match '<version>([^<]+)</version>') {
        $xmlVersion = $matches[1]
        Write-Host "   📋 XML Version: $xmlVersion" -ForegroundColor Green
        
        # Use XML version if no version parameter provided
        if ([string]::IsNullOrEmpty($Version)) {
            $Version = $xmlVersion
        }
        elseif ($Version -ne $xmlVersion) {
            $validationWarnings += "⚠️  Parameter version ($Version) differs from XML version ($xmlVersion)"
        }
    }
    else {
        $validationErrors += "❌ No version found in XML manifest"
    }
    
    # Check required folders in XML
    $requiredFolders = @("src", "language")
    $optionalFolders = @("media")
    
    foreach ($folder in $requiredFolders) {
        if ($xmlContent -notmatch "<folder>$folder</folder>") {
            $validationErrors += "❌ Missing required folder in XML: <folder>$folder</folder>"
        }
        else {
            Write-Host "   ✅ Found required folder: $folder" -ForegroundColor Green
        }
    }
    
    foreach ($folder in $optionalFolders) {
        if ($xmlContent -match "<folder>$folder</folder>") {
            Write-Host "   ✅ Found optional folder: $folder" -ForegroundColor Green
        }
    }
}

# 2. Check file structure
Write-Host "${Blue}2. Checking file structure...${Reset}"
$requiredFiles = @(
    "joomlaboost.php",
    "joomlaboost.xml",
    "src\Services\ServiceInterface.php",
    "src\Services\AbstractService.php", 
    "src\Services\SchemaService.php"
)

foreach ($file in $requiredFiles) {
    $filePath = Join-Path $sourceDir $file
    if (Test-Path $filePath) {
        Write-Host "   ✅ Found: $file" -ForegroundColor Green
    }
    else {
        $validationErrors += "❌ Missing required file: $file"
    }
}

# 3. Syntax validation  
Write-Host "${Blue}3. Checking PHP syntax...${Reset}"
$phpFiles = Get-ChildItem -Path $sourceDir -Filter "*.php" -Recurse
foreach ($file in $phpFiles) {
    $relativePath = $file.FullName.Substring($sourceDir.Length + 1)
    try {
        $syntaxCheck = php -l $file.FullName 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "   ✅ Syntax OK: $relativePath" -ForegroundColor Green
        }
        else {
            $validationErrors += "❌ Syntax error in $relativePath`: $syntaxCheck"
        }
    }
    catch {
        $validationWarnings += "⚠️  Could not check syntax for $relativePath (php command not available)"
    }
}

# 4. Check Schema Service integration
Write-Host "${Blue}4. Checking Schema Service integration...${Reset}"
$mainPluginPath = Join-Path $sourceDir "joomlaboost.php"
if (Test-Path $mainPluginPath) {
    $mainPluginContent = Get-Content $mainPluginPath -Raw
    
    $requiredPatterns = @(
        @{Pattern = "require_once.*ServiceInterface\.php"; Description = "ServiceInterface require" },
        @{Pattern = "require_once.*SchemaService\.php"; Description = "SchemaService require" },
        @{Pattern = "use.*SchemaService"; Description = "SchemaService use statement" },
        @{Pattern = "onBeforeCompileHead.*void"; Description = "onBeforeCompileHead method" },
        @{Pattern = "addSchemaMarkup.*void"; Description = "addSchemaMarkup method" }
    )
    
    foreach ($check in $requiredPatterns) {
        if ($mainPluginContent -match $check.Pattern) {
            Write-Host "   ✅ Found: $($check.Description)" -ForegroundColor Green
        }
        else {
            $validationWarnings += "⚠️  Missing or incorrect: $($check.Description)"
        }
    }
}

# ================================
# VALIDATION RESULTS
# ================================

Write-Host ""
Write-Host "${Magenta}📊 VALIDATION RESULTS${Reset}" -ForegroundColor Magenta
Write-Host "${Yellow}=====================${Reset}"

if ($validationErrors.Count -gt 0) {
    Write-Host "${Red}❌ VALIDATION FAILED${Reset}" -ForegroundColor Red
    foreach ($validationError in $validationErrors) {
        Write-Host "   $validationError" -ForegroundColor Red
    }
    
    if (-not $Force) {
        Write-Host ""
        Write-Host "${Yellow}Use -Force to build anyway (not recommended)${Reset}"
        exit 1
    }
    else {
        Write-Host "${Yellow}⚠️  Continuing with -Force flag...${Reset}"
    }
}

if ($validationWarnings.Count -gt 0) {
    Write-Host "${Yellow}⚠️  WARNINGS:${Reset}" -ForegroundColor Yellow
    foreach ($validationWarning in $validationWarnings) {
        Write-Host "   $validationWarning" -ForegroundColor Yellow
    }
}

if ($validationErrors.Count -eq 0) {
    Write-Host "${Green}✅ All validations passed!${Reset}" -ForegroundColor Green
}

if ($ValidateOnly) {
    Write-Host ""
    Write-Host "${Blue}🔍 Validation complete. Exiting (ValidateOnly mode).${Reset}"
    exit 0
}

# ================================
# BUILD PHASE
# ================================

Write-Host ""
Write-Host "${Magenta}🔨 BUILD PHASE${Reset}" -ForegroundColor Magenta
Write-Host "${Yellow}===============${Reset}"

Write-Host "${Magenta}📦 Building version: $Version${Reset}"

# Create build directory
if (Test-Path $buildDir) {
    Remove-Item $buildDir -Recurse -Force -ErrorAction SilentlyContinue
    Start-Sleep -Milliseconds 500
    Write-Host "${Yellow}🗑️ Removed existing build${Reset}"
}
New-Item -ItemType Directory -Path $buildDir -Force | Out-Null

# List files to be packaged
Write-Host "${Blue}📋 Files to be packaged:${Reset}"
$allFiles = Get-ChildItem -Path $sourceDir -Recurse -File
foreach ($file in $allFiles) {
    $relativePath = $file.FullName.Substring($sourceDir.Length + 1)
    Write-Host "   📄 $relativePath"
}

# Update XML creationDate with exact build timestamp
Write-Host "${Blue}📅 Updating creationDate in XML manifest...${Reset}"
$buildDate = Get-Date -Format "MMMM d, yyyy HH:mm"
$xmlPath = Join-Path $sourceDir "joomlaboost.xml"

if (Test-Path $xmlPath) {
    $xmlContent = Get-Content $xmlPath -Raw -Encoding UTF8
    $xmlContent = $xmlContent -replace '<creationDate>[^<]+</creationDate>', "<creationDate>$buildDate</creationDate>"
    Set-Content $xmlPath $xmlContent -Encoding UTF8 -NoNewline
    Write-Host "${Green}✅ Updated creationDate to: $buildDate${Reset}"
}
else {
    Write-Host "${Red}⚠️ Warning: joomlaboost.xml not found in source directory${Reset}"
}

# Create ZIP file
$zipFileName = "joomlaboost-$Version.zip"
$zipPath = Join-Path $buildDir $zipFileName

Write-Host "${Blue}📦 Creating ZIP archive with proper folder structure...${Reset}"

# Create ZIP with proper internal structure
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    # Add exclusion patterns for backup and legacy files
    $excludePatterns = @('*.backup', '*_OLD.php', '*.v0.*', 'AllServices.php')
    
    # Add all files with joomlaboost/ prefix, excluding unwanted patterns
    Get-ChildItem -Path $sourceDir -Recurse -File | Where-Object {
        $exclude = $false
        foreach ($pattern in $excludePatterns) {
            if ($_.Name -like $pattern) {
                Write-Host "   ${Yellow}⊗ Excluding: $($_.Name)${Reset}" -ForegroundColor Yellow
                $exclude = $true
                break
            }
        }
        !$exclude
    } | ForEach-Object {
        $relativePath = $_.FullName.Substring($sourceDir.Length + 1)
        $zipEntryName = "joomlaboost/" + $relativePath.Replace('\', '/')
        
        # Highlight main files
        if ($_.Name -eq "joomlaboost.php" -or $_.Name -eq "joomlaboost.xml") {
            Write-Host "   ${Green}➕ Adding (main): $zipEntryName${Reset}"
        }
        else {
            Write-Host "   ${Green}➕ Adding: $zipEntryName${Reset}"
        }
        
        $entry = $zip.CreateEntry($zipEntryName)
        $entryStream = $entry.Open()
        $fileStream = $_.OpenRead()
        $fileStream.CopyTo($entryStream)
        $fileStream.Close()
        $entryStream.Close()
    }
}
finally {
    $zip.Dispose()
}

# Get file size
$fileSize = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
Write-Host "${Green}✅ ZIP archive created successfully!${Reset}"
Write-Host "${Cyan}📊 File size: $fileSize KB${Reset}"
Write-Host "${Blue}📂 Location: $zipPath${Reset}"

Write-Host ""
Write-Host "${Yellow}📋 Package contents:${Reset}"
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zipToRead = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $zipToRead.Entries | ForEach-Object {
        Write-Host "   $($_.FullName)"
    }
}
finally {
    $zipToRead.Dispose()
}

Write-Host ""
Write-Host "${Cyan}🎯 Installation Instructions:${Reset}"
Write-Host "${Yellow}=============================${Reset}"
Write-Host "${White}1. 📥 Download: $zipFileName${Reset}"
Write-Host "${White}2. 🌐 Go to Joomla Administrator${Reset}"
Write-Host "${White}3. 🔧 Navigate to Extensions > Manage > Install${Reset}"
Write-Host "${White}4. 📤 Upload the ZIP file${Reset}"
Write-Host "${White}5. ✅ Enable the plugin in System Plugins${Reset}"
Write-Host "${White}6. ⚙️ Configure settings as needed${Reset}"
Write-Host "${White}7. 🎯 Set 'enable_schema' to 'Yes' for JSON-LD output${Reset}"
Write-Host "${White}8. 🎯 Set 'debug_mode' to 'Yes' for troubleshooting${Reset}"

Write-Host ""
Write-Host "${Green}✨ Build completed successfully!${Reset}"

# Final size check
if ($fileSize -lt 25 -or $fileSize -gt 35) {
    Write-Host "${Yellow}⚠️  Warning: File size ($fileSize KB) is outside expected range (25-35 KB)${Reset}"
}
