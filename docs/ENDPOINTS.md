# JoomlaBoost — Endpoints i specifikacija

Ovo je izlistano i u AI‑OVERVIEW, ovde su detalji + očekivana zaglavlja i brzi testovi. Primeri koriste example.com samo kao ilustraciju.

## robots.txt

- URL: `/robots.txt`
- Metod: GET
- Content‑Type: `text/plain; charset=UTF-8`
- Keš: `ETag` + 304 na `If-None-Match`
- Sadržaj: standardne Disallow linije + `Sitemap: https://example.com/sitemap_index.xml` (ili `sitemap.xml` ako index nije uključen; na stagingu biće `https://staging.example.com/...`)

Fallback (bez rewrite):

- `GET /index.php?jb_robots=1`

## Sitemape

Varijante ruta:

- `GET /sitemap.xml`
- `GET /sitemap_index.xml` (index)
- `GET /sitemap-pages.xml`
- `GET /sitemap-articles.xml`

Fallback (non‑SEF, mora raditi i kad path rewrite ne prolazi):

- `GET /index.php?jb_sitemap=index|pages|articles`

Zaglavlja:

- Content‑Type: `application/xml; charset=UTF-8`
- `ETag` i `Last-Modified` (UTC, dnevna rezolucija)
- Podržan 304 preko `If-None-Match` i `If-Modified-Since`

Format:

- Index: `<sitemapindex>` sa `<sitemap><loc>...` i `<lastmod>YYYY-MM-DD</lastmod>`
- Urlset: `<urlset>` sa `<url><loc>...`, opciono `<image:image>...`, alternates `<xhtml:link rel="alternate" hreflang="..." href="..."/>`

## Dijagnostika

- `GET /index.php?jb_diag=1` → `application/json`

Primer izlaza:

```json
{
  "plugin_info": {
    "name": "JoomlaBoost",
    "version": "0.1.17",
    "enabled": true
  },
  "domain_config": {
    "domain": "staging.example.com",
    "environment": "staging"
  },
  "services": { "sitemap": { "enabled": true } },
  "joomla_info": { "version": "4.x", "debug_mode": false }
}
```

Napomene:

- Diag radi i bez `active_domain` uslova (namerno), da bi se videlo stanje čak i kad domen ne „pogađa“.

## Testiranje (brzo)

- Ako dobijaš HTML umesto `text/plain`/`application/xml`, zahtevi ne prolaze do Joomle/plugina — vidi `docs/TROUBLESHOOTING.md`.
