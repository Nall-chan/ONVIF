<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';

class ONVIFDigitalInput extends ONVIFModuleBase
{
    const wsdl = 'devicemgmt-mod.wsdl';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        //$this->RegisterAttributeArray('InputConnectors', []);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*"Topic":"' . preg_quote('tns1:Device\/Trigger\/DigitalInput') . '".*');
        $this->SendDebug('SetReceiveDataFilter', '.*"Topic":"' . preg_quote('tns1:Device\/Trigger\/DigitalInput') . '".*', 0);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        //@$this->GetOutputs();
    }

    public function ReceiveData($JSONString)
    {
        $Data = parent::ReceiveData($JSONString);
        $Ident = $Data['Source']['Value'];
        $Value = ($Data['Data']['Value'] == 'true');
        $this->RegisterVariableBoolean($Ident, $Ident, '', 0);
        $this->SetValue($Ident, $Value);
    }

}
