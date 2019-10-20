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
        $this->RegisterAttributeBoolean('useRelayLogicalState', false);
        $this->RegisterPropertyBoolean('EmulateStatus', false);
    }

//
//    public function Destroy()
//    {
//        //Never delete this line!
//        parent::Destroy();
//    }
//
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        @$this->GetRelayOutputs();
        if ($this->ReadPropertyString('EventTopic') == '') {
            $this->SetStatus(IS_INACTIVE);
        } else {
            $Events = $this->GetEvents($this->ReadPropertyString('EventTopic'));
            $this->SendDebug('EventConfig', $Events, 0);
            if (count($Events) != 1) {
                $this->SetStatus(IS_EBASE + 1);
                echo count($Events);
            } else {
                $this->SetStatus(IS_ACTIVE);
                $Event = array_shift($Events);
                $this->WriteAttributeBoolean('useRelayLogicalState', (strpos($Event['Data']['Type'], 'LogicalState') > 0));
            }
        }
       // $this->ReloadForm();
    }

    protected function GetRelayOutputs()
    {
        $ret = $this->SendData('', 'GetRelayOutputs', true);
        if ($ret == false) {
            return false;
        }
        //$this->LogMessage(print_r($ret), KL_NOTIFY);
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

    public function SetRelayOutputState(string $Ident, bool $Value)
    {
        if (!array_key_exists($Ident, $this->ReadAttributeArray('RelayOutputs'))) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error('Invalid Ident', E_USER_NOTICE);
            restore_error_handler();
            return false;
        }

        if ($this->ReadAttributeBoolean('useRelayLogicalState')) {
            $SendValue = $Value ? 'active' : 'inactive';
        } else {
            $SendValue = $Value;
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
            $this->SetValue($Ident, $Value);
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
        $Data = parent::ReceiveData($JSONString);
        $Ident = $Data['Source']['Value'];
        $Value = ($Data['Data']['Value'] == 'active');
        $this->SetValue($Ident, $Value);
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
                'caption'  => 'Relay output: ' . $RelayOutput['token'],
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
                            'caption' => 'Mode:'
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
                                    'caption' => 'DelayTime:'
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
                            'caption' => 'IdleState:'
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

}
