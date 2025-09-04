# JoomlaBoost — Troubleshooting (404/HTML umesto robots/sitemap/diag)

Ako vidiš HTML (homepage/404) umesto plain XML/TXT, velika je šansa da zahtev nije ni došao do plugina. Radi redom:

1. .htaccess (Admin Tools ili custom)

- Na sam vrh dodaj pravila koja puštaju ove rute u Joomlu:

```
RewriteRule ^robots\.txt$ index.php [L]
RewriteRule ^sitemap(_index)?\.xml$ index.php [L]
RewriteRule ^sitemap-(pages|articles)\.xml$ index.php [L]
```

- Ako niže postoji `RewriteRule ^robots\.txt$ - [L]` (kratki‑spoj ka fizičkom fajlu) — ukloni/isključi ga.
- Ukloni fizički `robots.txt` iz docroot‑a ako postoji.

2. WAF/CDN (Cloudflare, surogate cache)

- Dodaj izuzetke (no cache + skip security): `/robots.txt`, `/sitemap*.xml`, `/jb-diag`.

3. Non‑SEF fallback test

- Probaj: `/index.php?jb_sitemap=index` i `/index.php?jb_diag=1`.
- Ako query parametre neko seče, koristi `/jb-diag` (putanja) i proveri .htaccess pravila.

4. Provera domena i verzije

- U diag izlazu `active_match` treba da bude `1` na aktivnom domenu/subdomenu.
- U Adminu plugin verzija: proveri da je aktuelna JoomlaBoost verzija.

5. SEF podešavanja

- Kratkotrajno isključi SEF URL‑ove u Joomli i probaj query fallback; ako proradi — problem je u rewrite pravilima.

Ako i dalje zapinje, zapiši tačne URL‑ove, HTTP status i prvi red tela odgovora — sa tim tragom brzo rešavamo.
