<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';

class ONVIFEvents extends ONVIFModuleBase
{
    const wsdl = '';
    public function Create()
    {
        parent::Create();
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
        } else {
            $Events = $this->GetEvents($this->ReadPropertyString('EventTopic'));
            $this->SendDebug('EventConfig', $Events, 0);
            if (count($Events) == 0) {
                $this->SetStatus(IS_INACTIVE);
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
        $EventProperties = $this->ReadAttributeArray('EventProperties');
        if (!array_key_exists($Data['Topic'], $EventProperties)) {
            return false;
        }
        $PreName = str_replace($this->ReadPropertyString('EventTopic'), '', $Data['Topic']);
        return $this->SetEventStatusVariable($PreName, $EventProperties[$Data['Topic']], $Data);
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Form['elements'][0] = $this->GetConfigurationFormEventTopic($Form['elements'][0], false, [':VideoSource', ':PTZ', '/Relay', '/DigitalInput']);
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }
}
