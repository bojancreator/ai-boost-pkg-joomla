# FAQ Schema Debug Guide

## Quick Checklist

Copy-paste ovu listu i popuni vrednosti sa staging sajta:

```
[ ] Plugin enabled (System → Plugins → JoomlaBoost = zelena kvačica)
[ ] Enable Schema.org = Yes
[ ] FAQ Schema Enabled = Yes  
[ ] Enable Manual FAQs = Yes
[ ] Manual FAQ Items textarea = ima JSON unutra
```

## Test JSON

Ovaj JSON 100% radi, probaj sa njim:

```json
[
  {
    "question": "Test pitanje 1?",
    "answer": "Test odgovor 1."
  },
  {
    "question": "Test pitanje 2?",
    "answer": "Test odgovor 2."
  }
]
```

## Gde gledati rezultat

FAQ schema se **NE pojavljuje na homepage-u**!

Mora biti na:
- Article странама (jesi na article stranici - OK ✅)

## Provera da li plugin uopšte radi

U source kodu (`Ctrl+U`) traži:
```
"@type":"Organization"
```

✅ **IMA** - plugin radi! (video sam u tvom HTML-u)

Traži:
```
"@type":"FAQPage"
```

❌ **NEMA** - FAQ schema se ne generiše

## Najčešći uzroci

1. **Toggle nije omogućen** - proveri tačno 3 toggle-a gore
2. **JSON greška** - copy-paste moj test JSON
3. **Cache** - očisti Joomla cache (System → Clear Cache)

## PHP Error log

Ako ništa ne pomogne, proveri:
`administrator/logs/` folder

Traži fajl koji sadrži `joomlaboost` ili danas današnji datum.
