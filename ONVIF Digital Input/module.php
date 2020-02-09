<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';

class ONVIFDigitalInput extends ONVIFModuleBase
{
    const wsdl = 'devicemgmt-mod.wsdl';
    const TopicFilter = 'input';

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyString('EventTopic') == '') {
            $this->SetStatus(IS_INACTIVE);
        } else {
            $Events = $this->GetEvents($this->ReadPropertyString('EventTopic'));
            $this->SendDebug('EventConfig', $Events, 0);
            if (count($Events) != 1) {
                $this->SetStatus(IS_EBASE + 1);
            } else {
                $this->SetStatus(IS_ACTIVE);
            }
        }
    }

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        $this->SendDebug('ReceiveEvent', $Data, 0);
        $Ident = $Data['SourceValue'];
        $Value = ($Data['DataValue'] == 'true');
        $this->RegisterVariableBoolean($Ident, $Ident, '', 0);
        $this->SetValue($Ident, $Value);
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Form['elements'][0] = $this->GetConfigurationFormEventTopic($Form['elements'][0]);
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }
}
