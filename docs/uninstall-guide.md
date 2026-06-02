# Uninstalling AI Boost for Joomla

This page explains what happens when you remove AI Boost from your site,
how to back up your configuration first, and how to reinstall later
without losing data.

## Before you uninstall — export your settings

Uninstalling **permanently deletes all AI Boost data** from the database.
Open the admin and export first so you can restore everything on a future
install or on a different site:

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
- All AI Boost database tables:
  - `#__aiboost_settings` (every plugin option)
  - `#__aiboost_translations` (per-language field overrides)
  - `#__aiboost_redirects` (your redirect list and hit counters)
  - `#__aiboost_url_scans` (URL Checker history)
  - `#__aiboost_404_log` (logged 404 hits)
- The article custom fields group **AI Boost — OpenGraph** and the six
  `aiboost_og_*` custom fields (per-article OG overrides stored on
  articles are also dropped — see "What stays" below for one exception).
- Generated files in the site root that AI Boost authored:
  - `robots.txt` — only when it still starts with the
    `# Managed by AI Boost for Joomla` header marker. A hand-edited
    `robots.txt` is **never** touched.
  - `llms.txt`
  - `sitemap.xml` / `sitemap-index.xml` (only when produced by AI Boost).

## What stays on the site

- Your Joomla configuration, articles, menus, modules, templates — none
  of this is touched.
- Any `robots.txt` you wrote yourself (without the AI Boost header).
- The Pro Upgrade package (`pkg_aiboost_pro`), if it is installed, stays
  until you uninstall it separately from
  **Extensions → Manage → Manage**.
- Joomla's built-in `#__menu` row for **Components → AI Boost** is
  removed by the standard component uninstaller along with the component.

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

- **"Could not drop table" warning during uninstall.** Joomla shows the
  exact table name in the warning. Open phpMyAdmin (or your DB tool),
  run `DROP TABLE IF EXISTS jos_aiboost_<name>;` (replace `jos_` with
  your prefix) and you are done.
- **Old `robots.txt` is still being served.** That means the file did
  not carry the AI Boost header marker, so the uninstaller left it
  untouched on purpose. Delete or edit it manually.
- **Old `#__menu` row for AI Boost is still there.** Joomla cleans this
  up as part of component uninstall — if a row is left behind, run
  **System → Clear Cache** and then refresh the Components menu.
