# Social Media Validation Guide

## Testing OpenGraph Meta Tags

After deploying v0.1.38+, validate OpenGraph implementation using official social media tools.

### 📘 Facebook Sharing Debugger
**URL**: https://developers.facebook.com/tools/debug/

**How to Use**:
1. Paste your page URL (e.g., `https://staging.offroadserbia.com/`)
2. Click "Debug" button
3. Check for errors in "Open Graph Object Debugger" section
4. Verify image preview appears correctly
5. Click "Scrape Again" if you updated meta tags

**Required Tags**:
- ✅ `og:title` - Page/article title
- ✅ `og:type` - "website" or "article"
- ✅ `og:url` - Canonical URL
- ✅ `og:image` - **MUST be absolute URL** (https://...)
- ✅ `og:description` - Page description

**Common Errors**:
- ❌ "Could not scrape URL" → Check og:image is absolute URL
- ❌ "Image too small" → Minimum 200x200px, recommended 1200x630px
- ❌ "Invalid URL" → Ensure no Joomla fragments (#joomlaImage://)

---

### 🐦 Twitter Card Validator
**URL**: https://cards-dev.twitter.com/validator

**How to Use**:
1. Paste your page URL
2. Click "Preview card" button
3. Verify card type (should be "summary_large_image")
4. Check image, title, description display correctly

**Required Tags**:
- ✅ `twitter:card` - "summary_large_image"
- ✅ `twitter:site` - @YourTwitterHandle (optional)
- ✅ `twitter:title` - Falls back to og:title
- ✅ `twitter:description` - Falls back to og:description
- ✅ `twitter:image` - Falls back to og:image

**Common Errors**:
- ❌ "Unable to render Card preview" → Check og:image URL format
- ❌ Image not showing → Verify image is publicly accessible
- ❌ Wrong card type → Plugin generates "summary_large_image" by default

---

### 💼 LinkedIn Post Inspector
**URL**: https://www.linkedin.com/post-inspector/

**How to Use**:
1. Paste your page URL
2. Click "Inspect" button
3. Verify title, description, and image preview
4. LinkedIn uses OpenGraph tags primarily

**Required Tags**:
- ✅ `og:title`
- ✅ `og:description`
- ✅ `og:image` - Minimum 1200x627px for best results
- ✅ `og:url`

---

## JoomlaBoost v0.1.38+ Image Handling

### Image URL Normalization
Plugin automatically converts relative URLs to absolute URLs and removes Joomla fragments:

**Before v0.1.38** (BROKEN):
```html
<meta property="og:image" content="images/LOGO-SERBIA-CREW.png#joomlaImage://local-images/LOGO-SERBIA-CREW.png?width=807&height=835" />
```

**After v0.1.38** (FIXED):
```html
<meta property="og:image" content="https://staging.offroadserbia.com/images/LOGO-SERBIA-CREW.png" />
```

### Image Priority Hierarchy
1. **Custom Field** (`custom_og_image`) - Per-article override
2. **Featured Image** - Article intro/fulltext image from Joomla
3. **Global Config** - Plugin setting `og_image` or `org_logo`

All three levels use `normalizeAndCleanImageUrl()` to ensure validator compatibility.

---

## Testing Checklist

After installing/updating plugin:

- [ ] Test homepage in Facebook Debugger
- [ ] Test article page in Facebook Debugger
- [ ] Test in Twitter Card Validator
- [ ] Test in LinkedIn Post Inspector
- [ ] Verify og:image shows as absolute URL in page source
- [ ] Verify no Joomla fragments (#joomlaImage://) in meta tags
- [ ] Check image preview renders correctly in all validators
- [ ] Test custom og_image field override (if using Custom Fields)

---

## Troubleshooting

### Problem: Facebook shows "Could not scrape URL"
**Solution**: 
1. View page source (Ctrl+U)
2. Search for `og:image`
3. Verify it's absolute URL starting with `https://`
4. If relative, upgrade to v0.1.38+

### Problem: Image has Joomla fragments
**Example**: `images/logo.png#joomlaImage://local-images/logo.png?width=800`

**Solution**: 
- Upgrade to v0.1.38+ which includes `normalizeAndCleanImageUrl()`
- Clear Joomla cache
- Click "Scrape Again" in Facebook Debugger

### Problem: Twitter card not showing
**Check**:
1. Verify `twitter:card` meta tag exists (should be "summary_large_image")
2. Verify og:image is absolute URL
3. Test in Twitter Card Validator (not deprecated validator)
4. Ensure image is publicly accessible (not behind login)

---

## Image Recommendations

### Optimal Sizes
- **Facebook**: 1200x630px (1.91:1 aspect ratio)
- **Twitter**: 1200x675px (16:9 aspect ratio)
- **LinkedIn**: 1200x627px (1.91:1 aspect ratio)

### General Guidelines
- Minimum: 200x200px
- Maximum: 8MB file size
- Formats: JPG, PNG (avoid GIF for preview)
- Avoid text-heavy images (hard to read in small previews)

---

## Plugin Configuration

### Global OpenGraph Settings
Navigate to: **Extensions → Plugins → System - JoomlaBoost**

1. **Enable OpenGraph**: Yes
2. **OG Site Name**: Your site name (e.g., "OffRoad Serbia")
3. **OG Default Image**: Upload fallback image (1200x630px recommended)
4. **Organization Logo**: Used if OG Image not set

### Per-Article Override (v0.1.37+)
Create Custom Fields in **Content → Fields**:

1. **Field Name**: `custom_og_image`
   - Type: Media
   - Label: "Custom OG Image"
   - Context: Articles

2. **Field Name**: `custom_og_title`
   - Type: Text
   - Label: "Custom OG Title"
   - Context: Articles

3. **Field Name**: `custom_og_description`
   - Type: Textarea
   - Label: "Custom OG Description"
   - Context: Articles

Then edit article → Custom Fields tab → Fill values → Meta tags updated automatically.

---

## Validation API Limits

### Facebook Sharing Debugger
- No explicit rate limit for manual testing
- Excessive automated requests may trigger temporary blocks

### Twitter Card Validator
- No documented rate limit
- Use for testing, not automated monitoring

### LinkedIn Post Inspector
- Rate limit: ~50 requests per hour per user
- Clear cache if hitting limits

---

## Version History

- **v0.1.38**: Fixed OpenGraph image normalization + Joomla fragment removal
- **v0.1.37**: Added Custom Fields support for per-article overrides
- **v0.1.36**: Added multilingual breadcrumb support
- **v0.1.35**: Enhanced FAQ schema detection
- **v0.1.34**: Config-driven schema type selection
- **v0.1.33**: Logo injection fix

---

## Support Resources

- **Plugin Documentation**: `/docs/AI-OVERVIEW.md`
- **Troubleshooting**: `/docs/TROUBLESHOOTING.md`
- **Endpoints Testing**: `/docs/ENDPOINTS.md`
- **GitHub Issues**: https://github.com/bojancreator/JoomlaBoost/issues

---

**Last Updated**: November 20, 2025 (v0.1.38)
