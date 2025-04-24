<?php
class TibberBestPriceDeviceStarter extends IPSModule {
    public function Create() {
        //Never delete this line!
        parent::Create();
        
        // Konfigurations-Properties
        $this->RegisterPropertyInteger('PriceVarID', 0); // ID der Preis-Variable
        $this->RegisterPropertyInteger('TargetVarID', 0); // ID der Ziel-Variable (Schaltaktor)
        $this->RegisterPropertyInteger('Duration', 60); // Laufzeit in Minuten
        $this->RegisterPropertyString('EndTime', '22:00'); // Fertig um (HH:MM)

        // Variablen
        $this->RegisterVariableBoolean('StartCalculation', $this->Translate('Berechnung starten'), '', 1);
        $this->EnableAction('StartCalculation');
        $this->RegisterVariableString('StartTime', $this->Translate('Startzeitpunkt'), '', 2);
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
    }

    public function GetConfigurationForm() {
        return json_encode([
            'elements' => [
                [
                    'type' => 'SelectVariable',
                    'name' => 'PriceVarID',
                    'caption' => $this->Translate('Strompreis-Variable'),
                    'width' => '400px',
                    'variableType' => 3 // String
                ],
                [
                    'type' => 'SelectVariable',
                    'name' => 'TargetVarID',
                    'caption' => $this->Translate('Schalt-Variable (Gerät)'),
                    'width' => '400px',
                    'variableType' => 0 // Boolean
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'Duration',
                    'caption' => $this->Translate('Laufzeit (Minuten)')
                ],
                [
                    'type' => 'Time',
                    'name' => 'EndTime',
                    'caption' => $this->Translate('Fertig um')
                ]
            ],
            'actions' => []
        ]);
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
        $duration = $this->ReadPropertyInteger('Duration');
        $endTime = $this->ReadPropertyString('EndTime');

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
        $endTimestamp = strtotime($today . ' ' . $endTime);
        if ($endTimestamp < $now) {
            $endTimestamp = strtotime('+1 day', $endTimestamp);
        }
        $windowStart = $now;
        $windowEnd = $endTimestamp;

        // Besten Startzeitpunkt suchen
        $bestStart = null;
        $bestSum = PHP_INT_MAX;
        $runSeconds = $duration * 60;
        foreach ($prices as $i => $slot) {
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
        $this->RemoveSwitchingEvents();
        // Einschalten
        $switchOnEvent = @IPS_CreateEvent(1);
        if ($switchOnEvent) {
            IPS_SetName($switchOnEvent, 'Gerät einschalten');
            IPS_SetParent($switchOnEvent, $this->InstanceID);
            IPS_SetEventCyclicTimeFrom($switchOnEvent, (int)date('H', $startTimestamp), (int)date('i', $startTimestamp), 0);
            IPS_SetEventCyclicDateFrom($switchOnEvent, (int)date('d', $startTimestamp), (int)date('m', $startTimestamp));
            IPS_SetEventCyclic($switchOnEvent, 0, 0, 0, 0, 0, 0);
            IPS_SetEventActive($switchOnEvent, true);
            IPS_SetEventScript($switchOnEvent, 'SetValue(' . $targetVarID . ', true);');
        }
    }

    private function RemoveSwitchingEvents() {
        $children = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($children as $childID) {
            if (IPS_GetObject($childID)['ObjectType'] == 4) { // Event
                $name = IPS_GetName($childID);
                if ($name == 'Gerät einschalten') {
                    IPS_DeleteEvent($childID);
                }
            }
        }
    }

    private function AbortStart() {
        // Hier Logik zum Abbrechen eines geplanten Starts
        SetValue($this->GetIDForIdent('StartTime'), 'Abgebrochen');
        $this->RemoveSwitchingEvents();
    }
}
?>
