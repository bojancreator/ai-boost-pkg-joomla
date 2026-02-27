$src = 'c:\POSLOVI\__JoomlaBoost\src\plugins\system\joomlaboost'
$buildDir = 'c:\POSLOVI\__JoomlaBoost\tools\__build'

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

# Update creationDate
$buildDate = Get-Date -Format 'MMMM d, yyyy HH:mm'
$xmlContent = $xmlContent -replace '<creationDate>[^<]+<\/creationDate>', "<creationDate>$buildDate</creationDate>"
Set-Content $xmlPath $xmlContent -Encoding UTF8 -NoNewline

$zipPath = Join-Path $buildDir "joomlaboost-$version.zip"
Write-Host "Building version: $version"
Write-Host "Output: $zipPath"

# Ensure build dir exists, remove old ZIP
New-Item -ItemType Directory -Force -Path $buildDir | Out-Null
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

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
Write-Host ""
Write-Host "Done! $zipPath ($sizeKB KB)"
