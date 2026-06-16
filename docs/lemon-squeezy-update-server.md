# Licensed auto-updates (Pro + integrations) — design & handoff

**Goal:** deliver Pro and integration updates the same way Free already works —
Joomla shows a native "Update available" notice and one-click installs — but gated
by the customer's Lemon Squeezy license key, served from `aiboostnow.com`.

This replaces the current manual Pro channel (Lemon Squeezy "My Orders" portal +
e-mail). Free already uses a static update XML and is unchanged.

There are two halves: **C1 — the plugin** (this repo) and **C2 — the website**
(`bojancreator/aiboostnow`, a separate repo). C2 must exist before C1's update
servers are pointed live, otherwise installed Pro sites get failed update-site
fetches. Sequence: build C2 → point C1 manifests at it → ship.

---

## How Joomla licensed updates work (the `dlid` "Download Key")

Joomla's extension updater supports a per-site **download key** (a.k.a. extra
query) declared in the package manifest:

```xml
<updateservers>
    <server type="extension" priority="1" name="AI Boost for Joomla (Pro)">https://aiboostnow.com/updates/pro/pkg_aiboost_pro.xml</server>
</updateservers>
<dlid prefix="dlid=" suffix=""/>
```

With `<dlid>` present, Joomla shows a **Download Key** field on the update site
(System → Update Sites). Whatever the customer enters is appended as a query string
to BOTH the update-XML fetch and the `<downloadurl>` from that XML. So our server
receives the license key on every check and every download, and can validate it.

We use the **Lemon Squeezy license key** as the download key. To avoid the customer
typing it twice, the plugin auto-fills it (see C1.4).

---

## C1 — Plugin side (this repo)

1. **Add update servers + `<dlid>`** to the Pro and integration package manifests:
   - `component/package/pkg_aiboost_pro.xml` → `https://aiboostnow.com/updates/pro/pkg_aiboost_pro.xml`
   - `component/addons/pkg_aiboost_falang/pkg_aiboost_falang.xml` → `.../updates/multilang/pkg_aiboost_falang.xml`
   - `component/addons/pkg_aiboost_yootheme/pkg_aiboost_yootheme.xml` → `.../updates/yootheme/pkg_aiboost_yootheme.xml`
   Each with `<dlid prefix="dlid=" suffix=""/>`.

2. **Stop stripping `<updateservers>` from the Pro build.** `scripts/build-package-zip.py`
   currently removes the `<updateservers>` block for the Pro edition
   (`re.sub(r"<updateservers>.*?</updateservers>", "", …)`). Change it so Pro KEEPS
   its update server (the Free base already keeps its own).

3. **Extend the update-XML generator.** `scripts/generate-update-xml.py` currently emits
   only the Free `pkg_aiboost.xml`. Add Pro + integration feeds (same `<update>` shape,
   per-element, J5 + J6 target rows). Note: the LIVE Pro/integration feeds are produced
   by the C2 server per-request (key-gated); the generator output is the canonical
   template / fallback.

4. **Auto-fill the download key on activation.** When `SettingsController::verifyLicense()`
   succeeds for a SKU, write the key into Joomla's `#__update_sites.extra_query` for the
   matching update site (e.g. `dlid=<key>`), so the native one-click update "just works"
   without the customer re-entering the key. Map: `bundle` → the `pkg_aiboost_pro`
   update site; `int_falang` → `pkg_aiboost_falang`; `int_yootheme` → `pkg_aiboost_yootheme`.

5. **Version lockstep** unchanged: Free + Pro ship the same `component/Version.php`.

---

## C2 — Website side (separate repo `bojancreator/aiboostnow`) — contract

Two endpoint families on `aiboostnow.com`. Both receive the license key as `?dlid=<key>`
(appended by Joomla from the Download Key).

### Update-XML endpoint (per product)

```
GET /updates/pro/pkg_aiboost_pro.xml?dlid=<key>
GET /updates/multilang/pkg_aiboost_falang.xml?dlid=<key>
GET /updates/yootheme/pkg_aiboost_yootheme.xml?dlid=<key>
```

Behaviour:
1. Validate `<key>` against Lemon Squeezy (`POST /v1/licenses/validate`), enforcing the
   SAME pins the plugin uses: `meta.store_id == EXPECTED_STORE_ID` and `meta.product_id ∈`
   the product set for this feed (core set for Pro; the single integration product for
   the others).
2. If **active** → return the Joomla update XML for the latest version, whose
   `<downloadurl>` carries the key (so the download is gated too), e.g.
   `https://aiboostnow.com/downloads/pro?dlid=<key>&v=<version>`.
3. If **not active / expired / wrong product / missing key** → return an EMPTY
   `<updates/>` (HTTP 200). Joomla then simply shows "no update", never an error. This
   matches the perpetual-activation model: an expired license keeps working but receives
   no new updates until renewed.

### Gated download endpoint (per product)

```
GET /downloads/pro?dlid=<key>&v=<version>
```

Behaviour: re-validate the key (same pins), then stream the matching ZIP from storage.
Reject (403) on invalid/expired/wrong-product. Keep current+previous versions available.

### Operational notes

- Store ID and product IDs are identical in Lemon Squeezy test and live mode — configure
  once.
- Cache validation results briefly (e.g. 60 s) to avoid hammering the LS API on update
  checks; never cache a not-active verdict as active.
- The Free `pkg_aiboost.xml` static feed is unchanged and needs no key.

---

## Status

- C1 mechanism: documented here; manifest/build wiring is applied only once C2 is live
  (a dead update server URL would show failed update-site fetches on installed Pro sites).
- C2: to be built in `bojancreator/aiboostnow`. The plugin already contains a fully
  tested LS validator (`component/lib/src/LicenseValidator.php`) whose pinning logic the
  C2 server should mirror.
