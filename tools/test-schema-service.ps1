# Test Schema Service on staging
param(
    [string]$BaseUrl = "https://staging.offroadserbia.com"
)

Write-Host "Testing Schema.org service on $BaseUrl..." -ForegroundColor Yellow

function Test-SchemaJson($url, $testName) {
    Write-Host "`nTesting: $testName" -ForegroundColor Cyan
    Write-Host "URL: $url" -ForegroundColor Gray
    
    try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 30
        $content = $response.Content
        
        # Extract all JSON-LD scripts
        $pattern = '<script type="application/ld\+json">(.*?)</script>'
        $options = [System.Text.RegularExpressions.RegexOptions]::Singleline
        $jsonLdMatches = [regex]::Matches($content, $pattern, $options)
        
        if ($jsonLdMatches.Count -eq 0) {
            Write-Host "No Schema.org JSON-LD found" -ForegroundColor Red
            return $false
        }
        
        Write-Host "Found $($jsonLdMatches.Count) Schema.org JSON-LD block(s)" -ForegroundColor Green
        
        foreach ($match in $jsonLdMatches) {
            $jsonContent = $match.Groups[1].Value.Trim()
            try {
                $schema = $jsonContent | ConvertFrom-Json
                $type = $schema.'@type'
                Write-Host "  Schema Type: $type" -ForegroundColor White
                
                # Show relevant properties
                if ($schema.name) { Write-Host "      Name: $($schema.name)" -ForegroundColor Gray }
                if ($schema.headline) { Write-Host "      Headline: $($schema.headline)" -ForegroundColor Gray }
                if ($schema.description) { 
                    $desc = $schema.description.Substring(0, [Math]::Min(80, $schema.description.Length))
                    Write-Host "      Description: $desc..." -ForegroundColor Gray 
                }
                if ($schema.url) { Write-Host "      URL: $($schema.url)" -ForegroundColor Gray }
                
            } catch {
                Write-Host "  Invalid JSON in Schema block" -ForegroundColor Red
                Write-Host "  Raw: $($jsonContent.Substring(0, [Math]::Min(100, $jsonContent.Length)))..." -ForegroundColor DarkGray
            }
        }
        
        return $true
        
    } catch {
        Write-Host "Error testing ${testName}: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
}

# Test different page types
$tests = @(
    @{ url = "$BaseUrl/"; name = "Homepage (Website + Organization Schema)" },
    @{ url = "$BaseUrl/blog"; name = "Blog page (Blog Schema)" },
    @{ url = "$BaseUrl/kontakt"; name = "Contact page (Organization Schema)" }
)

# Try to find an article to test
Write-Host "`nLooking for content articles..." -ForegroundColor Yellow
try {
    $homepageResponse = Invoke-WebRequest -Uri "$BaseUrl/" -UseBasicParsing -TimeoutSec 30
    
    # Look for article links in homepage
    $pattern = 'href="([^"]*(?:blog|vijesti|clanci|articles)[^"]*)"'
    $options = [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
    $articleMatches = [regex]::Matches($homepageResponse.Content, $pattern, $options)
    
    if ($articleMatches.Count -gt 0) {
        $articlePath = $articleMatches[0].Groups[1].Value
        if (-not $articlePath.StartsWith("http")) {
            if ($articlePath.StartsWith("/")) {
                $articleUrl = "$BaseUrl$articlePath"
            } else {
                $articleUrl = "$BaseUrl/$articlePath"
            }
        } else {
            $articleUrl = $articlePath
        }
        
        $tests += @{ url = $articleUrl; name = "Article page (Article Schema)" }
        Write-Host "Found article URL: $articleUrl" -ForegroundColor Green
    } else {
        Write-Host "No article links found on homepage" -ForegroundColor Yellow
    }
} catch {
    Write-Host "Could not scan homepage for articles: $($_.Exception.Message)" -ForegroundColor Yellow
}

# Run all tests
$passedTests = 0
$totalTests = $tests.Count

foreach ($test in $tests) {
    if (Test-SchemaJson -url $test.url -testName $test.name) {
        $passedTests++
    }
    Start-Sleep -Milliseconds 500  # Be nice to the server
}

# Summary
Write-Host "`n" + "="*60 -ForegroundColor Cyan
Write-Host "SCHEMA SERVICE TEST SUMMARY" -ForegroundColor Cyan
Write-Host "="*60 -ForegroundColor Cyan
Write-Host "Passed: $passedTests / $totalTests tests" -ForegroundColor $(if ($passedTests -eq $totalTests) { 'Green' } else { 'Yellow' })

if ($passedTests -eq $totalTests) {
    Write-Host "`nAll Schema.org tests PASSED! Service is working correctly." -ForegroundColor Green
} elseif ($passedTests -gt 0) {
    Write-Host "`nSome Schema.org tests failed. Check configuration." -ForegroundColor Yellow
} else {
    Write-Host "`nAll Schema.org tests FAILED. Service may be disabled or misconfigured." -ForegroundColor Red
}

Write-Host "`nNext steps for Schema.org enhancement:" -ForegroundColor White
Write-Host "1. Basic Website + Organization schemas working" -ForegroundColor Gray
Write-Host "2. Test Article schema on content pages" -ForegroundColor Gray  
Write-Host "3. Add LocalBusiness schema for OffRoad Serbia" -ForegroundColor Gray
Write-Host "4. Enhance breadcrumb navigation" -ForegroundColor Gray
Write-Host "5. Add FAQ schema for support pages" -ForegroundColor Gray

# Run all tests
$passedTests = 0
$totalTests = $tests.Count

foreach ($test in $tests) {
    if (Test-SchemaJson -url $test.url -testName $test.name) {
        $passedTests++
    }
    Start-Sleep -Milliseconds 500  # Be nice to the server
}

# Summary
Write-Host "`n" + "="*60 -ForegroundColor Cyan
Write-Host "📊 SCHEMA SERVICE TEST SUMMARY" -ForegroundColor Cyan
Write-Host "="*60 -ForegroundColor Cyan
Write-Host "✅ Passed: $passedTests / $totalTests tests" -ForegroundColor $(if ($passedTests -eq $totalTests) { 'Green' } else { 'Yellow' })

if ($passedTests -eq $totalTests) {
    Write-Host "`n🎉 All Schema.org tests PASSED! Service is working correctly." -ForegroundColor Green
} elseif ($passedTests -gt 0) {
    Write-Host "`n⚠️  Some Schema.org tests failed. Check configuration." -ForegroundColor Yellow
} else {
    Write-Host "`n❌ All Schema.org tests FAILED. Service may be disabled or misconfigured." -ForegroundColor Red
}

Write-Host "`n📋 Next steps for Schema.org enhancement:" -ForegroundColor White
Write-Host "1. ✅ Basic Website + Organization schemas working" -ForegroundColor Gray
Write-Host "2. 🔧 Test Article schema on content pages" -ForegroundColor Gray  
Write-Host "3. 🔧 Add LocalBusiness schema for OffRoad Serbia" -ForegroundColor Gray
Write-Host "4. 🔧 Enhance breadcrumb navigation" -ForegroundColor Gray
Write-Host "5. 🔧 Add FAQ schema for support pages" -ForegroundColor Gray
