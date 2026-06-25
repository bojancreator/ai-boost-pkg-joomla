# PHP 8.5: never call ReflectionProperty/Method::setAccessible()

`ReflectionProperty::setAccessible()` / `ReflectionMethod::setAccessible()` has been a
**no-op since PHP 8.1** (reflection grants access to private/protected members
automatically) and is **deprecated in PHP 8.5** — calling it emits an `E_DEPRECATED`
notice. A surrounding `try { … } catch (\Throwable)` does **not** suppress it (an
E_DEPRECATED diagnostic is not a `\Throwable`). We support PHP 8.1–8.5, so the call is
pure downside: useless on every supported version and noisy on 8.5.

**Rule:** in shipped code (`component/**`, `plugins/**` — not `tests/`), never call
`->setAccessible(`. Reflect and read/write the member directly:

```php
$prop = (new \ReflectionClass(Uri::class))->getProperty('instances'); // protected static
$prop->setValue(null, []);   // works on 8.1+ WITHOUT setAccessible()
```

Found live (v0.87.57) in `AiBoostAeo::detectMarkdownRequest()` clearing the Uri instance
cache on a `.md` request — removed. Guarded by
`component/tests/Lib/Php85DeprecationContractTest.php` (source scan; red-green: re-adding
the call turns it red naming file:line). The same test also bans **non-canonical casts**
`(boolean)/(integer)/(double)/(real)/(binary)` (deprecated 8.5 — use `(bool)/(int)/(float)/(string)`).

Note: the Falang plugin (`falangdriver.php`) throws this same deprecation on staging —
that is **third-party, not ours**; do not touch it. See [[sitemap-thirdparty-output-leak]].
