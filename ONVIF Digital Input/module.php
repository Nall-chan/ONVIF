<?php

class ONVIFDigitalInput extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{F40CA9A7-3B4D-4B26-7214-3A94B6074DFB}');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    protected function Send(ONVIFData $Data)
    {
        $this->SendDebug('Send', $Data, 1);
        $this->SendDataToParent($Data->ToJSON('{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}'));
    }

    public function ReceiveData($JSONString)
    {
        $Data = new ONVIFDATA($JSONString);
        $this->SendDebug('Receive', $Data, 1);
    }

}
