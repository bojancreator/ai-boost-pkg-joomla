#!/bin/bash
# Quick Test Script for JoomlaBoost Plugin Post-Installation
# Usage: ./quick-test-joomlaboost.sh

echo "🧪 JoomlaBoost Quick Test Suite"
echo "=============================="
echo ""

STAGING_URL="https://staging.offroadserbia.com"

echo "🔍 Testing: robots.txt"
curl -s -w "Status: %{http_code} | Time: %{time_total}s\n" "$STAGING_URL/robots.txt" | head -5
echo ""

echo "🔍 Testing: sitemap.xml"  
curl -s -w "Status: %{http_code} | Time: %{time_total}s\n" "$STAGING_URL/sitemap.xml" | head -5
echo ""

echo "🔍 Testing: Homepage meta tags"
curl -s "$STAGING_URL/" | grep -i -E "(joomlaboost|og:|schema|google)" | head -10
echo ""

echo "✅ Quick test completed!"
echo "📋 Manual verification:"
echo "   - Visit: $STAGING_URL/robots.txt"
echo "   - Visit: $STAGING_URL/sitemap.xml" 
echo "   - Check HTML source for meta tags"
