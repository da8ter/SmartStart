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
        $this->RegisterPropertyString('EndTime', '22:00'); // Fertig um (Format: HH:MM, Default: 22:00)

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
        list($h, $m) = explode(':', $endTimeRaw);
        $endTimeSeconds = ((int)$h) * 3600 + ((int)$m) * 60;
        $today = date('Y-m-d');
        $endTimestamp = strtotime($today) + $endTimeSeconds;
        if ($endTimestamp < $now) {
            $endTimestamp = strtotime('+1 day', strtotime($today)) + $endTimeSeconds;
        }

        $windowStart = $now;
        $windowEnd = $endTimestamp;

        IPS_LogMessage('TibberDebug', 'Zeitfenster: ' .
            'Start=' . date('Y-m-d H:i:s', $windowStart) .
            ', Ende=' . date('Y-m-d H:i:s', $windowEnd) .
            ', Laufzeit=' . $duration . ' Minuten');

        // Besten Startzeitpunkt suchen
        $bestStart = null;
        $bestSum = PHP_INT_MAX;
        $runSeconds = $duration * 60;
        foreach ($prices as $i => $slot) {
            $slotStart = $slot['start'];
            
            // Überprüfen ob der Slot in der Zukunft liegt
            if ($slotStart < $now) {
                IPS_LogMessage('TibberDebug', "Slot $i: Übersprungen - Startzeitpunkt liegt in der Vergangenheit");
                continue;
            }

            $slotEnd = $slotStart + $runSeconds;
            
            // Überprüfen ob das Gerät rechtzeitig fertig wird
            if ($slotEnd > $windowEnd) {
                IPS_LogMessage('TibberDebug', "Slot $i: Übersprungen - Laufzeit würde Endzeitpunkt überschreiten");
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
