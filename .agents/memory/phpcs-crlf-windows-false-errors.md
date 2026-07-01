# phpcs on this Windows checkout reports false CRLF errors on EVERY file

The working tree is checked out with **CRLF** line endings (git `autocrlf` on Windows) while the
committed blobs are **LF**. `phpcs` (phpcs.xml) expects LF, so running it against a working-tree file
reports `End of line character is invalid; expected "\n" but found "\r\n"` PLUS a cascade of spurious
`ScopeClosingBrace` / `ControlSignature` / multi-line-control brace errors — on **every** file, edited or
not. These are NOT real style violations and are NOT what CI (Linux, LF) sees.

**How to tell your edit is actually clean (do this, don't trust raw working-tree phpcs):**
- Compare HEAD vs working: `git show HEAD:<file> > /tmp/h.php && phpcs /tmp/h.php` (HEAD = LF = the CI view).
- Or LF-normalise the working file first: `tr -d '\r' < <file> > /tmp/lf.php && phpcs /tmp/lf.php` → 0 errors means your edit added nothing.
- Sanity check: run phpcs on a file you did NOT touch — if it shows the same CRLF/brace errors, it's the environment, not you.
- `git commit` warns `LF will be replaced by CRLF` and stores LF, so the committed/pushed code is clean.

Same class of Windows-local noise as phpstan here (192 baseline "extends unknown class Joomla\CMS\…"
errors because the Joomla libs aren't on phpstan's path locally). For phpstan, prove your edit is clean by
analysing a single non-Joomla-extending file (e.g. a `lib/src/Page/` class → `[OK] No errors`) and/or
confirming the total error count is unchanged vs HEAD. Discovered order 0043 (2026-07-01).
