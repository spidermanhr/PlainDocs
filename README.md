# Flat-File CMS / Bilješke

Ovo je jednostavan, lagan **Flat-File CMS** sustav za upravljanje bilješkama, dizajniran za brzo stvaranje, uređivanje i organizaciju HTML stranica bez potrebe za bazom podataka (MySQL).

## Značajke

- **Bez baze podataka:** Podaci se spremaju kao fizičke `.html` datoteke u mapi `pages/`.
- **Struktura podataka:** Koristi `conf/structure.json` za pamćenje hijerarhije stranica (parent-child odnosi).
- **Bogato uređivanje:** Integriran **CKEditor 5** za jednostavno pisanje i formatiranje sadržaja.
- **Hijerarhijski prikaz:** Sidebar s "drvolikim" prikazom (tree view) stranica.
- **Automatsko čišćenje:** Automatski kreira potrebne mape i čisti nepostojeće datoteke iz konfiguracije.
- **Sigurnost:** Automatsko generiranje "slug" naziva za datoteke (podrška za hrvatske znakove).

## Instalacija

1. Provjerite imate li instaliran PHP (preporučeno 7.4+ ili 8.x).
2. Kopirajte sve datoteke na vaš server.
3. **Važno:** Osigurajte da PHP proces ima dozvolu pisanja (write permissions) u korijenskoj mapi projekta kako bi se mogle automatski kreirati mape `pages/` i `conf/`.
    - Na Linux serverima: `chmod -R 775 .` (ili podesite vlasnika mape na web server korisnika, npr. `www-data`).

## Kako koristiti

1. Otvorite `index.php` u pregledniku.
2. Kliknite **"+ Nova Stranica"** za početak.
3. Unesite naslov, odaberite roditelja (ako želite pod-stranicu) i uredite sadržaj u editoru.
4. Kliknite **"Spremi Stranicu"**.
5. U sidebar-u možete kliknuti na bilo koju stranicu da biste je pregledali, uredili ili izbrisali.

## Tehničke napomene

- **Dijagnostika:** Skripta trenutno ima uključene `ini_set('display_errors', 1);`. Kada se uvjerite da sve radi ispravno na vašem serveru, **uklonite** ta dva reda s početka datoteke radi sigurnosti.
- **Struktura:**
    - `/pages/`: Ovdje se čuvaju vaše kreirane stranice.
    - `/conf/structure.json`: Ovdje se čuva hijerarhija. Ako se datoteka izbriše, CMS će je pokušati ponovno izgraditi skeniranjem mape `/pages/`.
- **Editor:** Koristi se CDN verzija CKEditor 5 (Classic build).

## Licenca

Ovaj projekt je besplatan za korištenje i prilagodbu.
