# Custom Fields PHP 8.1+ Deprecation Issue

## Problem Summary

We're experiencing a PHP 8.1+ deprecation warning when using Joomla's custom fields system with articles that don't have values set for media-type fields.

## Error Details

**Error Message:**

```
Deprecated: json_decode(): Passing null to parameter #1 ($json) of type string is deprecated
in /plugins/fields/media/src/Extension/Media.php on line 104
```

**When it occurs:**

- Articles WITHOUT custom field values → Error appears
- Articles WITH custom field values → Works perfectly
- Only visible when Error Reporting = Maximum
- Error hidden when Error Reporting = System Default

## Technical Context

### Our Custom Fields Setup

We auto-create 3 custom fields during plugin installation for per-article OpenGraph overrides:

1. **custom_og_image** (type: media) - Stores JSON: `{"imagefile":"path/to/image.jpg"}`
2. **custom_og_title** (type: text) - Plain string
3. **custom_og_description** (type: textarea) - Plain string

### Database Structure

**`#__fields` table:**

- `params`: Field behavior (display, render_class, showlabel, etc.) - JSON
- `fieldparams`: Field-type specific config (image_class, imagefile for media) - JSON
- `state`: 1=published (needed for backend editing)

**`#__fields_values` table:**

- `value`: Per-article field value - NULL when article doesn't have custom value set

### Root Cause

1. **Joomla's Auto-Rendering**: During `onContentPrepare` event, Joomla automatically renders ALL published custom fields
2. **Media Field Plugin**: Joomla core file `/plugins/fields/media/src/Extension/Media.php` line 104 calls:
   ```php
   $decoded = json_decode($value, true);
   ```
3. **PHP 8.1+ Type Requirements**: `json_decode()` expects `string` parameter, not `null`
4. **Result**: When article has no custom field value → `$value` is `NULL` → deprecation warning

### Our Use Case

- We need these fields **programmatically accessible** (our OpenGraphService reads them directly from database)
- We need them **editable in admin panel** (content editors can override OpenGraph per article)
- We **don't need** them auto-rendered on frontend (we generate OG meta tags programmatically)

## Attempted Solutions

### ❌ Attempt 1: Set default in `fieldparams`

```php
$fieldparams = json_encode(['default' => '', 'imagefile' => '']);
```

**Result:** Joomla doesn't use `fieldparams.default` for NULL values in `#__fields_values`

### ❌ Attempt 2: Bulk-insert empty values

```php
INSERT INTO #__fields_values (field_id, item_id, value) VALUES (?, ?, '');
```

**Result:** Media field still receives NULL during rendering

### ❌ Attempt 3: Insert JSON structure

```php
$defaultValue = json_encode(['imagefile' => '']);
```

**Result:** Joomla still auto-renders fields, triggering plugin

### ❌ Attempt 4: Expanded params to prevent rendering

```php
$params = json_encode([
    'display' => '0',
    'render_class' => '',
    'class' => '',
    'showlabel' => '0',
    'disabled' => '0',
    'readonly' => '0'
]);
```

**Result:** Joomla still processes fields during content prepare event

### ❌ Attempt 5: Set `state = 0` (unpublish)

```php
'state' => 0,  // Unpublished
```

**Result:** ✅ No error (fields not rendered), BUT ❌ Fields disappear from article edit form in admin

## Current Situation

**Working:** Article 216 (has custom values) - OpenGraph tags display perfectly
**Broken:** Article 214 (no custom values) - Deprecation error on every page with Error Reporting = Maximum

## Questions for Joomla Expert

1. **Is there a way to prevent Joomla from auto-rendering specific custom fields on frontend** while keeping them:

   - Published (`state = 1`)
   - Visible in admin article editor
   - Programmatically readable via direct database queries

2. **Can we hook into `onContentPrepare` or field rendering events** to exclude our `custom_og_*` fields from automatic rendering?

3. **Is there a `params` configuration** that completely disables frontend rendering but preserves backend editing?

4. **Alternative approach:** Should we create fields as a different type (not `media`) to avoid the `json_decode()` issue? Trade-off: less user-friendly media picker in admin.

5. **Template override:** Can we override `components/com_content/article/default.php` to explicitly exclude certain fields from rendering?

6. **Event priority:** Can we set our plugin to run BEFORE field rendering with higher priority to intercept and prevent rendering of specific fields?

## ✅ SOLUTION (v0.1.98): Access Level = 3 (Special)

### Implementation

Set `access = 3` (Special) instead of `1` (Public) for custom fields:

```php
// In script.php - createField() method (line 219):
$values = [
    // ... other values ...
    3,  // access: 3=Special (prevents frontend loading for Guest users, avoids PHP 8.1+ json_decode(null) deprecation)
    // ... rest of values ...
];

// In script.php - updateFieldsDisplayParam() method (line 303):
$updateQuery = $db->getQuery(true)
    ->update($db->quoteName('#__fields'))
    ->set($db->quoteName('access') . ' = 3')  // Special - prevents Guest loading
    ->where('id = ' . (int) $field->id);
```

### Why This Works

**Frontend (Guest Users - Access Level 1):**

- Guest access (1) < Required access (3)
- Joomla **skips field loading entirely** during `onContentPrepare`
- Media plugin **never invoked**
- No `json_decode(null)` → **No deprecation error** ✅

**Backend (Administrators - Access Level 6+):**

- Admin access (6+) > Required access (3)
- Fields **visible and editable** in article editor ✅

**OpenGraphService (Code):**

- Direct SQL query: `SELECT * FROM #__fields WHERE name='custom_og_image'`
- **Ignores ACL checks** → Reads field values regardless of access level ✅

### Expert Validation

This solution was recommended by Joomla expert as "magic bullet" and "najbrže i najelegantnije" (fastest and most elegant):

> "Access Level = Special ... Ovo je 'magic bullet'. Admini ga vide, frontend ga ne učitava, vaš servis čita direktno iz baze."

### Verification Results (Staging)

✅ **Article 214** (no custom values) - No json_decode error
✅ **Homepage** - No json_decode error
✅ **Our Story page** - No json_decode error
✅ **Article 216** (has custom values) - OpenGraph meta tags working perfectly:

- `og:title`: "Proba OG-a"
- `og:description`: "Ovo je proba OG-a kako radi"
- `og:image`: "https://staging.offroadserbia.com/images/images/4x4avantura.jpg"

### Alternative Approaches Considered

**Option 1: Fields Plugin (`plg_fields_ogfix`)**

- Hook: `onCustomFieldsBeforePrepareField`
- Sanitize: `$field->rawvalue = '{"imagefile":""}'` before Media plugin
- Complexity: Medium (requires separate plugin)
- Status: Not needed with Access Level solution

**Option 2: Database Normalization**

- SQL: `UPDATE #__fields_values SET value = '{}' WHERE value IS NULL`
- Bulk-insert defaults for all articles
- Complexity: High (ongoing maintenance for new articles)
- Status: Not needed with Access Level solution

### Why Previous Attempts Failed

1. ❌ **v0.1.91-93**: Tried to fix data (fieldparams, values table) but Joomla still loaded fields
2. ❌ **v0.1.94**: Tried to prevent rendering (params) but processing happened before rendering
3. ❌ **v0.1.95**: Unpublished fields (state=0) → Fixed error but broke admin editing
4. ❌ **v0.1.96-97**: Filtered in `onContentPrepare` → Too late, Media plugin already invoked

**Key Insight:** Error occurs during **field loading phase**, not rendering phase. Access Level prevents loading at the source.

## Desired End State - ✅ ACHIEVED

✅ No PHP 8.1+ deprecation warnings (even with Error Reporting = Maximum)
✅ Custom fields editable in Joomla admin article editor
✅ Custom fields readable programmatically by our OpenGraphService
✅ Fields NOT auto-rendered on frontend (we handle meta tags manually)
✅ PHP 8.1+ compatible without modifying Joomla core files
✅ **Native Joomla ACL mechanism - no custom workarounds**

## ✅ FINAL SOLUTION (v0.1.103): Fields Plugin with ALL Field Types

### The REAL Problem - TWO Errors!

**Error 1 (Discovered first):** `json_decode(): Passing null to parameter #1`

- **Location:** `/plugins/fields/media/src/Extension/Media.php` line 104
- **Affects:** Media type fields only
- **When:** Media plugin tries to decode NULL value

**Error 2 (Discovered later):** `DOMCdataSection::__construct(): Passing null to parameter #1`

- **Location:** `/administrator/components/com_fields/src/Plugin/FieldsPlugin.php` line 277
- **Affects:** ALL field types (text, textarea, media)
- **When:** Joomla creates CDATA section for XML rendering

The v0.1.98 Access Level solution worked for **frontend only**. Backend admin panel still showed BOTH deprecation errors because:

1. **Administrators have Access Level 6+** (higher than Special = 3)
2. Joomla loads custom fields to populate article editor form
3. **Error occurs during form loading**, not during save
4. `onContentPrepareForm` manipulates XML form definition, NOT field values
5. `onContentAfterSave` runs too late - error already displayed

### Event Lifecycle (Backend)

```
Admin opens article editor:
1. onContentPrepareForm → Form XML preparation (wrong level for fix)
2. FieldsHelper loads field values from database
3. onCustomFieldsBeforePrepareField → CORRECT EVENT FOR FIX ✅
4. Media field plugin: json_decode($value)
5. If $value is NULL → DEPRECATION ERROR ⚠️
6. Form displays (with errors visible)

Admin saves article:
7. onContentBeforeSave
8. Database UPDATE
9. onContentAfterSave → TOO LATE ❌
```

### The Correct Solution: Fields Plugin

Created **`plg_fields_ogfix`** - separate Fields plugin that intercepts field preparation:

**File:** `src/plugins/fields/ogfix/src/Extension/Ogfix.php`

```php
namespace Joomla\Plugin\Fields\Ogfix\Extension;

use Joomla\Component\Fields\Administrator\Plugin\FieldsPlugin;

final class Ogfix extends FieldsPlugin
{
    public function onCustomFieldsBeforePrepareField($context, $item, $field): void
    {
        // 1. Target only articles
        if (strpos($context, 'com_content.article') !== 0) {
            return;
        }

        // 2. Target ALL three custom OG fields
        if (!in_array($field->name, ['custom_og_image', 'custom_og_title', 'custom_og_description'], true)) {
            return;
        }

        // 3. Determine default value based on field type
        $defaultValue = '';
        if ($field->type === 'media') {
            $defaultValue = '{"imagefile":""}'; // JSON for json_decode()
        } else {
            $defaultValue = ''; // Empty string for text/textarea CDATA
        }

        // 4. THE FIX: Sanitize NULL BEFORE any processing
        // Prevents BOTH json_decode(null) AND DOMCdataSection(null)
        if (($field->rawvalue ?? null) === null || $field->rawvalue === '') {
            $field->rawvalue = $defaultValue;
        }

        if (($field->value ?? null) === null || $field->value === '') {
            $field->value = $field->rawvalue ?? $defaultValue;
        }
    }
}
```

### Why This Works

1. **Plugin Group:** `fields` (not system) - correct event context
2. **Event:** `onCustomFieldsBeforePrepareField` - fires BEFORE Media plugin
3. **Plugin Ordering:** Must be ABOVE (before) "Fields - Media" plugin
4. **Works Everywhere:** Both frontend and backend use same event
5. **By Reference:** Mutating `$field` object affects downstream plugins

### Installation & Configuration

**Build:**

```powershell
.\tools\build_ogfix.ps1 -Version "0.1.103"
```

**Install:**

1. Upload `plg_fields_ogfix-0.1.103.zip` via Extensions → Manage → Install
2. Enable plugin in System → Plugins
3. **CRITICAL:** Set plugin ordering **ABOVE** "Fields - Media":
   - System → Plugins
   - Filter: Group = "fields"
   - Drag "Fields - OG Fix" above "Fields - Media"
   - Or set ordering number lower than Media plugin

**Verify Ordering:**

```sql
SELECT element, ordering
FROM #__extensions
WHERE folder = 'fields'
AND element IN ('ogfix', 'media')
ORDER BY ordering;
```

Expected result:

```
ogfix  | -10 (or any number < media ordering)
media  |   0
```

### Expert Validation

Both Joomla experts confirmed this approach:

**Expert 1:** "Moramo presresti objekat polja u 'letu', tačno pre nego što `plg_fields_media` pokuša da ga parsira. [...] 'Before' efekat se postiže isključivo redosledom izvršavanja (Ordering)."

**Expert 2:** "To je _pravi_ način. [...] Mutiraš `$field->rawvalue` / `$field->value` pre nego što Media plugin dođe na red, pa `json_decode()` više nikad ne dobija `null`."

### Complete Protection Stack

**Layer 1:** SQL Normalization (v0.1.100)

- **script.php:** `fixNullFieldValues()` in postflight
- Cleans existing NULL values during plugin install/update
- One-time fix for historical data

**Layer 2:** Fields Plugin (v0.1.103) ✅

- **plg_fields_ogfix:** `onCustomFieldsBeforePrepareField`
- Runtime protection BEFORE Media plugin executes
- Protects ALL three fields: media, text, textarea
- Prevents BOTH `json_decode(null)` AND `DOMCdataSection(null)` errors
- Works in both frontend and backend
- **Primary defense against deprecation warnings**

**Layer 3:** System Plugin Backup (v0.1.101)

- **joomlaboost.php:** `onContentAfterSave`
- Ensures new articles get default JSON values
- Belt-and-suspenders approach

**Layer 4:** Access Level (v0.1.98)

- **Access = 3 (Special)** on custom fields
- Prevents Guest users from loading fields on frontend
- Reduces unnecessary processing

### Testing Results

**Frontend (Guest Users):**

- ✅ No errors (Access Level prevents loading)
- ✅ OpenGraphService reads values via direct SQL
- ✅ Error Reporting = Maximum shows clean output

**Backend (Administrators):**

- ✅ No errors when opening articles without custom values
- ✅ No errors when creating new articles
- ✅ Fields visible and editable in article editor
- ✅ Media picker works normally

### Why Previous Attempts Failed

1. ❌ **v0.1.91-97**: Frontend fixes (Access Level worked, but backend persisted)
2. ❌ **v0.1.100**: SQL normalization only (doesn't prevent new NULL values)
3. ❌ **v0.1.101**: `onContentPrepareForm` (wrong event - too early, wrong context)
4. ❌ **v0.1.102**: Only protected media fields, missed text/textarea DOMCdataSection error
5. ✅ **v0.1.103**: Fields plugin protecting ALL three custom field types

### PHP 8.1+ Core Status

Joomla has patched this in newer versions (PR #39542), but:

- Older J4.x installations may not have the fix
- Custom deployments / migrations can still create NULL values
- Runtime protection is defense-in-depth best practice

### Related Joomla Issues

- **GitHub PR:** [#39542 - Update plugins/fields/media for json_decode(null)](https://github.com/joomla/joomla-cms/pull/39542)
- **Joomla Stack Exchange:** [Event for saving Custom Fields data](https://joomla.stackexchange.com/questions/21222)

## ✅ ULTIMATE SOLUTION (v0.1.108): Joomla `default_value` Column

### The Missing Piece - Joomla's Built-in Default Mechanism!

All previous solutions were workarounds. **v0.1.108 uses Joomla's native default value system!**

**The Discovery:**

- Joomla has `default_value` column in `#__fields` table
- When creating NEW articles, Joomla reads this column
- Editor automatically shows default value (NOT NULL!)
- **No database trigger needed** - Joomla handles it!

### Implementation

**1. Field Creation (script.php - createField):**

```php
$columns = [
    'context',
    'group_id',
    'title',
    'name',
    'label',
    'type',
    'default_value',  // ✅ CRITICAL - This was missing!
    'state',
    'access',
    // ... rest
];

// Set appropriate default based on field type
$defaultValue = '';
if ($type === 'media') {
    $defaultValue = '{"imagefile":""}';  // Valid JSON for Media plugin
} elseif ($type === 'text' || $type === 'textarea') {
    $defaultValue = '';  // Empty string prevents DOMCdataSection(null)
}

$values = [
    // ...
    $db->quote($defaultValue),  // 🎯 Sets default in Joomla field definition
    // ...
];
```

**2. Field Update - Preserve Existing Defaults (script.php - updateFieldsDisplayParam):**

```php
// Determine default value based on field type
$defaultValue = '';
if ($field->type === 'media') {
    $defaultValue = '{"imagefile":""}';
}

$updateQuery = $db->getQuery(true)
    ->update($db->quoteName('#__fields'))
    ->set($db->quoteName('access') . ' = 3')
    ->set($db->quoteName('params') . ' = ' . $db->quote($params))
    ->set($db->quoteName('fieldparams') . ' = ' . $db->quote($fieldparams))
    ->where('id = ' . (int) $field->id);

// ✅ CRITICAL: Only update if currently empty - preserves manual changes!
if (empty($field->default_value)) {
    $updateQuery->set($db->quoteName('default_value') . ' = ' . $db->quote($defaultValue));
}
```

### Why This is THE Solution

✅ **Joomla Native** - Uses built-in field default mechanism
✅ **Visible in Admin** - Default Value field shows in Content → Fields → Edit
✅ **Automatic** - New articles inherit default without any hooks/triggers
✅ **Preserves Customization** - Reinstall doesn't overwrite user-set defaults
✅ **No Performance Impact** - No database triggers, no event interception
✅ **Simple Architecture** - Joomla does the work, we just configure it

### How It Works

**Timeline - Creating New Article:**

```
1. Admin: Content → New Article
2. Joomla: INSERT INTO #__content (new article created)
3. Joomla: Reads #__fields.default_value for custom fields
4. Joomla: Populates editor with default values
5. Admin: Sees fields with defaults (NOT empty/NULL!)
6. Result: NO DOMCdataSection(null) errors! ✅
```

**What Happens on Plugin Reinstall:**

```
1. script.php reads existing fields
2. Checks if default_value is empty
3. If EMPTY → Updates to proper default
4. If HAS VALUE → SKIPS (preserves user customization!)
5. Result: Safe reinstall without data loss ✅
```

### Verification

**Database Check:**

```sql
SELECT
    name,
    type,
    default_value
FROM #__fields
WHERE name IN ('custom_og_image', 'custom_og_title', 'custom_og_description');
```

**Expected Result:**

```
custom_og_image       | media    | {"imagefile":""}
custom_og_title       | text     |
custom_og_description | textarea |
```

**Admin UI Check:**

```
Content → Fields → Edit "Custom OG Image"
General tab → Default Value field: {"imagefile":""}
✅ Visible and editable in standard Joomla UI!
```

### Complete Protection Stack (Final Architecture)

**Layer 1:** Joomla Default Value (v0.1.108) ⚡ **PRIMARY**

- Built-in Joomla mechanism
- Prevents NULL at creation time
- No custom code execution needed

**Layer 2:** Access Level = 3 (v0.1.98)

- Prevents Guest users from loading fields
- Reduces frontend processing

**Layer 3:** SQL Normalization (v0.1.100)

- Cleans historical NULL values during install
- One-time fix for existing data

**Layer 4:** Fields Plugin (v0.1.103) - **Optional**

- Runtime protection if defaults somehow fail
- Safety net for edge cases

### Why ALL Previous Attempts Failed

1. ❌ **v0.1.91-97**: Tried to fix symptoms (fieldparams, values table) - Wrong level!
2. ❌ **v0.1.98-103**: Access Level + Fields Plugin - Workarounds, not root cause fix!
3. ❌ **v0.1.104-107**: Database triggers - Overengineered, ignored Joomla's way!
4. ✅ **v0.1.108**: **default_value column** - THE JOOMLA WAY! 🎯

**Root Issue:** We were setting `fieldparams.imagefile` and `params` but **NOT** the actual `default_value` column that Joomla uses for new articles!

## Environment

- **Joomla Version:** 4.x (4.3.4+ recommended)
- **PHP Version:** 8.1+
- **Plugin Type:** System plugin (no separate Fields plugin needed!)
- **Context:** Production SEO plugin with 15+ services
- **Installation:** Auto-creates fields via `script.php` postflight hook
- **Solution Versions:**
  - v0.1.98 (Frontend - Access Level)
  - v0.1.100 (SQL Normalization)
  - v0.1.101-102 (Wrong event hooks)
  - v0.1.103 (Fields plugin - complex workaround)
  - v0.1.104-107 (Database triggers - overengineered)
  - **v0.1.108 (Joomla default_value - FINAL SOLUTION!)** ✅
- **Status:** ✅ **FULLY RESOLVED** - Native Joomla mechanism (2025-11-25)

---

**Problem solved after 17 iterations - the answer was in Joomla's standard field definition all along!** 🎉
