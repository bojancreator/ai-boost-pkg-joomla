# Q&A Debug Checklist za staging.offroadserbia.com

Proveri sledeće (redosledom):

## 1. Plugin enabled?
- System → Plugins → JoomlaBoost
- Status kolona mora biti **zelena kvačica** (Published)
- Ako nije: klikni na status ikonu da omogućiš

## 2. Schema.org enabled?
U plugin settings, Schema.org tab:
- [ ] `Enable Schema.org` = **Yes**
- [ ] `FAQ Schema Enabled` = **Yes** 
- [ ] `Enable Manual FAQs` = **Yes**

## 3. JSON validan?
Proveri da JSON nema greške:
```json
[
  {
    "question": "Test pitanje?",
    "answer": "Test odgovor."
  }
]
```
- BEZ zareza nakon poslednjeg objekta
- Svi quote znaci moraju biti "pravi" `"` ne `"` ili `"`

## 4. Gde gledaš?
Schema se pojavljuje **SAMO** na:
- Article страницама (pojedinačni clanak)
- Category страницама

**NE pojavljuje se** na:
- Homepage
- Custom page-ovima
- Contact formi

## 5. Provera u source kodu
Ctrl+U → traži: `application/ld+json`

Trebalo bi da vidiš:
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [...]
}
</script>
```

## Debug log provera
Ako imaš pristup Joomla log fajlovima, proveri:
`logs/plg_system_joomlaboost.log.php`

Ako nešto pukne, biće tamo zapisano.
