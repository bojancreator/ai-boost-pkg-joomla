# 🌐 TEST SAJTOVI - KOMPLETNA TABELA (ŠABLON)

> ⚠️ **Ovo je šablon bez lozinki.** Kopiraj ga u `test_sajtovi.md` (gitignorisan)
> i popuni prave kredencijale lokalno. Prave lozinke NIKAD ne idu u git.
>
> ```powershell
> Copy-Item test_sajtovi.example.md test_sajtovi.md
> ```

## 🔑 Pristupni podaci

### Lokalni test sajtovi (server1.emarket1ng.net)
- **Username:** `neimar`
- **WordPress Password:** `<WP_PASSWORD>`
- **Joomla Password:** `<JOOMLA_PASSWORD>`

### Eksterni test sajtovi (LiteSpeed hosting)
- **Username:** `aiadmin`
- **Password:** `<LITESPEED_PASSWORD>`

---

## 📘 WORDPRESS SAJTOVI

| Sajt | Frontend URL | Admin URL | PHP Verzija |
|------|--------------|-----------|-------------|
| **WordPress 6 FREE** | https://wp6-free.testmyweb.info/ | https://wp6-free.testmyweb.info/wp-admin/ | PHP 8.3 ✅ |
| **WordPress 6 PRO** | https://wp6-pro.testmyweb.info/ | https://wp6-pro.testmyweb.info/wp-admin/ | PHP 8.3 ✅ |
| **WordPress 7 FREE** | https://wp7-free.testmyweb.info/ | https://wp7-free.testmyweb.info/wp-admin/ | PHP 8.3 ✅ |
| **WordPress 7 PRO** | https://wp7-pro.testmyweb.info/ | https://wp7-pro.testmyweb.info/wp-admin/ | PHP 8.3 ✅ |

---

## 🟠 JOOMLA SAJTOVI

| Sajt | Frontend URL | Admin URL | PHP Verzija |
|------|--------------|-----------|-------------|
| **Joomla 5 FREE** | https://joomla5-free.testmyweb.info/ | https://joomla5-free.testmyweb.info/administrator/ | PHP 8.2 ✅ |
| **Joomla 5 PRO** | https://joomla5-pro.testmyweb.info/ | https://joomla5-pro.testmyweb.info/administrator/ | PHP 8.3 |
| **Joomla 6 FREE** | https://joomla6-free.testmyweb.info/ | https://joomla6-free.testmyweb.info/administrator/ | PHP 8.3 ✅ |
| **Joomla 6 PRO** | https://joomla6-pro.testmyweb.info/ | https://joomla6-pro.testmyweb.info/administrator/ | PHP 8.3 |

---

## 🌍 EKSTERNI JOOMLA SAJTOVI (LiteSpeed)

| Sajt | Frontend URL | Admin URL | PHP | Database | Web Server |
|------|--------------|-----------|-----|----------|------------|
| **OffRoad Balkans** | https://offroadbalkans.com/ | https://offroadbalkans.com/administrator | PHP 8.4 | MariaDB 11.4.12 | LiteSpeed |
| **OffRoad Serbia (Staging)** | https://staging.offroadserbia.com/ | https://staging.offroadserbia.com/administrator | PHP 8.5 | MariaDB 11.4.12 | LiteSpeed |

**Pristupni podaci:**
- Username: `aiadmin`
- Password: `<LITESPEED_PASSWORD>`

---

## 📋 Brzi linkovi - WordPress Admin

- [WP6 FREE Admin](https://wp6-free.testmyweb.info/wp-admin/)
- [WP6 PRO Admin](https://wp6-pro.testmyweb.info/wp-admin/)
- [WP7 FREE Admin](https://wp7-free.testmyweb.info/wp-admin/)
- [WP7 PRO Admin](https://wp7-pro.testmyweb.info/wp-admin/)

## 📋 Brzi linkovi - Joomla Admin

### Lokalni test sajtovi
- [Joomla 5 FREE Admin](https://joomla5-free.testmyweb.info/administrator/)
- [Joomla 5 PRO Admin](https://joomla5-pro.testmyweb.info/administrator/)
- [Joomla 6 FREE Admin](https://joomla6-free.testmyweb.info/administrator/)
- [Joomla 6 PRO Admin](https://joomla6-pro.testmyweb.info/administrator/)

### Eksterni sajtovi
- [OffRoad Balkans Admin](https://offroadbalkans.com/administrator)
- [OffRoad Serbia Staging Admin](https://staging.offroadserbia.com/administrator)

---

## 💡 Napomene

✅ **Dostupno sa interneta** (bez VPN-a):
- Svi sajtovi su dostupni putem javne IP: 109.92.176.55
- DNS podešen za sve domene

🔐 **Svi sajtovi koriste iste kredencijale:**
- Username: **neimar**
- WordPress: **`<WP_PASSWORD>`** (4 uzvičnika)
- Joomla: **`<JOOMLA_PASSWORD>`** (2 uzvičnika)

⚠️ **PHP Verzije - Važna napomena:**
- **CentOS 7 (trenutno):** Maksimalna verzija je PHP 8.3
- **PHP 8.4 NIJE dostupan** za CentOS 7
- **Za PHP 8.4:** Potrebna migracija na AlmaLinux 9 (vidi: MIGRATION_TO_ALMALINUX.md)

---

*Šablon — prave lozinke drži lokalno u test_sajtovi.md (gitignorisan)*
