<?php

class ONVIFIO extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
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

    public function ForwardData($JSONString)
    {
        $Data = new ONVIFDATA($JSONString);
        $this->SendDebug('Forward', $Data, 1);
        return $this->Send($Data);
    }

    protected function SendToChilds(ONVIFData $Data)
    {
        $this->SendDebug('SendToChilds', $Data, 1);
        $this->SendDataToChildren($Data->ToJSON('{E23DD2CD-F098-268A-CE49-1CC04FE8060B}'));
    }

    protected function Send(ONVIFData $Data)
    {
        
    }

}
