<?php
class TibberBestPriceDeviceStarter extends IPSModule {
    public function Create() {
        //Never delete this line!
        parent::Create();

        // Timer registrieren
        $this->RegisterTimer('StartDevice', 0, 'TBPDS_StartDevice($_IPS[\'TARGET\']);');
        
        // Konfigurations-Properties
        $this->RegisterPropertyInteger('PriceVarID', 0); // ID der Preis-Variable
        $this->RegisterPropertyInteger('TargetVarID', 0); // ID der Ziel-Variable (Schaltaktor)
        $this->RegisterPropertyInteger('Duration', 60); // Laufzeit in Minuten
        $this->RegisterPropertyString('EndTime', '22:00:00'); // Fertig um (Format: HH:MM, Default: 22:00)
        $this->RegisterPropertyBoolean('ImmediateSwitchOnNoSlot', false); // Sofort schalten, wenn kein Slot gefunden wird

        // Variablen
        $this->RegisterVariableBoolean('StartCalculation', $this->Translate('Berechnung starten'), '', 1);
        $this->EnableAction('StartCalculation');
        $this->RegisterVariableString('StartTime', $this->Translate('Startzeitpunkt'), '', 2);
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
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
        }
    }

    private function CalculateBestStartTime() {
        // Debugausgabe aller registrierten Properties
        IPS_LogMessage('TibberDebug', 'Properties: PriceVarID=' . $this->ReadPropertyInteger('PriceVarID') .
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
        $duration = $this->ReadPropertyInteger('Duration');
        $runSeconds = $duration * 60;

        // Endzeitpunkt berechnen
        $endTimeRaw = $this->ReadPropertyString('EndTime');
        $hour = 0;
        $minute = 0;
        $second = 0;
        
        // Versuche JSON zu dekodieren
        $parsed = json_decode($endTimeRaw, true);
        if (is_array($parsed) && isset($parsed['hour']) && isset($parsed['minute'])) {
            $hour = (int)$parsed['hour'];
            $minute = (int)$parsed['minute'];
            $second = isset($parsed['second']) ? (int)$parsed['second'] : 0;
        } else if (strpos($endTimeRaw, ':') !== false) {
            // Fallback: String "HH:MM[:SS]"
            $parts = explode(':', $endTimeRaw);
            $hour = (int)$parts[0];
            $minute = isset($parts[1]) ? (int)$parts[1] : 0;
            $second = isset($parts[2]) ? (int)$parts[2] : 0;
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
        
        IPS_LogMessage('TibberDebug', 'Zeitfenster: ' .
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
                IPS_LogMessage('TibberDebug', "Slot $i: Übersprungen - Startzeitpunkt liegt in der Vergangenheit");
                continue;
            }

            // Überprüfen ob der Startzeitpunkt vor dem Endzeitpunkt minus Laufzeit liegt
            if ($slotStart > ($windowEnd - $runSeconds)) {
                IPS_LogMessage('TibberDebug', "Slot $i: Übersprungen - Startzeitpunkt zu spät für Laufzeit");
                continue;
            }
            
            IPS_LogMessage('TibberDebug', "Slot $i: " .
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
        if ($bestStart !== null) {
            SetValue($this->GetIDForIdent('StartTime'), date('H:i', $bestStart));
            $this->PlanSwitchingEvents($bestStart, $runSeconds);
        } else {
            SetValue($this->GetIDForIdent('StartTime'), 'Kein Startzeitpunkt gefunden');
            if ($this->ReadPropertyBoolean('ImmediateSwitchOnNoSlot')) {
                IPS_LogMessage('TibberDebug', 'Kein Startzeitpunkt gefunden, schalte Gerät sofort!');
                $this->PlanSwitchingEvents(time(), $runSeconds);
            }
        }
    }

    private function PlanSwitchingEvents($startTimestamp, $runSeconds) {
        $targetVarID = $this->ReadPropertyInteger('TargetVarID');
        if ($targetVarID == 0 || !IPS_VariableExists($targetVarID)) {
            IPS_LogMessage('TibberBestPriceDeviceStarter', 'Keine Ziel-Variable für Schalten definiert!');
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
}
?>
