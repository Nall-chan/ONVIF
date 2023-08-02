<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFModuleBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/AttributeArrayHelper.php') . '}');
require_once __DIR__ . '/wsdl.php';

/**
 * @property int $ParentID
 * @property string $EventTopic
 * @method void RegisterAttributeArray(string $name, mixed $Value, int $Size = 0)
 * @method array ReadAttributeArray(string $name)
 * @method void WriteAttributeArray(string $name, mixed $value)
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method void SetValueBoolean(string $Ident, bool $value)
 * @method void SetValueFloat(string $Ident, float $value)
 * @method void SetValueInteger(string $Ident, int $value)
 * @method void SetValueString(string $Ident, string $value)
 * @method void RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, int $MaxValue = -1, float $StepSize = 0)
 * @method void RegisterProfileFloatEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0)
 * @method void UnregisterProfile(string $Name)
 * @method void RegisterHook(string $WebHook)
 * @method void UnregisterHook(string $WebHook)
 * @uses \ONVIFModuleBase\BufferHelper
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
        $this->EventTopic = '';
        $this->RegisterAttributeArray('EventProperties', []);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
        }
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $EventTopic = $this->ReadPropertyString('EventTopic');
        $PullEvents = ($EventTopic != $this->EventTopic);
        //Never delete this line!
        parent::ApplyChanges();
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        if ($EventTopic == '') {
            $TopicFilter = '.*"Topic":"NOTHING".*';
        } else {
            $TopicFilter = '.*"Topic":"' . preg_quote(substr(json_encode($EventTopic), 1, -1)) . '.*';
        }
        $this->SetReceiveDataFilter($TopicFilter);
        $this->SendDebug('SetReceiveDataFilter', $TopicFilter, 0);

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RegisterParent();
        $Events = $this->GetEvents($EventTopic);
        $this->WriteAttributeArray('EventProperties', $Events);
        if ($PullEvents && ($this->HasActiveParent())) {
            $this->$EventTopic = $EventTopic;
            IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',"SetSynchronizationPoint",true);');
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        if ($Ident == 'SetSynchronizationPoint') {
            $this->SetSynchronizationPoint();
            return true;
        }
        return false;
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->UnregisterMessage(0, IPS_KERNELSTARTED);
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
            $this->EventTopic = $this->ReadPropertyString('EventTopic');
            $this->ApplyChanges();
            $this->ReloadForm();
        }
    }

    protected function GetEvents(string $Pattern = '', int $InstanceID = -1, array $SkippedTopics = [])
    {
        $answer = [];
        if ($this->ParentID == 0) {
            return $answer;
        }
        if (!$this->HasActiveParent()) {
            return $answer;
        }
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

        return $answer;
    }
    protected function SetSynchronizationPoint()
    {
        if ($this->ParentID > 0) {
            if ($this->HasActiveParent()) {
                $this->SendDebug('SetSynchronizationPoint', '', 0);
                $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'SetSynchronizationPoint']);
                $this->SendDataToParent($Data);
            }
        }
    }
    protected function GetCapabilities()
    {
        if ($this->ParentID > 0) {
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
        }
        return [
            'VideoSources'          => [],
            'AudioSources'          => [],
            'VideoSourcesJPEG'      => [],
            'AnalyticsTokens'       => [],
            'RelayOutputs'          => [],
            'DigitalInputs'         => [],
            'NbrOfVideoSources'     => 0,
            'NbrOfAudioSources'     => 0,
            'NbrOfOutputs'          => 0,
            'NbrOfInputs'           => 0,
            'NbrOfSerialPort'       => 0,
            'HasSnapshotUri'        => false,
            'HasRTSPStreaming'      => false,
            'RuleSupport'           => false,
            'AnalyticsModuleSupport'=> false,
            'XAddr'                 => [
                \ONVIF\NS::Event     => '',
                \ONVIF\NS::Media     => '',
                \ONVIF\NS::PTZ       => '',
                \ONVIF\NS::Imaging   => '',
                \ONVIF\NS::Analytics => '',
                \ONVIF\NS::DeviceIO  => '',
                \ONVIF\NS::Management=> '',
                \ONVIF\NS::Media2    => '',
                //'Recording' => '',
                //'Replay'    => ''
            ]
        ];
    }

    protected function GetCredentials()
    {
        if ($this->ParentID > 0) {
            if ($this->HasActiveParent()) {
                $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'GetCredentials']);
                $answer = $this->SendDataToParent($Data);
                if ($answer === false) {
                    $this->SendDebug('GetCredentials', 'No valid answer', 0);
                    throw new Exception($this->Translate('No valid answer.'), E_USER_NOTICE);
                }
                return unserialize($answer);
            }
        }
        return ['Username' => '', 'Password' => ''];
    }
    protected function GetUrl()
    {
        if ($this->ParentID > 0) {
            if ($this->HasActiveParent()) {
                $Data = json_encode(['DataID' => '{9B9C8DA6-BC89-21BC-3E8C-BA6E534ABC37}', 'Function' => 'GetUrl']);
                $answer = $this->SendDataToParent($Data);
                if ($answer === false) {
                    $this->SendDebug('GetUrl', 'No valid answer', 0);
                    throw new Exception($this->Translate('No valid answer.'), E_USER_NOTICE);
                }
                return unserialize($answer);
            }
        }
        return '';
    }
    protected function SendData(string $URI, string $Function, bool $UseLogin = false, array $Params = [], string $wsdl = '')
    {
        $this->SendDebug('Send URI', $URI, 0);
        if ($wsdl == '') {
            $wsdl = static::wsdl;
        }
        if ($this->ParentID == 0) {
            return false;
        }
        if (!$this->HasActiveParent()) {
            return false;
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
        if ($Result === false) {
            if (!$this->HasActiveParent()) {
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
        $user = isset($parsed_url['user']) ? urlencode($parsed_url['user']) : '';
        $pass = isset($parsed_url['pass']) ? ':' . urlencode($parsed_url['pass']) : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    protected function ModulErrorHandler($errno, $errstr)
    {
        $this->SendDebug('ERROR', utf8_decode($errstr), 0);
        echo $errstr . "\r\n";
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
        $NameParts = [];
        if ($PreName != '') {
            $NameParts[] = $PreName;
        }
        if (count($Data['Sources'])) {
            $DataName = [];
            foreach ($Data['Sources'] as $DataSource) {
                $DataName[] = $DataSource['Name'] . ':' . $DataSource['Value'];
            }
            $NameParts[] = implode(' - ', $DataName);
        }
        $PreName = implode('/', $NameParts);

        if ((count($Data['DataValues']) == 0) && (count($EventProperty['Data']))) {
            $Name = $PreName;
            if ($Name == '') {
                $Name = 'Event';
            }
            $Ident = str_replace([' - ', ':'], ['_', ''], $Name);
            $Ident = preg_replace('/[^a-zA-Z\d]/u', '_', $Ident);
            $this->RegisterVariableBoolean($Ident, $Name, '', 0);
            $this->SetValueBoolean($Ident, true);
            return true;
        }

        foreach ($Data['DataValues'] as $DataValue) {
            if (count($EventProperty['Data'])) {
                $DataIndex = array_search($DataValue['Name'], array_column($EventProperty['Data'], 'Name'));
                if ($DataIndex === false) {
                    continue; //Keine Beschreibung vom Datentyp vorhanden
                }
                $DataType = $EventProperty['Data'][$DataIndex]['Type'];
            } else {
                $DataType = 'xs:boolean';
            }
            if ($PreName == '') {
                $Name = $DataValue['Name'];
            } else {
                $Name = $PreName . ' - ' . $DataValue['Name'];
            }
            $Ident = str_replace([' - ', ':'], ['_', ''], $Name);
            $Ident = preg_replace('/[^a-zA-Z\d]/u', '_', $Ident);
            switch ($DataType) {
            case 'xs:boolean':
            case 'tt:boolean':
                $VariableValue = false;
                if (strtolower($DataValue['Value']) === 'true') {
                    $VariableValue = true;
                }
                if (intval($DataValue['Value']) === 1) {
                    $VariableValue = true;
                }
                $this->RegisterVariableBoolean($Ident, $Name, '', 0);
                $this->SetValueBoolean($Ident, $VariableValue);
            break;
            case 'tt:RelayLogicalState':
                $this->RegisterVariableBoolean($Ident, $Name, '', 0);
                $this->SetValueBoolean($Ident, (strtolower($DataValue['Value']) === 'active'));
                break;
            case 'xs:float':
            case 'xs:double':
            case 'xs:long':
            case 'tt:float':
            case 'tt:double':
            case 'tt:long':
                        $this->RegisterVariableFloat($Ident, $Name, '', 0);
                $this->SetValueFloat($Ident, (float) $DataValue['Value']);
                break;
            case 'xs:integer':
            case 'xs:int':
            case 'xs:decimal':
            case 'xs:short':
            case 'xs:unsignedLong':
            case 'xs:unsignedInt':
            case 'xs:unsignedShort':
            case 'xs:unsignedByte':
            case 'tt:integer':
            case 'tt:int':
            case 'tt:decimal':
            case 'tt:short':
            case 'tt:unsignedLong':
            case 'tt:unsignedInt':
            case 'tt:unsignedShort':
            case 'tt:unsignedByte':
                        $this->RegisterVariableInteger($Ident, $Name, '', 0);
                $this->SetValueInteger($Ident, (int) $DataValue['Value']);
                break;
            default:
                $this->RegisterVariableString($Ident, $Name, '', 0);
                $this->SetValueString($Ident, $DataValue['Value']);
                break;
            }
        }
        return true;
    }
}
