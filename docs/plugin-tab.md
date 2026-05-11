# Plugin Tab — Quick Setup, Domain & Robots.txt

The **Plugin** tab is the first tab you see when opening JoomlaBoost settings. It covers three areas: license management, quick setup presets, domain/environment detection, and robots.txt configuration.

---

## License

### License Key

**Field:** `license_key`

Enter your JoomlaBoost license key from Gumroad. Format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`.

After saving with a valid key, the field shows a **Licensed** badge alongside your plan tier (Starter, Developer, or Agency). An invalid or empty key shows the plugin in unlicensed mode — core features still work, but Developer/Agency features are locked.

> See [License Plans & Feature Gating](license-plans.md) for a full breakdown of what each tier includes.

---

## Quick Setup

### Site Type (Vertical Preset)

**Field:** `vertical_preset`

Choose the preset that best matches your website:

| Option | Best for |
|--------|----------|
| 🏨 Hotel / Accommodation | Hotels, guesthouses, serviced apartments, vacation rentals |
| 🍽️ Restaurant / Cafe | Restaurants, cafes, bars, food trucks, catering |
| 📰 Blog / Magazine | News sites, personal blogs, online magazines, content hubs |
| 🛍️ E-commerce / Online Shop | Online stores, product catalogs, marketplaces |
| 🎯 Generic Business / Corporate | Agencies, services, consulting firms, non-profits |

Each preset applies a bundle of recommended settings across all tabs — Schema type, FAQ detection mode, sitemap priorities, and more. See the [Vertical Presets Guide](vertical-presets.md) for details on exactly what each preset configures.

> After applying a preset, every individual setting can still be adjusted. The preset is a starting point, not a lock.

### Apply Preset on Save

**Field:** `vertical_preset_apply`

Set to **Yes** to apply the selected preset on the next save. After applying, this field automatically resets to **No** so the preset is not accidentally reapplied on every subsequent save.

**Workflow:**
1. Select your site type.
2. Set **Apply Preset on Save** to **Yes**.
3. Click **Save**.
4. Fine-tune individual settings as needed.

### Show Advanced Options

**Field:** `show_advanced_options`

**Default:** No

When set to **Yes** and saved, additional power-user fields are revealed across all tabs:

- Sitemap: priority per content type, update frequency, article exclusion, menu depth
- Schema.org: Manual FAQ scope, Events JSON per language
- Organization: Guest Ratings (AggregateRating)
- Analytics: Meta Pixel conversion events, Additional GSC verification HTML

> Toggle this setting to **No** to return to the simplified view without losing any data you have entered in the advanced fields.

---

## Domain & Environment

### Auto Domain Detection

**Field:** `auto_domain_detection`  
**Default:** Yes

When **Yes**, JoomlaBoost automatically detects your site's public domain from Joomla's `live_site` configuration and the current HTTP request. This is correct for the vast majority of sites.

### Manual Domain Override

**Field:** `manual_domain`  
**Visible when:** Auto Domain Detection = No

Enter your full domain URL if automatic detection picks up the wrong URL. This can happen with:
- Reverse-proxy or CDN setups where the server's internal address differs from the public URL
- Multi-domain hosting environments
- Joomla installations in a subdirectory

**Example:** `https://www.yourdomain.com`

---

## Robots.txt

JoomlaBoost serves a dynamic `robots.txt` that replaces the default Joomla static file. It is specifically crafted for the AI Search era, allowing all major AI crawlers while blocking admin and cache paths.

### Enable Dynamic Robots.txt

**Field:** `enable_robots`  
**Default:** Yes

When **Yes**, JoomlaBoost intercepts requests to `yoursite.com/robots.txt` and serves a dynamically generated file that includes:

- Standard Joomla path restrictions (`/administrator/`, `/cache/`, `/tmp/`, etc.)
- Explicit `User-agent` entries for 25+ AI crawlers with `Allow: /` directives, including: GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Bingbot, Yandex, FacebookBot, and more
- A `Sitemap:` declaration pointing to your XML sitemap

**Example output:**
```
User-agent: *
Disallow: /administrator/
Disallow: /cache/
Allow: /

User-agent: GPTBot
Allow: /

User-agent: ClaudeBot
Allow: /

Sitemap: https://yourdomain.com/sitemap.xml
```

> **Conflict with static file:** If a physical `robots.txt` file exists in your Joomla root directory, it takes precedence over the dynamic version. Delete or rename the static file to let JoomlaBoost take control.

### Auto-Sync Robots.txt File

**Field:** `robots_auto_sync`  
**Default:** Yes  
**Visible when:** Enable Robots.txt = Yes

When **Yes**, JoomlaBoost writes the robots.txt content to a physical `robots.txt` file on disk each time it is regenerated. This ensures your robots.txt remains correct even if a Joomla update overwrites it, and prevents conflicts with web server-level caching of the static file.

---

## Recommended Settings (Plugin Tab)

| Setting | Recommended value |
|---------|------------------|
| License Key | Paste your key from Gumroad |
| Site Type | Select your vertical |
| Apply Preset on Save | Yes (once, then resets automatically) |
| Show Advanced Options | No (Yes for experienced users only) |
| Auto Domain Detection | Yes |
| Enable Robots.txt | Yes |
| Auto-Sync Robots.txt | Yes |

---

*← Back to [Documentation Index](index.md)*  
*Next: [Organization Tab →](organization.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
