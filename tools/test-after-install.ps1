# Test JoomlaBoost Plugin After Installation
Write-Host "🧪 Testing JoomlaBoost Plugin Installation..." -ForegroundColor Cyan

Write-Host "`n1. Testing robots.txt endpoint..." -ForegroundColor Yellow
try {
    $robotsResponse = curl -s "https://staging.offroadserbia.com/robots.txt"
    if ($robotsResponse -match "JoomlaBoost") {
        Write-Host "✅ robots.txt: WORKING - JoomlaBoost detected" -ForegroundColor Green
        Write-Host "Content preview:" -ForegroundColor Gray
        Write-Host $robotsResponse.Substring(0, [Math]::Min(200, $robotsResponse.Length)) -ForegroundColor Gray
    } else {
        Write-Host "❌ robots.txt: Plugin not working" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ robots.txt: Error accessing endpoint" -ForegroundColor Red
}

Write-Host "`n2. Testing sitemap.xml endpoint..." -ForegroundColor Yellow
try {
    $sitemapResponse = curl -s "https://staging.offroadserbia.com/sitemap.xml"
    if ($sitemapResponse -match "JoomlaBoost") {
        Write-Host "✅ sitemap.xml: WORKING - JoomlaBoost detected" -ForegroundColor Green
        Write-Host "Content preview:" -ForegroundColor Gray
        Write-Host $sitemapResponse.Substring(0, [Math]::Min(200, $sitemapResponse.Length)) -ForegroundColor Gray
    } else {
        Write-Host "❌ sitemap.xml: Plugin not working" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ sitemap.xml: Error accessing endpoint" -ForegroundColor Red
}

Write-Host "`n3. Testing HTTP headers..." -ForegroundColor Yellow
try {
    $headers = curl -I "https://staging.offroadserbia.com/robots.txt" 2>$null
    if ($headers -match "200 OK") {
        Write-Host "✅ HTTP Status: 200 OK" -ForegroundColor Green
    } else {
        Write-Host "❌ HTTP Status: Not 200" -ForegroundColor Red
    }
    
    if ($headers -match "text/plain") {
        Write-Host "✅ Content-Type: text/plain" -ForegroundColor Green  
    } else {
        Write-Host "⚠️  Content-Type: Not text/plain (might be HTML error)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "❌ Headers: Error checking" -ForegroundColor Red
}

Write-Host "`n🎯 Summary:" -ForegroundColor Cyan
Write-Host "If both endpoints show JoomlaBoost content, installation was successful!" -ForegroundColor White
Write-Host "If still showing 404 or HTML errors, check plugin is enabled in admin panel." -ForegroundColor White
