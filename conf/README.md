# /conf Direktorij

Ovaj direktorij služi za pohranu konfiguracijskih datoteka sustava.

## Sadržaj

- `structure.json`: Datoteka u kojoj se automatski bilježi hijerarhijska struktura stranica (odnosi roditelj-dijete), redoslijed i nazivi `.html` datoteka unutar `/pages/` mape.

## Važne napomene

- Nemojte ručno mijenjati `structure.json` osim ako niste sigurni u strukturu JSON podatka, jer neispravan format može uzrokovati pogreške u prikazu navigacije.
- Ako se ova datoteka slučajno obriše, CMS će je automatski pokušati regenerirati skeniranjem sadržaja mape `/pages/`.
- Osigurajte da web poslužitelj ima dozvolu pisanja (write permissions) u ovu mapu.
