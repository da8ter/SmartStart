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
        // Debug: Zeitfenster und Laufzeit ausgeben
        $now = time();
        $today = date('Y-m-d');
        $duration = $this->ReadPropertyInteger('Duration');
        // EndTime im Format HH:MM nach Sekunden seit Mitternacht umrechnen
        $endTimeRaw = $this->ReadPropertyString('EndTime');
        list($h, $m) = explode(':', $endTimeRaw);
        $endTimeSeconds = ((int)$h) * 3600 + ((int)$m) * 60;
        $runSeconds = $duration * 60;
        $today = date('Y-m-d');
        $now = time();
        $endTimestamp = strtotime($today) + $endTimeSeconds;
        if ($endTimestamp < $now) {
            $endTimestamp = strtotime('+1 day', strtotime($today)) + $endTimeSeconds;
        }
        $windowStart = $now;
        $windowEnd = $endTimestamp;
        IPS_LogMessage('TibberDebug', 'now: '.date('Y-m-d H:i:s', $now));
        IPS_LogMessage('TibberDebug', 'endTimestamp: '.date('Y-m-d H:i:s', $endTimestamp));
        IPS_LogMessage('TibberDebug', 'runSeconds: '.$runSeconds);
        IPS_LogMessage('TibberDebug', 'window: '.($windowEnd-$windowStart).' Sekunden');

        // --- Originalcode ab hier ---
        $priceVarID = $this->ReadPropertyInteger('PriceVarID');
        $duration = $this->ReadPropertyInteger('Duration');
        $endTimeRaw = $this->ReadPropertyString('EndTime');
        list($h, $m) = explode(':', $endTimeRaw);
        $endTimeSeconds = ((int)$h) * 3600 + ((int)$m) * 60;

        if ($priceVarID == 0 || !IPS_VariableExists($priceVarID)) {
            SetValue($this->GetIDForIdent('StartTime'), 'Keine Preis-Variable gewählt');
            return;
        }
        $json = GetValue($priceVarID);
        $prices = json_decode($json, true);
        if (!is_array($prices)) {
            SetValue($this->GetIDForIdent('StartTime'), 'Preisdaten ungültig');
            return;
        }

        $now = time();
        $today = date('Y-m-d');
        $endTimestamp = strtotime($today) + $endTimeSeconds;
        if ($endTimestamp < $now) {
            $endTimestamp = strtotime('+1 day', strtotime($today)) + $endTimeSeconds;
        }
        $windowStart = $now;
        $windowEnd = $endTimestamp;

        // Besten Startzeitpunkt suchen
        $bestStart = null;
        $bestSum = PHP_INT_MAX;
        $runSeconds = $duration * 60;
        foreach ($prices as $i => $slot) {
            IPS_LogMessage('TibberDebug', "Slot $i: start=".date('Y-m-d H:i:s', $slot['start']).", end=".date('Y-m-d H:i:s', $slot['end']).", price=".$slot['price']);
            $slotStart = $slot['start'];
            $slotEnd = $slotStart + $runSeconds;
            if ($slotEnd > $windowEnd) continue;
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
