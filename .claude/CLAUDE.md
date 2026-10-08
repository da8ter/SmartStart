# SmartStart

Symcon-Modul, das ein Gerät (z. B. Spülmaschine an einer schaltbaren Steckdose) zum günstigsten Zeitpunkt vor einer Fertig-Zeit einschaltet. Die Preise kommen aus einer fremden Variable, typischerweise „Preisvorschaudaten für Energie Optimierer“ des Moduls Tibber V.2. Öffentliches Repo `da8ter/TibberSmartStart` (die `url` in `library.json`/`module.json` nennt `da8ter/SmartStart.git`), Arbeitszweig `main`.

Projektwissen: **`.claude/docs/README.md`**, Befunde und Offenes in `.claude/docs/stand.md`.

## Aufbau

- **`SmartStart/`** (Präfix `SMST`, Typ 3): einziges Modul. `module.php` enthält alles – Rechnung (`CalculateBestStartTime`), Einmal-Zeitgeber `StartDevice`, Abgleich Formular ↔ Variablen (`Runtime`, `EndTimeValue`).
- Altbestand: `extends IPSModule` ohne `declare(strict_types=1)`, untypisierte Signaturen, Variablenprofil `~Switch`, deutsche Schlüssel in `locale.json`. Beim Umbau auf Module Strict die Overrides erst typisieren, wenn die Basisklasse `IPSModuleStrict` ist (Plattformwissen `module-strict-und-php.md`).
- Eingangsformat der Preisvariable: JSON-Liste mit `start`, `end` (Unix-Sekunden) und `price` je Abschnitt; Vertrag auf der Gegenseite im Repo `da8ter/TibberV2` unter `.claude/docs/entscheidungen/preisdaten-fuer-optimierer.md`.

## Prüfen

Kein Prüfstand vorhanden.

```bash
php -l SmartStart/module.php
python3 -m json.tool SmartStart/form.json > /dev/null
```

Ein neuer Prüfstand für die Rechnung (Fenster, Überlappung, fehlende Preise) gehört nach `tests/` und braucht eine kleine Symcon-Attrappe.

## Regeln

- **Commits:** ein Thema je Commit, deutsche Botschaft, **ohne** Co-Authored-By-Zeile. Prüfungen vorher. Die bisherige Historie besteht aus Web-Uploads („Add files via upload“); neue Commits lokal und sprechend.
- **Nie** `git checkout`/`git restore` auf Dateien: Arbeitskopien enthalten nicht committete Arbeit.
- **Push und Release nur auf Zuruf.** Release: `build` und `date` in `library.json` setzen (stehen noch auf 0).
- **Öffentliches Repo:** keine Instanz-IDs, Adressen, Token, Pfade unter `/Users/`, keine Daten des eigenen Haushalts.
- **Doku nachziehen:** Ändert ein Commit etwas aus `docs/`, im selben Commit anpassen und das „Stand“-Datum erneuern.

## Plattformwissen

Gemessenes Symcon-Verhalten für alle Module: https://github.com/da8ter/SymDo-Family-Organizer/tree/SymDo-Beta/.claude/docs/plattform (lokal `../List/.claude/docs/plattform/`). Für dieses Modul besonders `module-strict-und-php.md` und `timer.md`.
