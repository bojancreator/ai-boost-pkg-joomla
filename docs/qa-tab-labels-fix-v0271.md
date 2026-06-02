# QA Report — Tab Labels Fix (v0.27.1)

**Task:** #91 — Verify fix on live Joomla — confirm other plugins show correct tab labels  
**Date:** 2026-05-12  
**Plugin version:** 0.27.1  
**ZIP:** `plg_system_joomlaboost-0.27.1.zip`  
**Fix file:** `plugin/src/plugins/system/joomlaboost/joomlaboost.php` — lines 519–596  

> **Note:** Live-Joomla verification requires a running Joomla admin installation.
> This Replit workspace does not have a Joomla instance. Code-level checks
> are marked **[CODE]**; checks requiring a live site are marked **[MANUAL — PENDING]**.
> Bojan must complete [MANUAL] rows on the staging instance before closing this task.

---

## 1. Bug Summary

`onContentPrepareForm` ran multilang field injection, field prefix registration,
and JS/CSS asset loading for **every** plugin edit form in Joomla admin because
`$formName === 'com_plugins.plugin'` is `true` for all plugins — not just AI Boost.

**Symptom:** Tab labels in System – Cache, System – Debug, etc. displayed as raw
constants (`PLG_SYSTEM_CACHE_TAB_CACHING`) instead of translated text.

---

## 2. Fix — Code Review

**File:** `plugin/src/plugins/system/joomlaboost/joomlaboost.php`  
**Lines:** 519–535 (guard block inserted before any injection)

```php
// Register custom fields for plugin configuration
if ($formName === 'com_plugins.plugin') {
    // Only act on our own plugin edit form — not on every other plugin's form.
    // $data may be an object (stdClass) or an associative array depending on Joomla version.
    $element = '';
    if (is_object($data) && isset($data->element)) {
        $element = (string) $data->element;
    } elseif (is_array($data) && isset($data['element'])) {
        $element = (string) $data['element'];
    }
    // Fallback: read from the request (covers edge cases where $data is empty on first load)
    if ($element === '') {
        $element = (string) Factory::getApplication()->input->get('element', '', 'cmd');
    }
    if ($element !== 'joomlaboost') {
        return;   // ← EARLY EXIT — zero side effects on other plugins
    }
    // ... injection only continues for element=joomlaboost
```

---

## 3. Static Code Analysis — Results

| # | Check | Evidence | Result |
|---|-------|----------|--------|
| 1 | `$data` as object (`stdClass`) — Joomla 4/5 | `is_object($data) && isset($data->element)` at line 524 | **PASS [CODE]** |
| 2 | `$data` as array — legacy/edge case | `is_array($data) && isset($data['element'])` at line 526 | **PASS [CODE]** |
| 3 | URL fallback when `$data` is empty | `Factory::getApplication()->input->get('element', '', 'cmd')` at line 531 | **PASS [CODE]** |
| 4 | Input sanitized — no injection risk | `'cmd'` filter strips all non-alphanumeric chars | **PASS [CODE]** |
| 5 | Early return prevents all side effects | `return` at line 534 — before `addFieldPrefix`, `addFieldPath`, any injection | **PASS [CODE]** |
| 6 | Article form path unaffected | Separate `if ($formName !== 'com_content.article') return;` block at line 600 — unchanged | **PASS [CODE]** |
| 7 | PHP 8.1–8.5 compatible | No deprecated calls; `isset()` prevents null-access warnings | **PASS [CODE]** |
| 8 | No new PHP errors possible from guard | All `$data` access is `isset()`-guarded | **PASS [CODE]** |

**Static analysis verdict: 8/8 PASS** ✅

---

## 4. Live Verification — Manual Checklist (Bojan to complete)

Install `plg_system_joomlaboost-0.27.1.zip` on staging, then verify each row.

### Group A — Other system plugins (tab labels must be translated)

| # | Plugin | Expected tab names | Result |
|---|--------|--------------------|--------|
| A1 | System – Cache | "Cache", "Advanced" (not raw constants) | PENDING |
| A2 | System – Debug | "Debug", "Logging", "Advanced" (not raw constants) | PENDING |
| A3 | System – SEF | "SEF", "Advanced" (not raw constants) | PENDING |

**Fail indicator:** Raw strings like `PLG_SYSTEM_CACHE_TAB_CACHING` still visible →
clear Joomla cache (`System → Clear Cache`), then re-open the plugin.

### Group B — AI Boost plugin (must work exactly as before)

| # | What | Expected | Result |
|---|------|----------|--------|
| B1 | Open AI Boost edit form | 7 tabs visible: Plugin, Organization, Schema.org, Sitemap, Social & Meta, Analytics, Debug | PENDING |
| B2 | Organization tab | Multilingual text fields visible (one per installed language) | PENDING |
| B3 | Plugin tab | License tier banner visible | PENDING |
| B4 | JS behaviour | Language-flag selector in multilang fields works | PENDING |
| B5 | Save | Settings save without error | PENDING |

### Group C — Error log

| # | What | Expected | Result |
|---|------|----------|--------|
| C1 | Joomla error log after A + B | No new PHP errors or warnings | PENDING |
| C2 | Browser console after opening any plugin | No JS errors | PENDING |

---

## 5. Acceptance Criteria

- All 8 static analysis checks: **PASS** ✅ (complete above)
- All A + B + C manual rows marked PASS by Bojan → task fully closed
- Any A-row fail: verify ZIP install; clear Joomla cache; reinstall
- Any B-row fail: note exact Joomla version + PHP version in comments below

---

## 6. Manual Test Sign-off

> Bojan — fill in this section after running the checklist above.

**Date tested:**  
**Joomla version:**  
**PHP version:**  
**A-group result:** (all pass / partial / fail)  
**B-group result:** (all pass / partial / fail)  
**C-group result:** (clean / errors noted)  
**Overall verdict:**
