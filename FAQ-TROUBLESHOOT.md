# FAQ Schema Troubleshooting - VAŽNO!

## 🔍 Da li si install-ovao v0.3.1?

**KRITIČNO PITANJE:**

U plugin podešavanjima, u **footer-u** ili negde gde piše verzija, koju verziju vidiš?

- Da li piše **0.3.1**? 
- Ili još uvek piše **0.3.0**?

Ako piše 0.3.0, nije se update-ovao!

---

## 📋 Test sa OVIM JSON-om (100% radi):

```json
[{"question":"Test?","answer":"Radi!"}]
```

Copy-paste **TAČNO OVAJ** JSON u Manual FAQ Items polje.

---

## 🐛 Mogući problemi:

### 1. `faq_schema_enabled` parametar možda ne postoji

Proveri u bazi podataka. Otvori phpMyAdmin ili SQL:

```sql
SELECT * FROM jos_extensions WHERE element = 'joomlaboost';
```

U koloni `params` treba da vidiš JSON koji sadrži:
```json
{
  "enable_schema": "1",
  "faq_schema_enabled": "1",
  "enable_manual_faqs": "1",
  "manual_faqs": "[...]"
}
```

### 2. Plugin nije enabled

System → Plugins → traži JoomlaBoost → proveri status kolonu

### 3. Cache problem

System → Clear Cache → Select All → Delete

Onda refresh stranice.

---

## 🔧 Sledeći korak ako ništa ne radi:

Screenshot-uj mi **CELU** Schema.org tab u plugin settings, da vidim sve parametre i vrednosti.

Ili mi pošalji direktno params JSON iz baze.
