<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/AttributeArrayHelper.php') . '}');

/**
 * @property int $ParentID
 */
class ONVIFModuleBase extends IPSModule
{
    use \ONVIFModuleBase\BufferHelper,
        \ONVIFModuleBase\VariableProfileHelper,
        \ONVIFModuleBase\VariableHelper,
        \ONVIFModuleBase\DebugHelper,
        \ONVIFModuleBase\AttributeArrayHelper,
        \ONVIFModuleBase\InstanceStatus {
        \ONVIFModuleBase\InstanceStatus::MessageSink as IOMessageSink; // MessageSink gibt es sowohl hier in der Klasse, als auch im Trait InstanceStatus. Hier wird für die Methode im Trait ein Alias benannt.
        \ONVIFModuleBase\InstanceStatus::RegisterParent as IORegisterParent;
        \ONVIFModuleBase\InstanceStatus::RequestAction as IORequestAction;
    }
    const wsdl = '';
    const TopicFilter = '';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('EventTopic', '');
        $this->RegisterAttributeArray('EventProperties', []);
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
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        $EventTopic = $this->ReadPropertyString('EventTopic');
        if ($EventTopic == '') {
            $EventTopic = 'NOTHING';
        }
        $TopicFilter = '.*"Topic":"' . preg_quote(substr(json_encode($EventTopic), 1, -1)) . '.*';
        $this->SetReceiveDataFilter($TopicFilter);
        $this->SendDebug('SetReceiveDataFilter', $TopicFilter, 0);
        //$this->LogMessage('SetReceiveDataFilter: ' . $TopicFilter, KL_DEBUG);

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RegisterParent();
        $Events = $this->GetEvents($this->ReadPropertyString('EventTopic'));
        $this->WriteAttributeArray('EventProperties', $Events);
        $this->SendDebug('RegisterEvents', $Events, 0);

        //$this->ReloadForm();
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        return false;
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }

    protected function KernelReady()
    {
        $this->RegisterParent();
        if ($this->HasActiveParent()) {
            $this->ApplyChanges();
        }
    }

    protected function RegisterParent()
    {
        $this->IORegisterParent();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        if ($State == IS_ACTIVE) {
            $this->ApplyChanges();
            $this->ReloadForm();
        }
    }

    protected function GetEvents(string $Pattern = '', int $InstanceID = -1, array $SkippedTopics = [])
    {
        $answer = [];
        if ($this->HasActiveParent()) {
            if ($InstanceID == -1) {
                $InstanceID = $this->InstanceID;
            }
            $this->SendDebug('GetEvents Pattern', $Pattern, 0);
            $this->SendDebug('GetEvents SkippedTopics', $SkippedTopics, 0);
            $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'GetEvents', 'Pattern' => $Pattern, 'Instance' => $InstanceID, 'SkippedTopics' => $SkippedTopics]);
            $answer = $this->SendDataToParent($Data);
            if ($answer !== false) {
                $answer = unserialize($answer);
            }
            $this->SendDebug('Events Result', $answer, 0);
        }
        return $answer;
    }

    protected function GetCapabilities()
    {
        if ($this->HasActiveParent()) {
            $this->SendDebug('GetCapabilities', '', 0);
            $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'GetCapabilities']);
            $answer = $this->SendDataToParent($Data);
            if ($answer !== false) {
                $Result = unserialize($answer);
                $this->SendDebug('Capabilities Result', $Result, 0);
                return $Result;
            }
            $this->SendDebug('Capabilities Result', [], 0);
        }
        return [
            'VideoSources' => [],
            'HasOutput'    => false,
            'HasInput'     => false,
            'XAddr'        => [
                'Events'    => '',
                'Media'     => '',
                'PTZ'       => '',
                'Imaging'   => '',
                'Recording' => '',
                'Replay'    => ''
            ]
        ];
    }

    protected function GetCredentials()
    {
        if ($this->HasActiveParent()) {
            $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'GetCredentials']);
            $answer = $this->SendDataToParent($Data);
            if ($answer === false) {
                $this->SendDebug('GetCredentials', 'No valid answer', 0);
                throw new Exception($this->Translate('No valid answer.'), E_USER_NOTICE);
            }
            return unserialize($answer);
        }
        return ['Username' => '', 'Password' => ''];
    }

    protected function SendData(string $URI, string $Function, bool $UseLogin = false, array $Params = [], string $wsdl = '')
    {
        $this->SendDebug('Send URI', $URI, 0);
        if ($wsdl == '') {
            $wsdl = static::wsdl;
        }
        $this->SendDebug('Send WSDL', $wsdl, 0);
        $this->SendDebug('Send Function', $Function, 0);
        $this->SendDebug('Send Params', $Params, 0);
        $this->SendDebug('Send useLogin', $UseLogin, 0);
        $Ret = $this->SendDataToParent(json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'URI' => $URI, 'Function' => $Function, 'Params' => $Params, 'useLogin' => $UseLogin, 'wsdl' => $wsdl]));
        if ($Ret === false) {
            $this->SendDebug('Result', false, 0);
            return false;
        }
        $Result = unserialize($Ret);
        if (is_a($Result, 'SoapFault')) {
            $this->SendDebug('Result Error', $Result, 0);
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($Result->getMessage(), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $this->SendDebug('Result', $Result, 0);
        if ($Result === false){
            if (!$this->HasActiveParent()){
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Instance has no active parent.'), E_USER_NOTICE);
                restore_error_handler();
            } else {
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Unknown error.'), E_USER_NOTICE);
                restore_error_handler();
            }
            return false;
        }
        if ((count(get_object_vars($Result)) == 0)) {
            return true;
        }
        return $Result;
    }

    protected static function unparse_url($parsed_url)
    {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    protected function ModulErrorHandler($errno, $errstr)
    {
        $this->SendDebug('ERROR', utf8_decode($errstr), 0);
        echo $errstr."\r\n";
        //return true;
    }

    protected function GetConfigurationFormEventTopic(array $Form, bool $AddNothingIndex = false, array $SkippedTopics = [])
    {
        $Events = $this->GetEvents(static::TopicFilter, 0, $SkippedTopics);
        if (count($Events) == 0) {
            unset($Form['options']);
            $Form['type'] = 'ValidationTextBox';
            $Form['enabled'] = true;
        } else {
            if ($AddNothingIndex) {
                $SelectTopic[] = [
                    'caption' => 'nothing',
                    'value'   => ''
                ];
            } else {
                $SelectTopic = [];
            }
            foreach (array_keys($Events) as $Topic) {
                $SelectTopic[] = [
                    'caption' => $Topic,
                    'value'   => $Topic
                ];
            }
            $Form['options'] = $SelectTopic;
            $Form['enabled'] = (count($Events) > 1);
            if ($this->ReadPropertyString('EventTopic') == '') {
                $Form['enabled'] = true;
            }
        }
        return $Form;
    }

    protected function SetEventStatusVariable($PreName, $EventProperty, $Data)
    {
        if ($PreName != '') {
            $Name = $PreName . ' - ' . $Data['DataName'];
        } else {
            $Name = $Data['DataName'];
        }

        $Ident = str_replace([' - ', '/', '-', ':'], ['_', '_', '_', ''], $Name);
        switch (stristr($EventProperty['DataType'], ':')) {
            case ':boolean':
            case ':bool':
                $this->RegisterVariableBoolean($Ident, $Name, '', 0);
                $DataValue = false;
                if (strtolower($Data['DataValue']) === 'true') {
                    $DataValue = true;
                }
                if (intval($Data['DataValue']) === 1) {
                    $DataValue = true;
                }
                $this->SetValueBoolean($Ident, $DataValue);
                break;
            case ':float':
            case ':double':
                $this->RegisterVariableFloat($Ident, $Name, '', 0);
                $this->SetValueFloat($Ident, (float) $Data['DataValue']);
                break;
            case ':integer':
            case ':int':
                $this->RegisterVariableInteger($Ident, $Name, '', 0);
                $this->SetValueInteger($Ident, (int) $Data['DataValue']);
                break;
            case ':string':
                $this->RegisterVariableString($Ident, $Name, '', 0);
                $this->SetValueString($Ident, $Data['DataValue']);
                break;
        }
        return true;
    }
}
