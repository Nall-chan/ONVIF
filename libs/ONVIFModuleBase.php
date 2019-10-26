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
        $this->LogMessage('SetReceiveDataFilter: ' . $TopicFilter, KL_DEBUG);

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RegisterParent();
        $Events = $this->GetEvents($this->ReadPropertyString('EventTopic'));
        $this->WriteAttributeArray('EventProperties', $Events);
        $this->SendDebug('RegisterEvents', $Events, 0);

        //$this->ReloadForm();
    }

    protected function KernelReady()
    {
        $this->RegisterParent();
    }

    protected function RegisterParent()
    {
        $this->IORegisterParent();
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
        if ($this->HasActiveParent()) {
            if ($InstanceID == -1) {
                $InstanceID = $this->InstanceID;
            }
            $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'GetEvents', 'Pattern' => $Pattern, 'Instance' => $InstanceID, 'SkippedTopics' => $SkippedTopics]);
            $anwser = $this->SendDataToParent($Data);
            if ($anwser === false) {
                return [];
            }
            return unserialize($anwser);
        }
        return [];
    }

    protected function GetCapabilities()
    {
        if ($this->HasActiveParent()) {
            $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'GetCapabilities']);
            $anwser = $this->SendDataToParent($Data);
            if ($anwser === false) {
                $this->SendDebug('GetCapabilities', 'No valid answer', 0);
                throw new Exception($this->Translate('No valid answer.'), E_USER_NOTICE);
            }
            return unserialize($anwser);
        }
        return ['VideoSources' => [], 'HasOutput' => false, 'HasInput' => false, 'XAddr' => []];
    }

    protected function GetCredentials()
    {
        if ($this->HasActiveParent()) {
            $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'GetCredentials']);
            $anwser = $this->SendDataToParent($Data);
            if ($anwser === false) {
                $this->SendDebug('GetCredentials', 'No valid answer', 0);
                throw new Exception($this->Translate('No valid answer.'), E_USER_NOTICE);
            }
            return unserialize($anwser);
        }
        return ['Username' => '', 'Password' => ''];
    }

    protected function SendData(string $URI, string $Function, bool $UseLogin = false, array $Params = [])
    {
        $this->SendDebug('Send URI', $URI, 0);
        $this->SendDebug('Send Function', $Function, 0);
        $this->SendDebug('Send Params', $Params, 0);
        $this->SendDebug('Forward useLogin', $UseLogin, 0);
        $Ret = $this->SendDataToParent(json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'URI' => $URI, 'Function' => $Function, 'Params' => $Params, 'useLogin' => $UseLogin, 'wsdl' => static::wsdl]));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        if (is_a($Result, 'SoapFault')) {
            trigger_error($Result->getMessage(), E_USER_WARNING);
            return false;
        }
        $this->SendDebug('Result', $Result, 0);
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
        echo $errstr;
    }

    protected function GetConfigurationFormEventTopic(array $Form, bool $AddNothingIndex = false, array $SkippedTopics = [])
    {
        $Events = $this->GetEvents(static::TopicFilter, 0, $SkippedTopics);
        $this->SendDebug('GetEvents', $SkippedTopics, 0);
        $this->SendDebug('GetEvents', $Events, 0);
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

}
