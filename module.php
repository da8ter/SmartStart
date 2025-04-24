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
        $this->RegisterVariableBoolean('StartCalculation', 'Berechnung starten', '', 1);
        $this->EnableAction('StartCalculation');
        $this->RegisterVariableString('StartTime', 'Startzeitpunkt', '', 2);
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
                    'caption' => 'Strompreis-Variable',
                    'width' => '400px',
                    'variableType' => 3 // String
                ],
                [
                    'type' => 'SelectVariable',
                    'name' => 'TargetVarID',
                    'caption' => 'Schalt-Variable (Gerät)',
                    'width' => '400px',
                    'variableType' => 0 // Boolean
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'Duration',
                    'caption' => 'Laufzeit (Minuten)'
                ],
                [
                    'type' => 'Time',
                    'name' => 'EndTime',
                    'caption' => 'Fertig um'
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

    private function CalculateBestStartTime() {
        $priceVarID = $this->ReadPropertyInteger('PriceVarID');
        // Hier kommt später die Tibber-API Anbindung und Berechnungslogik
        $duration = $this->ReadPropertyInteger('Duration');
        $endTime = $this->ReadPropertyString('EndTime');
        // --- Platzhalter: Berechnung ---
        $bestStart = date('H:i', strtotime($endTime) - $duration * 60);
        SetValue($this->GetIDForIdent('StartTime'), $bestStart);
        // Hier Gerät einschalten planen (z.B. Ereignis setzen)
    }

    private function AbortStart() {
        // Hier Logik zum Abbrechen eines geplanten Starts
        SetValue($this->GetIDForIdent('StartTime'), 'Abgebrochen');
        // Hier geplantes Ereignis löschen
    }
}
?>
