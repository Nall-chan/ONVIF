<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';

class ONVIFDigitalOutput extends ONVIFModuleBase
{
    const wsdl = 'devicemgmt-mod.wsdl';
    const TopicFilter = 'relay';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterAttributeArray('RelayOutputs', []);
        $this->RegisterPropertyBoolean('EmulateStatus', false);
    }

    public function ApplyChanges()
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
            $this->GetRelayOutputs();
            $Events = $this->ReadAttributeArray('EventProperties');
            if (count($Events) != 1) {
                $this->SetStatus(IS_EBASE + 1);
            } else {
                $this->SetStatus(IS_ACTIVE);
            }
            return;
        }
        $this->SetStatus(IS_ACTIVE);
    }

    public function SetRelayOutputState(string $Ident, bool $Value)
    {
        if (!array_key_exists($Ident, $this->ReadAttributeArray('RelayOutputs'))) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Invalid Ident'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $Events = $this->ReadAttributeArray('EventProperties');
        $EventProperty = array_pop($Events);

        switch (stristr($EventProperty['DataType'], ':')) {
            case ':RelayLogicalState':
                $SendValue = $Value ? 'active' : 'inactive';
                break;
            case ':bool':
            case ':boolean':
                $SendValue = $Value;
                break;
            default:
                trigger_error($this->Translate('Unsupported Datatype'), E_USER_NOTICE);
                return false;
        }

        $Params = [
            'RelayOutputToken' => $Ident,
            'LogicalState'     => $SendValue
        ];
        $ret = $this->SendData('', 'SetRelayOutputState', true, $Params);
        if ($ret == false) {
            return false;
        }
        if ($this->ReadPropertyBoolean('EmulateStatus')) {
            $this->SetValueBoolean($Ident, $Value);
        }
        return true;
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        return $this->SetRelayOutputState($Ident, $Value);
    }

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        $this->SendDebug('ReceiveEvent', $Data, 0);
        $Events = $this->ReadAttributeArray('EventProperties');
        $EventProperty = array_pop($Events);

        switch (stristr($EventProperty['DataType'], ':')) {
            case ':RelayLogicalState':
                $Value = $Data['DataValue'] == 'active';
                break;
            case ':bool':
            case ':boolean':
                $Value = ($Data['DataValue'] == 'true');
                break;
            default:
                trigger_error($this->Translate('Unsupported Datatype'), E_USER_NOTICE);
                return false;
        }
        $Ident = $Data['SourceValue'];
        $this->RegisterVariableBoolean($Ident, $Ident, '~Switch', 0);
        $this->SetValueBoolean($Ident, $Value);
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Form['elements'][0] = $this->GetConfigurationFormEventTopic($Form['elements'][0]);
        $Actions = [['type' => 'TestCenter']];
        $RelayOutputs = $this->ReadAttributeArray('RelayOutputs');
        foreach ($RelayOutputs as $RelayOutput) {
            $Expansion = [
                'type'     => 'ExpansionPanel',
                'caption'  => $this->Translate('Relay output: ') . $RelayOutput['token'],
                'expanded' => true,
                'items'    => []
            ];
            if (isset($RelayOutput['Properties']['Mode'])) {
                $Expansion['items'][] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '100px',
                            'caption' => $this->Translate('Mode:')
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $RelayOutput['Properties']['Mode']
                        ]
                    ]
                ];
                if ($RelayOutput['Properties']['Mode'] != 'Bistable') {
                    if (isset($RelayOutput['Properties']['DelayTime'])) {
                        $Expansion['items'][] = [
                            'type'  => 'RowLayout',
                            'items' => [
                                [
                                    'type'    => 'Label',
                                    'width'   => '100px',
                                    'caption' => $this->Translate('DelayTime:')
                                ],
                                [
                                    'type'    => 'Label',
                                    'caption' => $RelayOutput['Properties']['DelayTime']
                                ]
                            ]
                        ];
                    }
                }
            }
            if (isset($RelayOutput['Properties']['IdleState'])) {
                $Expansion['items'][] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '100px',
                            'caption' => $this->Translate('IdleState:')
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $RelayOutput['Properties']['IdleState']
                        ]
                    ]
                ];
            }
            $Actions[] = $Expansion;
        }
        $Form['actions'] = $Actions;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }

    protected function GetRelayOutputs()
    {
        $ret = $this->SendData('', 'GetRelayOutputs', true);
        if ($ret == false) {
            return false;
        }
        $RelayOutputs = [];
        if (is_array($ret->RelayOutputs)) {
            foreach ($ret->RelayOutputs as $RelayOutput) {
                $RelayOutputs[$RelayOutput->token] = json_decode(json_encode($RelayOutput), true);
            }
        } else {
            $RelayOutputs[$ret->RelayOutputs->token] = json_decode(json_encode($ret->RelayOutputs), true);
        }
        $this->SendDebug('RelayOutputs', $RelayOutputs, 0);
        $this->WriteAttributeArray('RelayOutputs', $RelayOutputs);
        foreach ($RelayOutputs as $Ident => $RelayOutput) {
            $this->RegisterVariableBoolean($Ident, $Ident, '~Switch', 0);
            $this->EnableAction($Ident);
        }
        return true;
    }
}
