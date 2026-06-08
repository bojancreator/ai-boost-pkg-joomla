# Uninstalling AI Boost for Joomla

This page explains what happens when you remove AI Boost from your site,
how to back up your configuration first, and how to reinstall later
without losing data.

## Before you uninstall — export your settings

Uninstalling **preserves your data** — every AI Boost table is left in the
database, so a later reinstall on the **same** site restores all settings,
redirects, translations and logs automatically. Only your Pro **licence keys**
are cleared on uninstall.

Even so, export first whenever you plan to **move to another site** or simply
want a portable backup you control:

1. Go to **Components → AI Boost → Import / Export**.
2. Click **Download settings (JSON)**. Save the file somewhere safe.
3. (Recommended) Take a fresh full database backup with your usual tool
   (Akeeba Backup, hosting panel, `mysqldump`).

The exported JSON contains every plugin option, every redirect, and every
stored translation. It can be re-imported into AI Boost on the same site
or on a fresh install through the same Import / Export screen.

## What gets removed on uninstall

When you uninstall the **pkg_aiboost** package from
**Extensions → Manage → Manage**, AI Boost removes:

- The admin component `com_aiboost` and its 7 system plugins
  (Schema, Sitemap, Social, Analytics, AEO, Core, Code).
- Your Pro **licence keys only** — the six licence/dev keys are cleared from
  the `main` row of `#__aiboost_settings` so a reinstall comes up unlicensed.
  **Every other setting in that table is kept.**
- The article custom fields group **AI Boost — OpenGraph** and the six
  `aiboost_og_*` custom fields, together with the per-article OG values
  stored against them.
- Generated files in the site root that AI Boost authored:
  - `robots.txt` — only the fenced **AI Boost managed block** is stripped;
    the file is deleted outright only when it was entirely ours. A hand-edited
    `robots.txt` is **never** removed.
  - `llms.txt`
  - `sitemap.xml` / `sitemap-index.xml` (only when produced by AI Boost,
    identified by our marker).

## What stays on the site

- **All AI Boost data tables are preserved — nothing is dropped:**
  - `#__aiboost_settings` (every plugin option except the cleared licence keys)
  - `#__aiboost_translations` (per-language field overrides)
  - `#__aiboost_redirects` (your redirect list and hit counters)
  - `#__aiboost_url_scans` (URL Checker history)
  - `#__aiboost_404_log` / `#__aiboost_error_log` (logged 404 hits and errors)

  This is deliberate: reinstalling AI Boost on the same site brings back every
  setting, redirect, translation and log automatically. To remove this data on
  purpose, see **Fully removing all data** below.
- Your Joomla configuration, articles, menus, modules, templates — none
  of this is touched.
- Any `robots.txt` you wrote yourself (outside the AI Boost managed block).
- The Pro Upgrade package (`pkg_aiboost_pro`), if it is installed, stays
  until you uninstall it separately from
  **Extensions → Manage → Manage**.
- Joomla's built-in `#__menu` row for **Components → AI Boost** is
  removed by the standard component uninstaller along with the component.

## Fully removing all data

AI Boost keeps your tables on uninstall so reinstalls are lossless. If you want
to wipe everything (for example before retiring a site), drop the tables
manually after uninstalling. In phpMyAdmin or your DB tool, replace `jos_` with
your real table prefix and run:

```sql
DROP TABLE IF EXISTS jos_aiboost_settings;
DROP TABLE IF EXISTS jos_aiboost_translations;
DROP TABLE IF EXISTS jos_aiboost_redirects;
DROP TABLE IF EXISTS jos_aiboost_url_scans;
DROP TABLE IF EXISTS jos_aiboost_404_log;
DROP TABLE IF EXISTS jos_aiboost_error_log;
```

## Recommended order (mixed Free + Pro installs)

1. **Components → AI Boost → Import / Export** → download settings JSON.
2. **Extensions → Manage → Manage** → search for
   `AI Boost for Joomla — Pro Upgrade` → uninstall.
3. **Extensions → Manage → Manage** → search for
   `AI Boost for Joomla` → uninstall.
4. (Optional) Refresh the site frontend. AI Boost meta tags, JSON-LD
   blocks, and generated artifacts should now be gone.

## Reinstalling later without losing data

1. Install the new `pkg_aiboost-x.y.z.zip` through
   **Extensions → Install**.
2. (Pro users) Install `pkg_aiboost_pro-x.y.z.zip` on top.
3. Open **Components → AI Boost → Import / Export**.
4. Click **Upload settings (JSON)** and pick the file you exported before
   uninstalling. All settings, redirects and translations are restored.
5. (Pro users) Re-enter and verify your Pro license key on
   **Components → AI Boost → Licenses**.

## Troubleshooting

- **"Could not drop table" notice during install or update.** This refers to
  a one-time migration of the legacy translations table — not to uninstall,
  which never drops your tables. Joomla shows the exact table name; open
  phpMyAdmin (or your DB tool), run `DROP TABLE IF EXISTS jos_<name>;`
  (replace `jos_` with your prefix) and you are done.
- **I want my data gone after uninstalling.** Uninstall keeps your tables on
  purpose. See **Fully removing all data** above for the exact `DROP TABLE`
  statements.
- **Old `robots.txt` is still being served.** That means the file did
  not carry the AI Boost header marker, so the uninstaller left it
  untouched on purpose. Delete or edit it manually.
- **Old `#__menu` row for AI Boost is still there.** Joomla cleans this
  up as part of component uninstall — if a row is left behind, run
  **System → Clear Cache** and then refresh the Components menu.
