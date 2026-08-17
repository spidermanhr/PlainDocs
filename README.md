# Flat-File CMS / Bilješke

Ovo je brz, lagan i moderan **Flat-File CMS** sustav za upravljanje bilješkama, dokumentacijom i člancima. Dizajniran je za jednostavno stvaranje, organizaciju i pregled HTML stranica bez potrebe za bazom podataka (MySQL). Svi se podaci pohranjuju u strukturi čisto oblikovanih datoteka.

---

## 🚀 Ključne značajke

- **Bez baze podataka:** Sadržaj se pohranjuje direktno u `.html` datoteke unutar mape `pages/`.
- **Drvolika struktura (Tree View):** Podrška za neograničeno ugniježđivanje stranica i podstranica.
- **Sustav povijesti verzija (History/Backup):** Potpuna kontrola nad izmjenama uz mogućnost pregleda i vraćanja starih verzija.
- **Napredno prilagođavanje (Postavke):** Prilagodba teme, fontova, stilova tablica, veličine radne površine i učestalosti automatskog spremanja.
- **Automatsko spremanje:** Spremanje rada u pozadini bez stvaranja viška u povijesti verzija.
- **Predlošci za nove stranice:** Automatsko umetanje zadanog HTML ili tekstualnog predloška prilikom izrade nove stranice.
- **Pametne unutarnje poveznice:** Automatsko prepoznavanje i otvaranje internih `.html` poveznica unutar aplikacijskog sučelja (bez osvježavanja cijele stranice).
- **Sigurnost i čisti URL-ovi:** Podrška za hrvatska dijakritička slova (`č`, `ć`, `đ`, `š`, `ž`) koja se automatski pretvaraju u sigurne "slug" nazive datoteka.

---

## ⚙️ Detaljan pregled postavki

Klikom na **"⚙️ Postavke"** u bočnom izborniku otvara se modalni prozor s nizom opcija razvrstanih po cjelinama:

### 1. Izgled i Tema
- **Tema sučelja:** Odabir između **Svijetle** i **Tamne (Dark Mode)** teme. Tamna tema u potpunosti prilagođava pozadine, bočni izbornik, modale i CKEditor uređivač za ugodan rad noću.
- **Širina radne površine:** Odabir maksimalne širine prostora za čitanje i uređivanje:
  - *Standardna (750px)* – Idealno za brzo čitanje i bilješke.
  - *Proširena (1100px)* – Pogodno za dokumentaciju s tablicama.
  - *Puna širina (100%)* – WySIWYG iskustvo preko cijelog zaslona.

### 2. Tipografija i Tekst
- **Odabir fonta:** Prilagodba fonta cijele aplikacije i uređivača (npr. *Segoe UI*, *Arial*, *Georgia*, *Consolas / Monospace*...).
- **Veličina slova:** Povećavanje ili smanjivanje teksta radnog prostora.
- **Prored (Line-height):** Podešavanje visine linije teksta radi bolje preglednosti.

### 3. Stiliziranje Tablica
Prilagodite izgled svih HTML tablica unutar dokumentacije:
- **Stilovi tablice:**
  - *Grid* – Klasična tablica s punim obrubima.
  - *Clean* – Minimalistička tablica samo s horizontalnim linijama.
  - *Zebra* – Izmjenične boje redova radi lakšeg čitanja opsežnih podataka.
  - *Dense* – Zbijeni prikaz s manjim razmacima unutar ćelija.
- **Dodatne opcije tablica:**
  - *Hover efekt* – Isticanje reda prelazkom miša.
  - *Sticky header* – Fiksiranje zaglavlja tablice prilikom skrolanja po dugim dokumentima.
  - *Širina tablica* – Prilagodba na 100% širine ili automatsku širinu prema sadržaju.

### 4. Ponašanje Aplikacije & Automatsko spremanje
- **Automatsko spremanje (Auto-save):** Mogućnost uključivanja i postavljanja intervala (npr. svakih 30 s ili 60 s).
  > *Napomena:* Automatsko spremanje osvježava aktivnu datoteku i ne zatrpava mapu povijesti novim verzijama.
- **Pamćenje zadnje stranice:** Aplikacija automatski otvara zadnju uređivanu ili pregledavanu stranicu pri ponovnom dolasku.
- **Zadani predložak:** Mogućnost definiranja unaprijed pripremljenog HTML-a (npr. naslovi, tablice, podsjetnici) koji će se automatski učitati u uređivač pri izradi svake nove stranice.

---

## 🕒 Sustav povijesti i vraćanja verzija (History)

Sustav povijesti štiti vaše podatke od slučajnog brisanja ili neželjenih izmjena:

1. **Automatsko arhiviranje:** Prilikom svakog ručnog klika na **"💾 Spremi stranicu"**, sustav stvara arhivsku kopiju stranice u mapi `history/`.
2. **Vremenske oznake:** Svaka verzija imenovana je prema datumu i vremenu nastanka (npr. `stranica_2026-08-17_11-00-00.html`).
3. **Pregled starih verzija:**
   - Klikom na gumb **"🕒 Povijest"** otvara se popis svih dosadašnjih verzija za trenutno otvorenu stranicu.
   - Možete odabrati bilo koju staru verziju i pregledati njezin točan sadržaj.
4. **Restoriranje (Vraćanje):**
   - Odabirom opcije za vraćanje stare verzije, sustav zamjenjuje trenutno aktivnu stranicu odabranom arhivom.
   - Prije nego što vrati staru verziju, aplikacija **automatski sprema sigurnosnu kopiju trenutnog stanja**, tako da je postupak u potpunosti siguran i reverzibilan.

---

## 📥 Instalacija i postavljanje

1. Provjerite imate li instaliran **PHP (preporučeno PHP 7.4 ili 8.x)**.
2. Kopirajte sve datoteke projekta na vaš web server ili lokalno okruženje (Nginx, Apache, XAMPP...).
3. **Važno:** Provjerite ima li PHP proces dozvole pisanja (*write permissions*) u korijenskom direktoriju kako bi mogao automatski kreirati potrebne mape (`pages/`, `history/`, `conf/`).
   
   Na Linux serverima postavite dozvole:
   ```bash
   chmod -R 775 .
   chown -R www-data:www-data .
