---
name: Sitemap XML / admin-JSON output leak from third-party plugins
description: Why a clean sitemap/JSON response can still be corrupted by another plugin, and the scoped fix that works.
---

# Third-party output leaking into sitemap XML / admin JSON

A machine-readable response (frontend `sitemap.xml`, admin `settings.previewSitemap`
JSON) can be corrupted by HTML emitted **outside our own code path**:

1. **Admin AJAX JSON** — any PHP `warning/deprecation` printed before `json_encode`
   produces `Unexpected token '<'` in the browser. Fix: before echoing JSON, drain
   *all* buffers in a loop (`while (ob_get_level()>0) ob_end_clean()`) then echo +
   close the app. A single `ob_end_clean()` is not enough — there can be nested buffers.

2. **Frontend sitemap XML** — a different **system plugin** (here Falang's
   `falangdriver`, incompatible with PHP 8.5, `setAccessible()` deprecation) can emit
   its notice from **its own `onAfterInitialise` handler, which may fire BEFORE ours**.
   Once those bytes flush, headers are already sent (response becomes `text/html`) and
   our later output buffering / `discardStrayOutput()` can no longer remove them.

**Why buffering alone fails:** plugin event order is not guaranteed; a lower-`ordering`
plugin runs first. You cannot un-send bytes another plugin already flushed in an
earlier event.

**The fix that works:** suppress error *display* as early as the plugin **import /
construction** phase (all system plugins are instantiated during `importPlugin` before
any `onAfterInitialise` dispatch). Use a **variadic constructor**
(`__construct(...$args){ parent::__construct(...$args); }`) to stay compatible with the
CMSPlugin signature across Joomla 4/5/6, and inside it call `@ini_set('display_errors','0')`
**only when the current request is a sitemap URL** (basename check on
`$_SERVER['REQUEST_URI']`, routing-independent). Scoped to URLs we fully own and
terminate → no site-wide error-visibility side effects.

**Confirmation signal:** a successful fix flips the sitemap response `Content-Type`
from `text/html` back to `application/xml` — proof the deprecation is no longer
flushed before our `header()`/`sendXml()` calls.

**Also remember:** language-neutral items must not emit `hreflang="*"` (return ''
for language `''`/`'*'`); and dedup `<loc>` (homepage + a menu item routing to `/`
collide) keeping first occurrence, or you ship invalid sitemap markup.
