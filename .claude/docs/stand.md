# Stand

Befunde aus der Durchsicht des Codes am 08.10.2026 und offene Punkte. Erledigtes wird hier gestrichen, nicht abgehakt. Veröffentlicht: Version 1.0.0, `build`/`date` noch 0.

## Befunde im Code

- **Schalten per `SetValue` statt `RequestAction`.** `CalculateBestStartTime()` und `StartDevice()` setzen die Schalt-Variable mit `SetValue`. Das überschreibt bei einer Variable, die zu einer Geräte-Instanz gehört (Steckdose), nur den gespeicherten Wert; geschaltet wird nichts. Es wirkt nur, wenn ein eigenes Ereignis auf die Variable reagiert.
- **„Sofort schalten, wenn kein Startzeitpunkt gefunden wird“ wird sofort zurückgenommen.** Nach dem Einschalten läuft am Ende von `CalculateBestStartTime()` immer „Gerät nach Berechnung ausschalten“ (`SetValue(..., false)`), auch in diesem Zweig.
- **Platzhalter-Preise werden als echte Preise gerechnet.** Tibber V.2 (Branches `beta` und `Testing`, nicht `main`) füllt im Stundenmodus Stunden ohne Preis mit `price` 0 und leerem `level` auf (siehe Vertrag im Repo `da8ter/TibberV2`). SmartStart prüft nur, ob das Laufzeitfenster lückenlos abgedeckt ist, und hält 0 für den günstigsten Preis: Vor der Veröffentlichung der Folgetagspreise landet der Start deshalb bevorzugt in Stunden ohne Preis. Abhilfe: Einträge mit leerem `level` oder ohne Preis als Lücke behandeln.
- **Startkandidaten sind nur Abschnittsanfänge in der Zukunft.** Der laufende Abschnitt wird übersprungen, der früheste Start ist der nächste Abschnittsanfang.
- **Rückfall auf die Property, wenn `EndTimeValue` kein `:` enthält**, ruft `UpdateEndTime()` und schreibt die Variable nach; ein ungültiger Wert über `RequestAction` wird nur geloggt.
- **Viel Protokoll im Meldungsfenster:** jede Rechnung schreibt je Abschnitt eine Zeile `SmartStartDebug` per `IPS_LogMessage`; besser `SendDebug`.

## Offen

- Umbau auf `IPSModuleStrict` mit Darstellungen statt `~Switch` (Hausstandard der übrigen Module).
- Übersetzungen: `locale.json` hat deutsche Schlüssel; „Fertig Zeit“, „Laufzeit“ und die Statustexte in `StartTime` („Keine Preis-Variable gewählt“, „Abgebrochen“ …) sind nicht übersetzt, der Schlüssel „Fertig um“/„Gerät soll fertig sein um“ wird nirgends benutzt.
- Formularhinweis nennt als Preisquelle das Modul „Strompreis (Vorhersage)“, die README Tibber V.2; ob beide dasselbe Format liefern, ist hier nicht prüfbar.
- `url` in `library.json` und `module.json` zeigt auf `da8ter/SmartStart.git`, das Repo heißt `da8ter/TibberSmartStart`.
- Kein Prüfstand.

Stand: geprüft gegen den Code am 08.10.2026
