# Quick Test Script for JoomlaBoost Plugin Post-Installation
# Usage: .\tools\quick-test-joomlaboost.ps1

Write-Host "🧪 JoomlaBoost Quick Test Suite" -ForegroundColor Green
Write-Host "==============================" -ForegroundColor Green
Write-Host ""

$stagingUrl = "https://staging.offroadserbia.com"

Write-Host "🔍 Testing: robots.txt" -ForegroundColor Yellow
try {
    $robotsResponse = Invoke-WebRequest -Uri "$stagingUrl/robots.txt" -UseBasicParsing -TimeoutSec 10
    Write-Host "   Status: $($robotsResponse.StatusCode) | Length: $($robotsResponse.Content.Length) bytes" -ForegroundColor Green
    $content = $robotsResponse.Content
    if ($content -match "JoomlaBoost|STAGING|Generated") {
        Write-Host "   ✅ JoomlaBoost signature detected!" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  No JoomlaBoost signature found" -ForegroundColor Yellow
    }
    Write-Host "   Content preview:" -ForegroundColor Cyan
    ($content -split "`n")[0..3] | ForEach-Object { Write-Host "     $_" -ForegroundColor Gray }
} catch {
    Write-Host "   ❌ Failed: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

Write-Host "🔍 Testing: sitemap.xml" -ForegroundColor Yellow
try {
    $sitemapResponse = Invoke-WebRequest -Uri "$stagingUrl/sitemap.xml" -UseBasicParsing -TimeoutSec 10
    Write-Host "   Status: $($sitemapResponse.StatusCode) | Length: $($sitemapResponse.Content.Length) bytes" -ForegroundColor Green
    $content = $sitemapResponse.Content
    if ($content -match "JoomlaBoost|STAGING|Generated") {
        Write-Host "   ✅ JoomlaBoost signature detected!" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  No JoomlaBoost signature found" -ForegroundColor Yellow
    }
    if ($content -match "<urlset|<url>") {
        Write-Host "   ✅ Valid XML sitemap structure!" -ForegroundColor Green
    }
    Write-Host "   Content preview:" -ForegroundColor Cyan
    ($content -split "`n")[0..5] | ForEach-Object { Write-Host "     $_" -ForegroundColor Gray }
} catch {
    Write-Host "   ❌ Failed: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

Write-Host "🔍 Testing: Homepage meta tags" -ForegroundColor Yellow
try {
    $homepageResponse = Invoke-WebRequest -Uri "$stagingUrl/" -UseBasicParsing -TimeoutSec 10
    Write-Host "   Status: $($homepageResponse.StatusCode) | Length: $($homepageResponse.Content.Length) bytes" -ForegroundColor Green
    $content = $homepageResponse.Content
    
    # Check for various meta tags
    $checks = @{
        "OpenGraph" = $content -match 'property="og:'
        "Schema.org" = $content -match 'application/ld\+json'
        "Google Verification" = $content -match 'google-site-verification'
        "JoomlaBoost Debug" = $content -match 'JoomlaBoost'
    }
    
    foreach ($check in $checks.GetEnumerator()) {
        $status = if ($check.Value) { "✅" } else { "❌" }
        Write-Host "   $status $($check.Key)" -ForegroundColor $(if ($check.Value) { "Green" } else { "Red" })
    }
    
    # Extract and display some meta tags
    $metaTags = [regex]::Matches($content, '<meta[^>]+>', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if ($metaTags.Count -gt 0) {
        Write-Host "   Meta tags found: $($metaTags.Count)" -ForegroundColor Cyan
        $metaTags[0..2] | ForEach-Object { 
            Write-Host "     $($_.Value)" -ForegroundColor Gray 
        }
    }
} catch {
    Write-Host "   ❌ Failed: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

Write-Host "✅ Quick test completed!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Manual verification:" -ForegroundColor Yellow
Write-Host "   - Visit: $stagingUrl/robots.txt" -ForegroundColor White
Write-Host "   - Visit: $stagingUrl/sitemap.xml" -ForegroundColor White  
Write-Host "   - Check HTML source for meta tags" -ForegroundColor White
Write-Host "   - Admin: Extensions > Plugins > System - JoomlaBoost" -ForegroundColor White
Write-Host ""

Write-Host "🎯 Installation Notes:" -ForegroundColor Cyan
Write-Host "   1. Install: build/joomlaboost-0.1.17.zip" -ForegroundColor White
Write-Host "   2. Enable plugin in System Plugins" -ForegroundColor White
Write-Host "   3. Configure basic settings (robots, sitemap, debug)" -ForegroundColor White
Write-Host "   4. Run this script again to verify" -ForegroundColor White
