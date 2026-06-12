# Integrations

AI Boost detects popular third-party extensions and adapts its output so the two
never fight. Each integration is **detection-first**: AI Boost never edits another
extension's data or settings. Where an integration adds extra AI Boost output
(Falang, YOOtheme Pro), you can switch it on or off from
**AI Boost → Integrations**. Switching it off keeps all of your settings — it only
pauses that extra output, and a normal Settings save never erases the integration's
options.

How the status badges read:

| Badge | Meaning |
|-------|---------|
| ✅ AI Boost support active | The extension is installed and AI Boost is actively enhancing it. |
| ⏸ Paused — integration off | The extension is installed, but you switched this integration off here. No extra output until you switch it back on. |
| 🔍 Detected in Joomla | The extension is installed; dedicated support is not active yet. |
| 🚧 Add-on available soon | Dedicated support is planned as a separate add-on. |
| ⚪ Not installed | The extension is not installed on this site. |

---

## Falang Pro — Multilingual

**Vendor:** Falang · **Category:** Multilingual · master switch: **yes**

**What it does:** adds Falang-aware `hreflang` link tags to the page `<head>`,
translates Schema.org and OpenGraph per language, and lists translated URLs as
`hreflang` alternates in the XML sitemap.

**What turning it off changes:** AI Boost stops adding Falang hreflang, translated
schema and translated OpenGraph. Your Falang translations and every AI Boost setting
are kept — only this extra output pauses, and a normal Settings save will not erase
your `falang_*` options.

**Conflicts it resolves:** prevents AI Boost from emitting hreflang twice when Joomla
native multilingual and Falang are both present (the source is chosen by the
*Hreflang source mode* setting). Honours the *Translation source priority* setting so
translated content is read from one canonical source.

---

## YOOtheme Pro — Page Builder

**Vendor:** YOOtheme · **Category:** Page Builder · master switch: **yes** · **Pro feature**

**What it does:** reads YOOtheme Pro page content to build FAQ and image-gallery
Schema.org, and uses the YOOtheme page title and description for per-page meta and
OpenGraph.

**What turning it off changes:** AI Boost stops reading YOOtheme Pro page content for
schema and meta. Your YOOtheme settings are untouched — only this extra output pauses,
and a normal Settings save will not erase your YOOtheme options.

**Conflicts it resolves:** routes all YOOtheme-derived JSON-LD through AI Boost's single
consolidated head block (claiming the FAQ schema slot), so a YOOtheme page never ends
up with duplicate or competing structured data.

> YOOtheme enhancement runs only when an active AI Boost Pro licence is present. The
> switch and settings are visible on every install, but the output is Pro-gated.

---

## Admin Tools — Security (detection only)

**Vendor:** Akeeba · **Category:** Security · master switch: **no**

**What it does:** detection only. AI Boost never changes Admin Tools. When **both**
AI Boost and Admin Tools are set to manage `robots.txt`, AI Boost raises a Health
warning so you keep `robots.txt` editing in one tool — otherwise whichever tool writes
last overwrites the other.

**Conflicts it resolves:** the `robots.txt` ownership warning above. There is nothing to
switch on or off because AI Boost only watches, it does not bridge.
