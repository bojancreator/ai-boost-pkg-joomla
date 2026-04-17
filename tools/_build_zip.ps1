$src = 'c:\POSLOVI\__JoomlaBoost\src\plugins\system\joomlaboost'
$buildDir = 'c:\POSLOVI\__JoomlaBoost\tools\__build'
$archiveDir = Join-Path $buildDir 'archive'

# Load ZIP support FIRST
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

# Read version from XML
$xmlPath = Join-Path $src 'joomlaboost.xml'
$xmlContent = Get-Content $xmlPath -Raw -Encoding UTF8
if ($xmlContent -match '<version>([^<]+)</version>') {
    $version = $Matches[1].Trim()
}
else {
    $version = '0.0.0'
}

# Update creationDate in XML
$buildDate = Get-Date -Format 'MMMM d, yyyy HH:mm'
$xmlContent = $xmlContent -replace '<creationDate>[^<]+<\/creationDate>', "<creationDate>$buildDate</creationDate>"
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($xmlPath, $xmlContent, $utf8NoBom)

# Update Version.php to match XML version
$versionPhpPath = Join-Path $src 'src\Version.php'
if (Test-Path $versionPhpPath) {
    $versionPhpContent = Get-Content $versionPhpPath -Raw -Encoding UTF8
    $versionPhpContent = $versionPhpContent -replace "const VERSION = '[^']+';", "const VERSION = '$version';"
    [System.IO.File]::WriteAllText($versionPhpPath, $versionPhpContent, $utf8NoBom)
    Write-Host "Updated Version.php to $version"
}

$zipPath = Join-Path $buildDir "joomlaboost-$version.zip"
Write-Host "Building version: $version"
Write-Host "Output: $zipPath"

# Ensure build + archive dirs exist
New-Item -ItemType Directory -Force -Path $buildDir | Out-Null
New-Item -ItemType Directory -Force -Path $archiveDir | Out-Null

# Archive old builds (move all existing ZIPs to archive/)
$existingZips = Get-ChildItem -Path $buildDir -Filter "joomlaboost-*.zip" -File
if ($existingZips.Count -gt 0) {
    foreach ($oldZip in $existingZips) {
        Move-Item -Path $oldZip.FullName -Destination $archiveDir -Force
        Write-Host "  Archived: $($oldZip.Name)"
    }
}

# Exclusion patterns
$excludePatterns = @('*.backup', '*_OLD.php', '*.v0.*', 'AllServices.php')

# Create ZIP with joomlaboost/ prefix (Joomla-compatible structure)
$zipStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
$zip = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -Path $src -Recurse -File | Where-Object {
        $exclude = $false
        foreach ($pattern in $excludePatterns) {
            if ($_.Name -like $pattern) { $exclude = $true; break }
        }
        -not $exclude
    } | ForEach-Object {
        $relativePath = $_.FullName.Substring($src.Length + 1)
        $zipEntryName = 'joomlaboost/' + $relativePath.Replace('\', '/')
        Write-Host "  + $zipEntryName"
        $entry = $zip.CreateEntry($zipEntryName, [System.IO.Compression.CompressionLevel]::Optimal)
        $es = $entry.Open()
        $fs = [System.IO.File]::OpenRead($_.FullName)
        $fs.CopyTo($es)
        $fs.Dispose()
        $es.Dispose()
    }
}
finally {
    $zip.Dispose()
    $zipStream.Dispose()
}

$sizeKB = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
$archiveCount = (Get-ChildItem -Path $archiveDir -Filter "*.zip" -File).Count
Write-Host ""
Write-Host "Done! $zipPath ($sizeKB KB)"
Write-Host "Archive: $archiveCount previous build(s) in $archiveDir"
