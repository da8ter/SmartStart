<?php
class SmartStart extends IPSModule {
    public function Create() {
        //Never delete this line!
        parent::Create();

        // Timer registrieren
        $this->RegisterTimer('StartDevice', 0, 'SMST_StartDevice($_IPS[\'TARGET\']);');
        
        // Konfigurations-Properties
        $this->RegisterPropertyInteger('PriceVarID', 0); // ID der Preis-Variable
        $this->RegisterPropertyInteger('TargetVarID', 0); // ID der Ziel-Variable (Schaltaktor)
        $this->RegisterPropertyInteger('Duration', 60); // Laufzeit in Minuten
        $this->RegisterPropertyString('EndTime', '{"hour":12,"minute":0,"second":0}'); // Fertig Zeit
        $this->RegisterPropertyBoolean('ImmediateSwitchOnNoSlot', false); // Sofort schalten, wenn kein Slot gefunden wird

        // Variablen
        $this->RegisterVariableBoolean('StartCalculation', $this->Translate('SmartStart'), '~Switch', 1);
        $this->EnableAction('StartCalculation');
        $this->RegisterVariableString('StartTime', $this->Translate('Startzeitpunkt'), '', 2);
    }

    public function ApplyChanges() {
        // Aktuelle Werte speichern, bevor sie überschrieben werden (falls Variablen existieren)
        $runtimeID = @$this->GetIDForIdent('Runtime');
        $endTimeID = @$this->GetIDForIdent('EndTimeValue');
        
        $runtimeExists = $runtimeID !== false && @IPS_ObjectExists($runtimeID);
        $endTimeExists = $endTimeID !== false && @IPS_ObjectExists($endTimeID);
        
        $currentRuntime = $runtimeExists ? GetValue($runtimeID) : 0;
        $currentEndTime = $endTimeExists ? GetValue($endTimeID) : '';
        
        // Standardaufruf der Elternklasse
        parent::ApplyChanges();
        
        // Zusätzliche Variablen für Laufzeit und Fertig Zeit erstellen
        $this->RegisterVariableInteger('Runtime', $this->Translate('Laufzeit'), '', 3);
        $this->EnableAction('Runtime'); // Variable aktivierbar machen
        
        // Für die Fertig Zeit verwenden wir ein String-Format HH:MM
        $this->RegisterVariableString('EndTimeValue', $this->Translate('Fertig Zeit'), '', 4);
        $this->EnableAction('EndTimeValue'); // Variable aktivierbar machen
        
        // SYNCHRONISIERUNG: Form -> Variablen
        // Wenn die Form geändert wurde, aktualisieren wir die Variablen
        $duration = $this->ReadPropertyInteger('Duration');
        $endTimeRaw = $this->ReadPropertyString('EndTime');
        
        // Runtime aktualisieren, wenn Form geändert wurde
        if ($runtimeExists) {
            if ($duration != $currentRuntime) {
                // Form wurde geändert, variable aktualisieren
                IPS_LogMessage('SmartStart', 'Laufzeit aus Form geändert: ' . $duration . ' Minuten');
                $this->SetValue('Runtime', $duration);
            }
        } else {
            // Neue Installation, Standard setzen
            $this->SetValue('Runtime', $duration);
        }
        
        // EndTime aktualisieren, wenn Form geändert wurde
        $parsed = json_decode($endTimeRaw, true);
        $hour = 0;
        $minute = 0;
            
        if (is_array($parsed) && isset($parsed['hour']) && isset($parsed['minute'])) {
            $hour = (int)$parsed['hour'];
            $minute = (int)$parsed['minute'];
            $newEndTimeString = sprintf('%02d:%02d', $hour, $minute);
            
            if (!$endTimeExists || $newEndTimeString != $currentEndTime) {
                // Form wurde geändert oder neue Installation
                IPS_LogMessage('SmartStart', 'Fertig Zeit aus Form geändert: ' . $newEndTimeString);
                $this->SetValue('EndTimeValue', $newEndTimeString);
            }
        }
    }


    public function RequestAction($Ident, $Value) {
        switch($Ident) {
            case 'StartCalculation':
                SetValue($this->GetIDForIdent('StartCalculation'), $Value);
                if($Value) {
                    $this->CalculateBestStartTime();
                } else {
                    $this->AbortStart();
                }
                break;
                
            case 'Runtime':
                // Laufzeit wurde geändert
                SetValue($this->GetIDForIdent('Runtime'), $Value);
                IPS_LogMessage('SmartStart', 'Laufzeit geändert auf: ' . $Value . ' Minuten');
                
                // Synchronisiere zurück zum Formular
                if ($this->ReadPropertyInteger('Duration') != $Value) {
                    IPS_SetProperty($this->InstanceID, 'Duration', $Value);
                    IPS_ApplyChanges($this->InstanceID);
                    IPS_LogMessage('SmartStart', 'Laufzeit im Formular aktualisiert: ' . $Value . ' Minuten');
                }
                break;
                
            case 'EndTimeValue':
                // Fertig-Zeit wurde geändert
                // Prüfen ob JSON Format vom Formular vorliegt
                $parsed = json_decode($Value, true);
                if (is_array($parsed) && isset($parsed['hour']) && isset($parsed['minute'])) {
                    // Es ist ein JSON vom Formular
                    $hour = (int)$parsed['hour'];
                    $minute = (int)$parsed['minute'];
                    $timeString = sprintf('%02d:%02d', $hour, $minute);
                    SetValue($this->GetIDForIdent('EndTimeValue'), $timeString);
                    IPS_LogMessage('SmartStart', 'Fertig-Zeit geändert auf: ' . $timeString);
                } 
                // Prüfen ob das Format korrekt ist (HH:MM) - Fall: direkte Eingabe in WebFront
                else if (preg_match('/^([0-1]?[0-9]|2[0-3]):([0-5][0-9])$/', $Value)) {
                    // Hier kommt eine direkte Änderung vom WebFront
                    SetValue($this->GetIDForIdent('EndTimeValue'), $Value);
                    IPS_LogMessage('SmartStart', 'Fertig-Zeit geändert auf: ' . $Value);
                    
                    // Synchronisiere zurück zum Formular
                    $parts = explode(':', $Value);
                    $hour = (int)$parts[0];
                    $minute = (int)$parts[1];
                    
                    // Aktuelles EndTime JSON aus dem Formular holen
                    $currentEndTime = json_decode($this->ReadPropertyString('EndTime'), true);
                    
                    // Prüfen ob sich die Werte geändert haben
                    if (!is_array($currentEndTime) || 
                        $currentEndTime['hour'] != $hour || 
                        $currentEndTime['minute'] != $minute) {
                        
                        // Neues JSON erstellen
                        $newEndTimeJson = json_encode([
                            'hour' => $hour,
                            'minute' => $minute,
                            'second' => 0
                        ]);
                        
                        // Im Formular aktualisieren
                        IPS_SetProperty($this->InstanceID, 'EndTime', $newEndTimeJson);
                        IPS_ApplyChanges($this->InstanceID);
                        IPS_LogMessage('SmartStart', 'Fertig-Zeit im Formular aktualisiert: ' . $Value);
                    }
                } else {
                    // Bei ungültigem Format eine Meldung loggen und nicht speichern
                    IPS_LogMessage('SmartStart', 'Ungültiges Zeit-Format: ' . $Value . '. Format muss HH:MM sein oder ein gültiges JSON-Objekt.');
                }
                break;
        }
    }

    private function CalculateBestStartTime() {
        // Debugausgabe aller registrierten Properties
        IPS_LogMessage('SmartStartDebug', 'Properties: PriceVarID=' . $this->ReadPropertyInteger('PriceVarID') .
            ', TargetVarID=' . $this->ReadPropertyInteger('TargetVarID') .
            ', Duration=' . $this->ReadPropertyInteger('Duration') .
            ', EndTime=' . $this->ReadPropertyString('EndTime'));

        $priceVarID = $this->ReadPropertyInteger('PriceVarID');
        if ($priceVarID == 0 || !IPS_VariableExists($priceVarID)) {
            SetValue($this->GetIDForIdent('StartTime'), 'Keine Preis-Variable gewählt');
            return;
        }

        // Preisdaten abrufen und validieren
        $json = GetValue($priceVarID);
        $prices = json_decode($json, true);
        if (!is_array($prices)) {
            SetValue($this->GetIDForIdent('StartTime'), 'Preisdaten ungültig');
            return;
        }

        // Zeitfenster berechnen
        $now = time();
        
        // Laufzeit aus der Modulvariable lesen
        $duration = GetValue($this->GetIDForIdent('Runtime'));
        IPS_LogMessage('SmartStartDebug', 'Laufzeit: ' . $duration . ' Minuten');
        $runSeconds = $duration * 60;

        // Fertig Zeit aus der Modulvariable lesen
        $endTimeString = GetValue($this->GetIDForIdent('EndTimeValue'));
        
        // Parse HH:MM format
        if (strpos($endTimeString, ':') !== false) {
            $parts = explode(':', $endTimeString);
            $hour = (int)$parts[0];
            $minute = isset($parts[1]) ? (int)$parts[1] : 0;
            $second = isset($parts[2]) ? (int)$parts[2] : 0;
            
            IPS_LogMessage('SmartStartDebug', 'Fertig Zeit: ' . $hour . ':' . $minute . ':' . $second);
        } else {
            // Fallback: Standardzeit aus Property nehmen
            $endTimeRaw = $this->ReadPropertyString('EndTime');
            $parsed = json_decode($endTimeRaw, true);
            $hour = 0;
            $minute = 0;
            $second = 0;
            
            if (is_array($parsed) && isset($parsed['hour']) && isset($parsed['minute'])) {
                $hour = (int)$parsed['hour'];
                $minute = (int)$parsed['minute'];
                $second = isset($parsed['second']) ? (int)$parsed['second'] : 0;
                
                // Aktualisieren wir auch die Variable für nächstes Mal
                $this->UpdateEndTime($endTimeRaw);
            }
            
            IPS_LogMessage('SmartStartDebug', 'Fertig Zeit (Fallback): ' . $hour . ':' . $minute . ':' . $second);
        }
        
        $endTimeSeconds = $hour * 3600 + $minute * 60 + $second;
        $today = date('Y-m-d');
        $endTimestamp = strtotime($today) + $endTimeSeconds;
        if ($endTimestamp < $now) {
            $endTimestamp = strtotime('+1 day', strtotime($today)) + $endTimeSeconds;
        }

        $windowStart = $now;
        $windowEnd = $endTimestamp;

        // Berechne den spätesten möglichen Startzeitpunkt
        $latestStart = $windowEnd - $runSeconds;
        
        IPS_LogMessage('SmartStartDebug', 'Zeitfenster: ' .
            'Start=' . date('Y-m-d H:i:s', $windowStart) .
            ', Ende=' . date('Y-m-d H:i:s', $windowEnd) .
            ', Spätester Start=' . date('Y-m-d H:i:s', $latestStart) .
            ', Laufzeit=' . $duration . ' Minuten');

        // Besten Startzeitpunkt suchen
        $bestStart = null;
        $bestSum = PHP_INT_MAX;
        $runSeconds = $duration * 60;
        foreach ($prices as $i => $slot) {
            $slotStart = $slot['start'];
            $slotEnd = $slotStart + $runSeconds;
            
            // Überprüfen ob der Slot in der Zukunft liegt
            if ($slotStart < $now) {
                IPS_LogMessage('SmartStartDebug', "Slot $i: Übersprungen - Startzeitpunkt liegt in der Vergangenheit");
                continue;
            }

            // Überprüfen ob der Startzeitpunkt vor dem Endzeitpunkt minus Laufzeit liegt
            if ($slotStart > ($windowEnd - $runSeconds)) {
                IPS_LogMessage('SmartStartDebug', "Slot $i: Übersprungen - Startzeitpunkt zu spät für Laufzeit");
                continue;
            }
            
            IPS_LogMessage('SmartStartDebug', "Slot $i: " .
                "Start=" . date('Y-m-d H:i:s', $slotStart) .
                ", Ende=" . date('Y-m-d H:i:s', $slotEnd) .
                ", Preis=" . number_format($slot['price'], 2) . " EUR/kWh");
            // Summe der Preise im Laufzeitfenster
            $sum = 0;
            $covered = 0;
            foreach ($prices as $j => $check) {
                $checkStart = $check['start'];
                $checkEnd = $check['end'];
                if ($checkEnd <= $slotStart || $checkStart >= $slotEnd) continue;
                // Überlappung berechnen
                $overlapStart = max($slotStart, $checkStart);
                $overlapEnd = min($slotEnd, $checkEnd);
                $overlap = max(0, $overlapEnd - $overlapStart);
                $sum += ($check['price'] * ($overlap / 3600));
                $covered += $overlap;
            }
            if ($covered >= $runSeconds && $sum < $bestSum) {
                $bestSum = $sum;
                $bestStart = $slotStart;
            }
        }
        $targetVarID = $this->ReadPropertyInteger('TargetVarID');
        if ($bestStart !== null) {
            SetValue($this->GetIDForIdent('StartTime'), date('H:i', $bestStart));
            $this->PlanSwitchingEvents($bestStart, $runSeconds);
        } else {
            SetValue($this->GetIDForIdent('StartTime'), 'Kein Startzeitpunkt gefunden');
            if ($this->ReadPropertyBoolean('ImmediateSwitchOnNoSlot')) {
                IPS_LogMessage('SmartStartDebug', 'Kein Startzeitpunkt gefunden, schalte Gerät sofort!');
                if ($targetVarID != 0 && IPS_VariableExists($targetVarID)) {
                    SetValue($targetVarID, true);
                } else {
                    IPS_LogMessage('SmartStartDebug', 'Sofort-Schaltung fehlgeschlagen: Keine gültige Ziel-Variable definiert!');
                }
            }
        }
        // Gerät nach Berechnung immer ausschalten
        if ($targetVarID != 0 && IPS_VariableExists($targetVarID)) {
            SetValue($targetVarID, false);
        }
    }

    private function PlanSwitchingEvents($startTimestamp, $runSeconds) {
        $targetVarID = $this->ReadPropertyInteger('TargetVarID');
        if ($targetVarID == 0 || !IPS_VariableExists($targetVarID)) {
            IPS_LogMessage('SmartStart', 'Keine Ziel-Variable für Schalten definiert!');
            return;
        }

        // Berechne die Millisekunden bis zum Start
        $now = time();
        $msToStart = ($startTimestamp - $now) * 1000;
        
        // Timer für das Einschalten setzen
        $this->SetTimerInterval('StartDevice', $msToStart);
        
        // Speichere die Ziel-Variable ID für den Timer
        $this->SetBuffer('TargetVarID', $targetVarID);
    }

    public function StartDevice() {
        $targetVarID = $this->GetBuffer('TargetVarID');
        if ($targetVarID && IPS_VariableExists($targetVarID)) {
            SetValue($targetVarID, true);
            // Timer deaktivieren nach Ausführung
            $this->SetTimerInterval('StartDevice', 0);
        }
    }

    private function AbortStart() {
        // Timer deaktivieren
        $this->SetTimerInterval('StartDevice', 0);
        SetValue($this->GetIDForIdent('StartTime'), 'Abgebrochen');
    }
    
    // Diese Funktion wird aufgerufen, wenn die Laufzeit im Formular geändert wird
    public function UpdateRuntime(int $duration) {
        // Aktualisiere die Runtime Variable
        SetValue($this->GetIDForIdent('Runtime'), $duration);
        IPS_LogMessage('SmartStart', 'Laufzeit aus Formular aktualisiert: ' . $duration . ' Minuten');
        return true;
    }
    
    // Diese Funktion wird aufgerufen, wenn die Fertig Zeit geändert wurde
    public function UpdateEndTime(string $endTimeJson) {
        // Der Parameter kann entweder ein JSON-Objekt vom Formular sein
        // oder ein direkter Zeitstring z.B. vom Skript
        
        $parsed = json_decode($endTimeJson, true);
        $timeString = '';
        
        if (is_array($parsed) && isset($parsed['hour']) && isset($parsed['minute'])) {
            // Es ist ein JSON-String vom Formular
            $hour = (int)$parsed['hour'];
            $minute = (int)$parsed['minute'];
            $timeString = sprintf('%02d:%02d', $hour, $minute);
        } else if (preg_match('/^([0-1]?[0-9]|2[0-3]):([0-5][0-9])$/', $endTimeJson)) {
            // Es ist bereits ein formatierter Zeitstring
            $timeString = $endTimeJson;
        } else {
            IPS_LogMessage('SmartStart', 'Ungültiges Format für Fertig Zeit: ' . $endTimeJson);
            return false;
        }
        
        // Aktualisiere die EndTimeValue Variable
        SetValue($this->GetIDForIdent('EndTimeValue'), $timeString);
        IPS_LogMessage('SmartStart', 'Fertig Zeit aktualisiert: ' . $timeString);
        return true;
    }
}
?>
