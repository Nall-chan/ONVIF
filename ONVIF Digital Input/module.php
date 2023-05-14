<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';

class ONVIFDigitalInput extends ONVIFModuleBase
{
    public const wsdl = \ONVIF\WSDL::Management;
    public const TopicFilter = 'input';
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterAttributeArray('DigitalInputs', []);
    }
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyString('EventTopic') == '') {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if ($this->HasActiveParent()) {
            $Capabilities = @$this->GetCapabilities();
            if (!$Capabilities) {
                $this->SetStatus(IS_EBASE + 1);
                return;
            }
            $this->WriteAttributeArray('DigitalInputs', $Capabilities['DigitalInputs']);
            foreach ($Capabilities['DigitalInputs'] as $Name => $DigitalInput) {
                $Ident = str_replace([' - ', ':'], ['_', ''], $Name);
                $Ident = preg_replace('/[^a-zA-Z\d]/u', '_', $Ident);
                $this->RegisterVariableBoolean($Ident, $Name, '~Switch', 0);
            }

            $Events = $this->GetEvents($this->ReadPropertyString('EventTopic'));
            $this->SendDebug('EventConfig', $Events, 0);
            if (count($Events) != 1) {
                $this->SetStatus(IS_EBASE + 1);
            } else {
                $this->SetStatus(IS_ACTIVE);
            }
        }
    }

    public function ReceiveData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        $this->SendDebug('ReceiveEvent', $Data, 0);
        $EventProperties = $this->ReadAttributeArray('EventProperties');
        if (!array_key_exists($Data['Topic'], $EventProperties)) {
            return '';
        }
        if ((count($Data['Sources']) != 1) || (count($Data['DataValues']) != 1)) {
            return '';
        }

        $Name = $Data['Sources'][0]['Value'];
        $Ident = str_replace([' - ', ':'], ['_', ''], $Name);
        $Ident = preg_replace('/[^a-zA-Z\d]/u', '_', $Ident);

        $DataValue = $Data['DataValues'][0]['Value'];
        $VariableValue = false;
        if (strtolower($DataValue) === 'true') {
            $VariableValue = true;
        }
        if (intval($DataValue) === 1) {
            $VariableValue = true;
        }

        $this->RegisterVariableBoolean($Ident, $Name, '', 0);
        $this->SetValueBoolean($Ident, $VariableValue);
        return '';
    }

    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Form['elements'][0] = $this->GetConfigurationFormEventTopic($Form['elements'][0]);
        $Actions = [['type' => 'TestCenter']];
        $DigitalInputs = $this->ReadAttributeArray('DigitalInputs');
        foreach ($DigitalInputs as $Token => $DigitalInput) {
            $Expansion = [
                'type'     => 'ExpansionPanel',
                'caption'  => $this->Translate('Digital input: ') . $Token,
                'expanded' => true,
                'items'    => []
            ];
            if (isset($DigitalInput['IdleState'])) {
                $Expansion['items'][] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '100px',
                            'caption' => $this->Translate('IdleState: ')
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $DigitalInput['IdleState']
                        ]
                    ]
                ];
            }
            $Actions[] = $Expansion;
        }
        $Form['actions'] = array_merge($Actions, $Form['actions']);

        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }
}
