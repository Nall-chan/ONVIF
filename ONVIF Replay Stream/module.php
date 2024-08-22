<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';
/**
 * @property array $Recordings
 * @property array $Capabilities
 */
class ONVIFReplayStream extends ONVIFModuleBase
{
    public const wsdl = \ONVIF\WSDL::Replay;
    public const TopicFilter = 'RecordingConfig/JobState';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString(\ONVIF\Replay\Property::VideoSource, '');
        $this->RegisterPropertyString(\ONVIF\Replay\Property::RecordingToken, '');
        $this->Recordings = [];
        $this->Capabilities = [];
    }

    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if (!$this->HasActiveParent() || ($this->ParentID == 0)) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Instance has no active parent.'
                    ]]
                ]
            ];
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $Recordings = $this->Recordings;
        if (!count($Recordings)) {
            $Recordings = @$this->GetRecordings();
        }

        if ($Recordings == false) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Error on read recordings.'
                    ]]
                ]
            ];
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $VideoSourcesOptions = [];
        $RecordingOptions = [];
        $RecordingOptions[] = [
            'caption' => 'none',
            'value'   => ''
        ];
        $ActualRecording = null;
        foreach ($Recordings as $VideoSource => $SourceRecordings) {
            $VideoSourcesOptions[] = [
                'caption' => $VideoSource,
                'value'   => $VideoSource
            ];
            if ($this->ReadPropertyString(\ONVIF\Replay\Property::VideoSource) == $VideoSource) {
                foreach ($SourceRecordings as $RecordingToken) {
                    $RecordingOptions[] = [
                        'caption' => $RecordingToken,
                        'value'   => $RecordingToken
                    ];
                    if ($this->ReadPropertyString(\ONVIF\Replay\Property::RecordingToken) == $RecordingToken) {
                        $ActualRecording = $RecordingToken;
                    }
                }
            }
        }
        $Form['elements'][0]['options'] = $VideoSourcesOptions;
        $Form['elements'][1]['options'] = $RecordingOptions;
        $Form['elements'][2] = $this->GetConfigurationFormEventTopic($Form['elements'][2]);
        $this->SendDebug('ActualRecording', $ActualRecording, 0);
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    public function RequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::RequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        switch ($Ident) {
            case 'RefreshRecordingTokenForm':
                $this->RefreshRecordingTokenForm($Value);
                return;
        }
        if (stripos($Ident, 'JobState') === 0) {
            $this->SetRecordingJobMode(substr($Ident, 8), (bool) $Value);
        }
        $this->SendDebug('Ident', $Ident, 0);
        /*
        if (stripos($Ident, 'JobConfig') === 0 ){
            $this->SetRelayOutputState(substr($Ident,9), (bool)$Value);
        }
         */
        return;
    }

    public function ReceiveData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        $this->SendDebug('ReceiveEvent', $Data, 0);
        $EventProperties = $this->ReadAttributeArray(\ONVIF\Device\Attribute::EventProperties);
        $EventProperty = array_pop($EventProperties);
        $SourceIndex = array_search('tt:RecordingJobReference', array_column($EventProperty['Sources'], 'Type'));
        if ($SourceIndex === false) {
            return '';
        }
        $SourceName = $EventProperty['Sources'][$SourceIndex]['Name'];
        $EventSourceIndex = array_search($SourceName, array_column($Data['Sources'], 'Name'));
        if ($EventSourceIndex === false) {
            return '';
        }
        $Ident = 'JobState' . $Data['Sources'][$EventSourceIndex]['Value'];
        $Name = 'JobState:' . $Data['Sources'][$EventSourceIndex]['Value'];
        $DataName = $EventProperty['Data'][$SourceIndex]['Name'];
        $EventDataIndex = array_search($DataName, array_column($Data['DataValues'], 'Name'));
        if ($EventDataIndex === false) {
            return '';
        }
        $Value = $Data['DataValues'][$EventDataIndex]['Value'];
        $this->RegisterVariableBoolean($Ident, $Name, '~Switch', 0);
        $this->EnableAction($Ident);
        $this->SetValueBoolean($Ident, (strtolower($Value) == 'active'));
        return '';
    }

    protected function InitFilterAndEvents(): void
    {
        parent::InitFilterAndEvents();
        $this->Capabilities = @$this->GetCapabilities();
        @$this->GetRecordings();
        if ($this->ReadPropertyString(\ONVIF\Replay\Property::VideoSource) == '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetMedia('');
            return;
        }
        if ($this->ReadPropertyString(\ONVIF\Replay\Property::RecordingToken) == '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetMedia('');
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        /**
         * @todo
         */
        $this->GetRecordingSummary();
        //$this->GetRecordingInformation();
        //$this->GetReplayConfiguration();
        $this->GetRecordingJobs();
        //$this->GetRecordingConfiguration();
        //$this->GetRecordingOptions();
        if (count($this->Recordings)) {
            $StreamURL = $this->GetStreamUri();
            if ($StreamURL) {
                $this->SetMedia($StreamURL);
                $this->SetStatus(IS_ACTIVE);
            } else {
                $this->SetMedia('');
                $this->SetStatus(IS_EBASE + 1);
            }
        }
    }

    protected function RefreshRecordingTokenForm(string $NewVideoSource): void
    {
        $Recordings = $this->Recordings;
        $RecordingOptions = [];
        $RecordingOptions[] = [
            'caption' => 'none',
            'value'   => ''
        ];
        foreach ($Recordings as $VideoSource => $SourceRecordings) {
            if ($NewVideoSource == $VideoSource) {
                foreach ($SourceRecordings as $RecordingToken) {
                    $ProfileOptions[] = [
                        'caption' => $RecordingToken,
                        'value'   => $RecordingToken
                    ];
                }
            }
        }
        $this->UpdateFormField('RecordingToken', 'options', json_encode($ProfileOptions));
    }

    protected function GetRecordingSummary(): void
    {
        $Capabilities = $this->Capabilities;
        $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::SearchRecording], 'GetRecordingSummary', true, [], \ONVIF\WSDL::SearchRecording);
        if ($Result == false) {
            return false;
        }
    }
    /*
    protected function GetRecordingInformation()
    {
        $Capabilities = $this->Capabilities;
        $Params = [
            'RecordingToken' => $this->ReadPropertyString(\ONVIF\Replay\Property::RecordingToken)
        ];
        $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::SearchRecording], 'GetRecordingInformation', true, $Params,\ONVIF\WSDL::SearchRecording);
        if ($Result == false) {
            return false;
        }
    }
     */
    /*
    protected function GetReplayConfiguration(){
        $Capabilities = $this->Capabilities;
        $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Replay], 'GetReplayConfiguration', true);
        if ($Result == false) {
            return false;
        }
    }
     */

    protected function GetRecordingJobs(): bool
    {
        $Capabilities = $this->Capabilities;
        $RecordingJobsResult = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'GetRecordingJobs', true, [], \ONVIF\WSDL::Recording);
        if (is_bool($RecordingJobsResult)) {
            return $RecordingJobsResult;
        }
        if (!is_array($RecordingJobsResult->JobItem)) {
            $RecordingJobs = [];
            $RecordingJobs[] = json_decode(json_encode($RecordingJobsResult->JobItem), true);
        } else {
            $RecordingJobs = json_decode(json_encode($RecordingJobsResult->JobItem), true);
        }

        foreach ($RecordingJobs as $RecordingJob) {
            /*$Ident = 'JobConfig'.$RecordingJob['JobConfiguration']['RecordingToken'];
            $Name = 'JobConfiguration:'.$RecordingJob['JobConfiguration']['RecordingToken'];
            $Value = $RecordingJob['JobConfiguration']['Mode'];
            $this->RegisterVariableBoolean($Ident, $Name, '~Switch', 0);
            $this->EnableAction($Ident);
            $this->SetValueBoolean($Ident, (strtolower($Value) == 'active'));*/
            $this->GetRecordingJobState($RecordingJob['JobConfiguration']['RecordingToken']);
        }
        return true;
    }

    protected function GetRecordingJobState(string $JobToken): bool
    {
        $Capabilities = $this->Capabilities;
        $Params = [
            'JobToken' => $JobToken
        ];
        $GetRecordingJobStateResult = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'GetRecordingJobState', true, $Params, \ONVIF\WSDL::Recording);
        if ($GetRecordingJobStateResult == false) {
            return false;
        }
        $Value = $GetRecordingJobStateResult->State->State;
        $Ident = 'JobState' . $JobToken;
        $Name = 'JobState:' . $JobToken;
        $this->RegisterVariableBoolean($Ident, $Name, '~Switch', 0);
        $this->EnableAction($Ident);
        $this->SetValueBoolean($Ident, (strtolower($Value) == 'active'));
        return true;
    }

    protected function SetRecordingJobMode(string $JobToken, bool $State): bool
    {
        $Capabilities = $this->Capabilities;
        $Params = [
            'JobToken' => $JobToken,
            'Mode'     => $State ? 'Active' : 'Idle'
        ];
        $SetRecordingJobModeResult = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'SetRecordingJobMode', true, $Params, \ONVIF\WSDL::Recording);
        if ($SetRecordingJobModeResult) {
            $Ident = 'JobState' . $JobToken;
            $Name = 'JobState:' . $JobToken;
            $this->RegisterVariableBoolean($Ident, $Name, '~Switch', 0);
            $this->EnableAction($Ident);
            $this->SetValueBoolean($Ident, $State);
            return true;
        }
        return false;
    }
    /*
    protected function GetRecordingConfiguration(){
        $Capabilities = $this->Capabilities;
        $Params = [
            'RecordingToken' => $this->ReadPropertyString(\ONVIF\Replay\Property::RecordingToken)
        ];
        $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'GetRecordingConfiguration', true, $Params,\ONVIF\WSDL::Recording);
        if ($Result == false) {
            return false;
        }
    }*/

    /*
    protected function GetRecordingOptions(){
        $Capabilities = $this->Capabilities;
        $Params = [
            'RecordingToken' => $this->ReadPropertyString(\ONVIF\Replay\Property::RecordingToken)
        ];
        $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'GetRecordingOptions', true, $Params,\ONVIF\WSDL::Recording);
        if ($Result == false) {
            return false;
        }
    }
     */

    protected function GetRecordings(): false|array
    {
        $Recordings = [];
        $this->Recordings = $Recordings;
        $Capabilities = $this->Capabilities;
        $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'GetRecordings', true, [], \ONVIF\WSDL::Recording);
        if ($Result == false) {
            return false;
        }
        $RecordingsResult = json_decode(json_encode($Result), true);
        if (!isset($RecordingsResult['RecordingItem'])) {
            return false;
        }
        foreach ($RecordingsResult['RecordingItem'] as $RecordingItem) {
            if ($RecordingItem['Configuration']['MaximumRetentionTime'] == 'PT0S') {
                continue;
            }
            $Recordings[$RecordingItem['Configuration']['Source']['Name']][] = $RecordingItem['RecordingToken'];
        }
        $this->Recordings = $Recordings;
        $this->SendDebug('Recordings', $Recordings, 0);
        return $Recordings;
    }

    /**
     * Todo
     */
    protected function GetStreamUri(): false|string
    {
        $Capabilities = $this->Capabilities;
        $Params = [
            'StreamSetup'  => [
                'Stream'    => 'RTP-Unicast',
                'Transport' => [
                    'Protocol' => 'RTSP',
                ],
            ],
            'RecordingToken' => $this->ReadPropertyString(\ONVIF\Replay\Property::RecordingToken)
        ];
        $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Replay], 'GetReplayUri', true, $Params);
        if ($Result == false) {
            return false;
        }
        $GetReplayUriResult = json_decode(json_encode($Result), true);
        if (!isset($GetReplayUriResult['Uri'])) {
            return false;
        }
        $Uri = parse_url($GetReplayUriResult['Uri']);

        $Credentials = $this->GetCredentials();
        $Uri['host'] = parse_url($this->GetUrl(), PHP_URL_HOST);
        if (($Credentials['Username'] != '') || ($Credentials['Password'] != '')) {
            $Uri['user'] = $Credentials['Username'];
            $Uri['pass'] = $Credentials['Password'];
        }
        $MediaURL = self::unparse_url($Uri);
        $this->SendDebug('MediaURL', $MediaURL, 0);
        return $MediaURL;
    }

    protected function SetMedia($StreamURL): void
    {
        IPS_SetMediaFile($this->GetMediaId(), $StreamURL, false);
    }

    protected function GetMediaId(): int
    {
        $MediaId = @$this->GetIDForIdent('STREAM');
        if ($MediaId == false) {
            $MediaId = IPS_CreateMedia(MEDIATYPE_STREAM);
            IPS_SetParent($MediaId, $this->InstanceID);
            IPS_SetName($MediaId, $this->Translate('Stream'));
            IPS_SetIdent($MediaId, 'STREAM');
        }
        return $MediaId;
    }
}
