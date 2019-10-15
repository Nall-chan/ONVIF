<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';

class ONVIFDigitalOutput extends ONVIFModuleBase
{
    const wsdl = 'devicemgmt-mod.wsdl';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterAttributeArray('RelayOutputs', []);
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
        $this->SetReceiveDataFilter('.*"Topic":"' . preg_quote('tns1:Device\/Trigger\/Relay') . '".*');
        $this->SendDebug('SetReceiveDataFilter', '.*"Topic":"' . preg_quote('tns1:Device\/Trigger\/Relay') . '".*', 0);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        @$this->GetRelayOutputs();
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
        $Params = [
            'RelayOutputToken' => $Ident,
            'LogicalState'     => $Value ? 'active' : 'inactive'
        ];
        $ret = $this->SendData('', 'SetRelayOutputState', true, $Params);
        if ($ret == false) {
            return false;
        }
        //$this->SetValue($Ident, $Value);
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
