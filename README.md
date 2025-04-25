# Tibber Smart-Start

Dieses IP-Symcon Modul schaltet ein Gerät automatisch zum optimalen (günstigsten) Strompreis ein. Die Preisinformationen müssen über eine externe Preis-Variable bereitgestellt werden. (z.B. durch das Modul "Tibber V2" Variable "Preisvorschaudaten für Energie Optimierer")

Beispiel für einen Anwendungsfall:

Eine NICHT smarte Spülmaschine. Das Gerät wird über eine Schaltbare Steckdose ein- bzw. ausgeschaltet. Die Spülmaschine wird startklar gemacht und das Spülprogramm gestartet. Danach aktiviert man über die Visualisierung die Variable "Smart-Start". Der optimale Startzeitpunkt wird berechnet und das Gerät, bzw die schaltbare Steckdose wird ausgeschaltet. Zum berechneten Startzeitpunkt wird das Gerät eingeschaltet. Wenn man eine Zielzeit hat kann diese im Konfigurationsformular eingestellt werden. Beispiel: Die Spülmaschine soll Nachts laufen und spätestens um 7 Uhr fertig sein.

## Funktionen
- Berechnet den besten Startzeitpunkt für ein Gerät basierend auf Preisdaten
- Schaltet das Gerät zur berechneten Zeit ein (Boolean-Variable)
- Der Vorgang kann jederzeit abgebrochen werden
- Das Modul schaltet das Gerät nach der Berechnung des günstigsten Zeitpunktes AUS. Zum berechneten Zeitpunkt wird das Gerät dann eingeschaltet.

## Einrichtung
1. Modul in IP-Symcon installieren
2. Im Konfigurationsformular:
   - Preis-Variable auswählen  
   - Schalt-Variable auswählen (Boolean)
   - Laufzeit und späteste Endzeit einstellen
   - Der Schalter "Gerät sofort schalten..." aktiviert das zu schaltende Gerät sofort, wenn kein Startzeitpunkt gefunden wird.

## Hinweise
- Das Modul schaltet das Gerät nach der Berechnung des günstigsten Zeitpunktes AUS. Zum berechneten Zeitpunkt wird das Gerät eingeschaltet.

## Lizenz
MIT-Lizenz
