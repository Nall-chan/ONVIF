<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';
/**
 * @property string $xAddr
 * @property string $wsdl
 */
class ONVIFDigitalOutput extends ONVIFModuleBase
{
    public const wsdl = \ONVIF\WSDL::Management; // default, wenn DeviceIO nicht genutzt
    public const TopicFilter = 'relay';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyBoolean(\ONVIF\Output\Property::EmulateStatus, false);
        $this->RegisterAttributeArray(\ONVIF\Output\Attribute::RelayOutputs, []);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyString(\ONVIF\Device\Property::EventTopic) == '') {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if ($this->HasActiveParent()) {
            $Capabilities = @$this->GetCapabilities();
            if (!$Capabilities) {
                $this->SetStatus(IS_EBASE + 1);
                return;
            }
            if ($Capabilities['XAddr'][\ONVIF\NS::DeviceIO]) {
                $this->xAddr = $Capabilities['XAddr'][\ONVIF\NS::DeviceIO];
                $this->wsdl = \ONVIF\WSDL::DeviceIO;
            } else {
                $this->xAddr = $Capabilities['XAddr'][\ONVIF\NS::Management];
                $this->wsdl = \ONVIF\WSDL::Management;
            }
            $this->WriteAttributeArray(\ONVIF\Output\Attribute::RelayOutputs, $Capabilities['RelayOutputs']);
            foreach ($Capabilities['RelayOutputs'] as $Name => $RelayOutput) {
                $Ident = str_replace([' - ', ':'], ['_', ''], (string) $Name);
                $Ident = preg_replace('/[^a-zA-Z\d]/u', '_', $Ident);
                $this->RegisterVariableBoolean($Ident, $Name, '~Switch', 0);
                $this->EnableAction($Ident);
            }
            $Events = $this->ReadAttributeArray(\ONVIF\Device\Attribute::EventProperties);
            if (count($Events) != 1) {
                $this->SetStatus(IS_EBASE + 1);
            } else {
                $this->SetStatus(IS_ACTIVE);
            }
            return;
        }
        $this->SetStatus(IS_ACTIVE);
    }

    public function SetRelayOutputState(string $Ident, bool $Value): bool
    {
        if (!array_key_exists($Ident, $this->ReadAttributeArray(\ONVIF\Output\Attribute::RelayOutputs))) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Invalid Ident'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $Params = [
            'RelayOutputToken' => $Ident,
            'LogicalState'     => $Value ? 'active' : 'inactive'
        ];
        $Result = $this->SendData($this->xAddr, 'SetRelayOutputState', true, $Params, $this->wsdl);

        if ($Result == false) {
            return false;
        }
        if ($this->ReadPropertyBoolean(\ONVIF\Output\Property::EmulateStatus)) {
            $this->SetValueBoolean($Ident, $Value);
        }
        return true;
    }

    public function RequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::RequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        $this->SetRelayOutputState($Ident, $Value);
        return;
    }

    public function ReceiveData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        $this->SendDebug('ReceiveEvent', $Data, 0);
        $Events = $this->ReadAttributeArray(\ONVIF\Device\Attribute::EventProperties);
        $EventProperty = array_pop($Events);
        $SourceIndex = array_search('tt:ReferenceToken', array_column($EventProperty['Sources'], 'Type'));
        if ($SourceIndex === false) {
            return '';
        }
        $SourceName = $EventProperty['Sources'][$SourceIndex]['Name'];
        $EventSourceIndex = array_search($SourceName, array_column($Data['Sources'], 'Name'));
        if ($EventSourceIndex === false) {
            return '';
        }
        $Ident = $Data['Sources'][$EventSourceIndex]['Value'];
        $DataIndex = array_search('tt:RelayLogicalState', array_column($EventProperty['Data'], 'Type'));
        if ($DataIndex === false) {
            return '';
        }
        $DataName = $EventProperty['Data'][$SourceIndex]['Name'];
        $EventDataIndex = array_search($DataName, array_column($Data['DataValues'], 'Name'));
        if ($EventDataIndex === false) {
            return '';
        }
        $Value = $Data['DataValues'][$EventDataIndex]['Value'];
        $this->RegisterVariableBoolean($Ident, $Ident, '~Switch', 0);
        $this->SetValueBoolean($Ident, ($Value == 'active'));
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
        $RelayOutputs = $this->ReadAttributeArray(\ONVIF\Output\Attribute::RelayOutputs);
        foreach ($RelayOutputs as $Token => $RelayOutput) {
            $Expansion = [
                'type'     => 'ExpansionPanel',
                'caption'  => $this->Translate('Relay output: ') . $Token,
                'expanded' => true,
                'items'    => []
            ];
            if (isset($RelayOutput['Mode'])) {
                $Expansion['items'][] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '100px',
                            'caption' => $this->Translate('Mode: ')
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $RelayOutput['Mode']
                        ]
                    ]
                ];
                if ($RelayOutput['Mode'] != 'Bistable') {
                    if (isset($RelayOutput['DelayTime'])) {
                        $Expansion['items'][] = [
                            'type'  => 'RowLayout',
                            'items' => [
                                [
                                    'type'    => 'Label',
                                    'width'   => '100px',
                                    'caption' => $this->Translate('DelayTime: ')
                                ],
                                [
                                    'type'    => 'Label',
                                    'caption' => $RelayOutput['DelayTime']
                                ]
                            ]
                        ];
                    }
                }
            }
            if (isset($RelayOutput['IdleState'])) {
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
                            'caption' => $RelayOutput['IdleState']
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
