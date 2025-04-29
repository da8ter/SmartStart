# Smart Start

Das Modul schaltet ein Gerät automatisch zum günstigsten Zeitpunkt basierend auf aktuellen Preisdaten ein.

Die Preisinformationen müssen über eine externe Variable bereitgestellt werden, z.B. durch das Modul **Tibber V2** über die Variable „Preisvorschaudaten für Energie Optimierer“.

### **Beispielanwendung**

Eine **nicht** smarte Spülmaschine:

* Die Maschine wird über eine schaltbare Steckdose gesteuert.
* Nach dem manuellen Start des Spülprogramms wird über die Visualisierung die Variable **“Berechnung starten”**aktiviert.
* Das Modul berechnet den optimalen Startzeitpunkt und schaltet die Steckdose zunächst aus.
* Zum optimalen Zeitpunkt wird die Steckdose automatisch wieder eingeschaltet.
* Optional kann eine späteste Endzeit definiert werden (z.B. Spülmaschine soll bis spätestens 7 Uhr morgens fertig sein).

### **Funktionen**

* Berechnung des optimalen Startzeitpunkts anhand der Preisdaten
* Automatisches Einschalten eines Geräts (via Boolean-Schaltvariable)
* Vorgang kann jederzeit abgebrochen werden
* Gerät wird nach der Berechnung zunächst ausgeschaltet
* Einschalten erfolgt automatisch zum besten Zeitpunkt

### **Einrichtung**

1. Modul in IP-Symcon über die Modulverwaltung installieren: https://github.com/da8ter/SmartStart.git
2. Instanz erstellen.
3. Konfiguration:
  * Preis-Variable auswählen
  * Schalt-Variable (Boolean) auswählen
  * Laufzeit des Geräts sowie späteste Endzeit einstellen
  * Option **“Gerät sofort schalten, wenn kein Startzeitpunkt gefunden wird”** aktivieren, falls erforderlich (z.B. bei fehlenden Preisinformationen)
