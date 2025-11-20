#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Validate OpenGraph and Twitter Card meta tags for social media sharing
.DESCRIPTION
    Tests staging site for proper meta tag implementation after v0.1.38 deployment
    Validates Facebook, Twitter, and LinkedIn requirements
.PARAMETER Url
    Base URL to test (default: https://staging.offroadserbia.com)
.EXAMPLE
    .\tools\validate-social-meta.ps1
    .\tools\validate-social-meta.ps1 -Url "https://production.example.com"
#>

param(
    [string]$Url = "https://staging.offroadserbia.com"
)

$ErrorActionPreference = "Stop"

Write-Host "`n🔍 JoomlaBoost Social Media Meta Tag Validator" -ForegroundColor Cyan
Write-Host "=" * 60 -ForegroundColor Cyan
Write-Host "Testing URL: $Url" -ForegroundColor Yellow
Write-Host "Timestamp: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')`n" -ForegroundColor Gray

# Test pages
$testPages = @(
    @{ Path = "/"; Name = "Homepage" }
    @{ Path = "/our-story"; Name = "Static Page" }
    @{ Path = "/our-adventures"; Name = "Article Page" }
)

$allPassed = $true
$totalTests = 0
$passedTests = 0

function Test-MetaTag {
    param($Html, $Property, $Name, $Required = $true)

    $global:totalTests++

    # Try property first (OpenGraph)
    $pattern = "<meta\s+property=`"$Property`"\s+content=`"([^`"]*)`""
    if ($Html -match $pattern) {
        $content = $Matches[1]
        if ($content) {
            $global:passedTests++
            Write-Host "  ✅ $Name" -ForegroundColor Green -NoNewline
            $displayContent = if ($content.Length -gt 60) {
                $content.Substring(0, 57) + "..."
            } else {
                $content
            }
            Write-Host " = `"$displayContent`"" -ForegroundColor Gray
            return $content
        }
    }

    # Try name attribute (Twitter)
    $pattern = "<meta\s+name=`"$Property`"\s+content=`"([^`"]*)`""
    if ($Html -match $pattern) {
        $content = $Matches[1]
        if ($content) {
            $global:passedTests++
            Write-Host "  ✅ $Name" -ForegroundColor Green -NoNewline
            $displayContent = if ($content.Length -gt 60) {
                $content.Substring(0, 57) + "..."
            } else {
                $content
            }
            Write-Host " = `"$displayContent`"" -ForegroundColor Gray
            return $content
        }
    }

    if ($Required) {
        $global:allPassed = $false
        Write-Host "  ❌ $Name - MISSING" -ForegroundColor Red
    } else {
        Write-Host "  ⚠️  $Name - Optional (not found)" -ForegroundColor Yellow
    }

    return $null
}

function Test-ImageUrl {
    param($ImageUrl)

    if (-not $ImageUrl) {
        return $false
    }

    # Check if absolute URL
    if (-not ($ImageUrl -match "^https?://")) {
        Write-Host "    ❌ Image URL is relative (should be absolute)" -ForegroundColor Red
        return $false
    }

    # Check for Joomla fragments
    if ($ImageUrl -match "#joomlaImage://") {
        Write-Host "    ❌ Contains Joomla fragments (#joomlaImage://)" -ForegroundColor Red
        return $false
    }

    # Check for unnecessary query params
    if ($ImageUrl -match "\?width=|\?height=") {
        Write-Host "    ⚠️  Contains query parameters (may confuse validators)" -ForegroundColor Yellow
    }

    Write-Host "    ✅ Image URL format valid (absolute, no fragments)" -ForegroundColor Green
    return $true
}

# Test each page
foreach ($page in $testPages) {
    $fullUrl = "$Url$($page.Path)"

    Write-Host "`n📄 Testing: $($page.Name)" -ForegroundColor Cyan
    Write-Host "   URL: $fullUrl" -ForegroundColor Gray
    Write-Host "-" * 60 -ForegroundColor DarkGray

    try {
        $response = Invoke-WebRequest -Uri $fullUrl -UseBasicParsing -TimeoutSec 30
        $html = $response.Content

        # OpenGraph Required Tags
        Write-Host "`n  📘 OpenGraph Tags (Facebook/LinkedIn):" -ForegroundColor Yellow
        $ogTitle = Test-MetaTag -Html $html -Property "og:title" -Name "og:title" -Required $true
        $ogType = Test-MetaTag -Html $html -Property "og:type" -Name "og:type" -Required $true
        $ogUrl = Test-MetaTag -Html $html -Property "og:url" -Name "og:url" -Required $true
        $ogImage = Test-MetaTag -Html $html -Property "og:image" -Name "og:image" -Required $true
        $ogDescription = Test-MetaTag -Html $html -Property "og:description" -Name "og:description" -Required $true
        $ogSiteName = Test-MetaTag -Html $html -Property "og:site_name" -Name "og:site_name" -Required $false

        # Validate og:image
        if ($ogImage) {
            $imageValid = Test-ImageUrl -ImageUrl $ogImage
            if (-not $imageValid) {
                $global:allPassed = $false
            }
        }

        # Twitter Card Tags
        Write-Host "`n  🐦 Twitter Card Tags:" -ForegroundColor Yellow
        $twitterCard = Test-MetaTag -Html $html -Property "twitter:card" -Name "twitter:card" -Required $true
        $twitterSite = Test-MetaTag -Html $html -Property "twitter:site" -Name "twitter:site" -Required $false
        $twitterTitle = Test-MetaTag -Html $html -Property "twitter:title" -Name "twitter:title" -Required $false
        $twitterDescription = Test-MetaTag -Html $html -Property "twitter:description" -Name "twitter:description" -Required $false
        $twitterImage = Test-MetaTag -Html $html -Property "twitter:image" -Name "twitter:image" -Required $false

        # Article-specific tags (if og:type is article)
        if ($ogType -eq "article") {
            Write-Host "`n  📰 Article-Specific Tags:" -ForegroundColor Yellow
            Test-MetaTag -Html $html -Property "article:published_time" -Name "article:published_time" -Required $false
            Test-MetaTag -Html $html -Property "article:modified_time" -Name "article:modified_time" -Required $false
        }

    } catch {
        Write-Host "  ❌ ERROR: Failed to fetch page - $($_.Exception.Message)" -ForegroundColor Red
        $global:allPassed = $false
    }
}

# Summary
Write-Host "`n" + ("=" * 60) -ForegroundColor Cyan
Write-Host "📊 VALIDATION SUMMARY" -ForegroundColor Cyan
Write-Host ("=" * 60) -ForegroundColor Cyan

$passRate = if ($totalTests -gt 0) {
    [math]::Round(($passedTests / $totalTests) * 100, 1)
} else {
    0
}

Write-Host "`nTests: $passedTests / $totalTests passed ($passRate%)" -ForegroundColor $(if ($passRate -ge 90) { "Green" } elseif ($passRate -ge 70) { "Yellow" } else { "Red" })

if ($allPassed -and $passRate -eq 100) {
    Write-Host "`n✅ ALL VALIDATIONS PASSED!" -ForegroundColor Green
    Write-Host "   Social media sharing should work correctly." -ForegroundColor Green
    Write-Host "`n📋 Next Steps:" -ForegroundColor Cyan
    Write-Host "   1. Test in Facebook Sharing Debugger:" -ForegroundColor Gray
    Write-Host "      https://developers.facebook.com/tools/debug/" -ForegroundColor Blue
    Write-Host "   2. Test in Twitter Card Validator:" -ForegroundColor Gray
    Write-Host "      https://cards-dev.twitter.com/validator" -ForegroundColor Blue
    Write-Host "   3. Test in LinkedIn Post Inspector:" -ForegroundColor Gray
    Write-Host "      https://www.linkedin.com/post-inspector/" -ForegroundColor Blue
    exit 0
} else {
    Write-Host "`n❌ VALIDATION FAILED" -ForegroundColor Red
    Write-Host "   Some meta tags are missing or invalid." -ForegroundColor Red
    Write-Host "`n🔧 Troubleshooting:" -ForegroundColor Yellow
    Write-Host "   1. Verify plugin is enabled and configured" -ForegroundColor Gray
    Write-Host "   2. Check plugin version (should be v0.1.38+)" -ForegroundColor Gray
    Write-Host "   3. Clear Joomla cache" -ForegroundColor Gray
    Write-Host "   4. Review: docs/SOCIAL-MEDIA-VALIDATION.md" -ForegroundColor Gray
    exit 1
}
