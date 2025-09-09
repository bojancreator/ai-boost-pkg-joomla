# JoomlaBoost v0.1.18 - LocalBusiness Schema Enhancement

## 🆕 What's New in v0.1.18

### ✅ Enhanced Schema.org Support

- **LocalBusiness Schema**: Automatic detection and generation of LocalBusiness schema for OffRoad Serbia sites
- **Geographic Information**: Added coordinates and area served for better local SEO
- **Service Types**: Includes specific off-road and adventure service types
- **Social Media Integration**: Automatically includes social media profiles when detected
- **Backward Compatibility**: Standard Organization schema for non-LocalBusiness sites

## 📦 Installation Instructions

### Step 1: Download the Plugin

- **File**: `joomlaboost-0.1.18.zip` (51KB)
- **Location**: `build/joomlaboost-0.1.18.zip`

### Step 2: Install in Joomla

1. Login to **staging.offroadserbia.com/administrator**
2. Navigate to **Extensions > Manage > Install**
3. **Upload Package File**: Select `joomlaboost-0.1.18.zip`
4. Click **Upload & Install**
5. ✅ Success message should appear

### Step 3: Enable/Configure Plugin

1. Go to **Extensions > Plugins**
2. Search for **"JoomlaBoost"**
3. **Enable** the plugin if not already enabled
4. Click **JoomlaBoost** to open configuration
5. Verify **Schema.org Support** is **Enabled**

## 🧪 Testing LocalBusiness Schema

### Quick Test Commands

```bash
# Test Schema.org output
php tools/test-schema-simple.php

# Expected output should show:
# 📋 Type: LocalBusiness
# 🏢 LocalBusiness detected!
# 📍 Address: Serbia, RS
# 🔧 Services: Off-road tours, 4x4 adventures, Nature experiences, Outdoor activities
# 🌍 Coordinates: 44.0165, 21.0059
```

### Browser Verification

1. Open **https://staging.offroadserbia.com/**
2. **View Page Source** (Ctrl+U)
3. Search for **"LocalBusiness"** (Ctrl+F)
4. Should find JSON-LD script with LocalBusiness schema

### Google Rich Results Test

1. Go to **https://search.google.com/test/rich-results**
2. Enter **https://staging.offroadserbia.com/**
3. Click **TEST URL**
4. Should detect **LocalBusiness** structured data

## 🎯 LocalBusiness Schema Benefits

### 🔍 SEO Improvements

- **Local Search Visibility**: Better ranking in local search results
- **Rich Snippets**: Enhanced search result appearance with business info
- **Google My Business Integration**: Improved connection with GMB listings

### 📊 Schema.org Properties Added

- **Business Type**: LocalBusiness with adventure/outdoor focus
- **Geographic Data**: Coordinates and service area (Serbia)
- **Contact Information**: Customer service contact points
- **Services**: Off-road tours, 4x4 adventures, nature experiences
- **Operating Hours**: Standard business hours (9 AM - 6 PM)
- **Social Profiles**: Facebook, Instagram, YouTube links

## 🚀 Next Steps

After installing v0.1.18:

1. **✅ Verify Installation**: Test Schema.org output
2. **🔧 Configure Services**: Update service descriptions if needed
3. **📱 Verify Social Links**: Check social media profile URLs
4. **🧪 Test Article Schema**: Test on blog posts/content pages
5. **📊 Monitor Results**: Check Google Search Console for improvements

## 📋 Development Progress

- **✅ Phase 1**: Basic plugin functionality (robots.txt, sitemap.xml)
- **✅ Phase 2A**: Schema.org Website + Organization schemas
- **🔄 Phase 2B**: Enhanced LocalBusiness schema (Current)
- **📅 Next**: Article schema testing and OpenGraph optimization

---

**Installation File**: `build/joomlaboost-0.1.18.zip`  
**Version**: 0.1.18  
**Size**: 51KB  
**Compatibility**: Joomla 4.0+
