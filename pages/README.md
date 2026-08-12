# /pages Direktorij

Ovaj direktorij sadrži fizičke HTML datoteke koje predstavljaju sadržaj pojedinačnih stranica/bilježaka kreiranih kroz CMS.

## Sadržaj

- Svaka kreirana stranica sprema se kao zasebna `.html` datoteka (npr. `moja-prva-stranica.html`).
- Nazivi datoteka ("slug") automatski se generiraju iz naslova stranice, uz podršku za hrvatske znakove.

## Važne napomene

- U ove datoteke se direktno sprema HTML sadržaj koji generira CKEditor 5.
- Ručno dodavanje ili brisanje `.html` datoteka u ovoj mapi bit će automatski usklađeno s konfiguracijskom datotekom sustava prilikom sljedećeg osvježavanja aplikacije.
- Osigurajte da web poslužitelj ima dozvolu pisanja (write permissions) u ovu mapu.
