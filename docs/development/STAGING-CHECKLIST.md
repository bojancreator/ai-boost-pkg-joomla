# Staging-only activation checklist (JoomlaBoost)

Ovaj dokument vodi korak‑po‑korak samo na STAGING okruženju. NEMA produkcije dok sve ne prođe ovde 100%.

## 1) Preduslovi (Joomla admin)

1. Plugin “System – JoomlaBoost” je instaliran i omogućen (0.1.0+).
2. Parametar “Active domain” postavljen na staging host (prazno = svi hostovi; preporuka: eksplicitno staviti staging domen).
3. Poredak pluginova: JoomlaBoost visoko iznad ostalih SEO/Cache/Template pluginova (posebno ispred Page Cache, minify, JSitemap, 4SEF itd.).
4. Isključiti “System – Page Cache” i obrisati sve keševe (System > Clear Cache + Purge Expired Cache).
5. (Opcionalno) Uključiti “Show staging badge” u JoomlaBoost da vizuelno vidite da ste na stagingu.

## 2) Direktni com_ajax testovi (MORAJU raditi)

Otvorite sledeće URL‑ove i proverite da su čisti (bez HTML templata u output‑u):

- Diag: /index.php?jb_diag=1
  - Očekuje se “JoomlaBoost diag v0.1.0…” i flagovi (active_match=1, enable_robots=1, enable_sitemap=1, itd.).
- Robots (fallback): /index.php?jb_robots=1
  - Content-Type: text/plain; sadrži “Sitemap: https://…/sitemap.xml”.
- Sitemap (index/pages/articles): /index.php?jb_sitemap=index|pages|articles
  - Content-Type: application/xml; prvi red:

```xml
<?xml version="1.0" encoding="UTF-8"?>
```

Headers (poželjno):

- ETag prisutan. Ponovni refresh treba da vrati 304 Not Modified (bez tela).
- Last-Modified na sitemap endpointima.

## 3) Fallback query rute (bez com_ajax)

Treba da vrate identičan čist output, bez HTML‑a:

- Robots fallback: /?jb_robots=1
- Sitemap index/pages/articles: /?jb_sitemap=index | pages | articles
- Diag fallback: /?jb_diag=1

Ako ovde dobijete HTML umesto čistog teksta/XML:

1. Proverite opet redosled pluginova (JoomlaBoost visoko).
2. Isključite Page Cache i minify/optimizer ekstenzije.
3. Proverite da li u `head`/`body` ništa ne injektuje com_ajax output (u 1.8.7 smo to već zaštitili, ali redosled može uticati).

## 4) “Pretty” rute (robots.txt, sitemap\*.xml)

Da bi radile:

1. Ne sme postojati fizički robots.txt na disku (u web root-u). Ako postoji – privremeno ga preimenujte.
2. Web server mora da propušta nepostojeće fajlove ka Joomla index.php (standardni Joomla rewrite).

Primeri konfiguracije:

- Apache (.htaccess): koristite default Joomla .htaccess iz distribucije. Važno: nemojte imati “RedirectMatch” za robots.txt; dozvolite da se prosledi u index.php.
- Nginx (concept):

```nginx
location / {
  try_files $uri $uri/ /index.php?$args;
}
# bez zasebnog “location = /robots.txt” ako je fizički fajl uklonjen
```

Testirajte:

- /robots.txt
- /sitemap.xml (index ili pages u zavisnosti od podešavanja),
- /sitemap-pages.xml
- /sitemap-articles.xml

Svi treba da budu Content-Type ispravan (text/plain ili application/xml), bez HTML “template” kontaminacije.

## 5) Validacija sadržaja sitemap-a

1. Home URL prisutan u pages.
2. Menu stavke (objavljene, interne) uključene, izuzete prema listi “Exclude menu IDs”.
3. Članci (state=1), max N po “sitemap_max_articles”, izuzete kategorije po listi.
4. Alternates (hreflang) i image tagovi prisutni ako su opcije uključene.
5. Datumi lastmod u formatu YYYY-MM-DD; indeksi imaju lastmod po najskorijem child-u.

## 6) Kriterijumi “GREEN” na stagingu

- Svi com_ajax i fallback URL‑ovi čisti (bez HTML), sa ETag/304 ponašanjem.
- Pretty rute rade (nema fizičkog robots.txt; rewrites podešeni).
- Diag “active_match=1” i verzija odgovara ZIP-u.
- Nema dupliranih OG/Twitter meta; hreflang linkovi prisutni po jezicima.

## 7) Tek posle ovoga – plan za produkciju

1. Prenesite potpuno istu konfiguraciju (plugin params) i redosled pluginova.
2. Uverite se da na produkciji nema fizičkog robots.txt (ili ga arhivirajte) i da rewrites rade.
3. Ponovite sve testove iz ove liste na produkciji.

Napomena: Ako želite, možemo dodati kratku admin “Health” stranicu u plugin (Back‑office) koja automatski proverava sve gore i daje zeleno/crveno.
