<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';
/**
 * Aufzeichnungen (Recording-Jobs) eines ONVIF-Geräts.
 *
 * @project       ONVIFRecording
 * @file          module.php
 * @package       ONVIF
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       2.61
 *
 * @property array $Capabilities
 */
class ONVIFRecording extends ONVIFModuleBase
{
    public const wsdl = \ONVIF\WSDL::Recording;
    public const TopicFilter = 'RecordingConfig/JobState';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyBoolean(\ONVIF\Recording\Property::EmulateStatus, false);
        $this->Capabilities = [];
    }

    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Form['elements'][0] = $this->GetConfigurationFormEventTopic($Form['elements'][0]);
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
        if (stripos($Ident, 'JobState') === 0) {
            $this->SetRecordingJobMode($Ident, (bool) $Value);
        }
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
        $Ident = $this->GetJobIdent((string) $Data['Sources'][$EventSourceIndex]['Value']);
        $Name = 'JobState:' . $Data['Sources'][$EventSourceIndex]['Value'];
        $DataName = $EventProperty['Data'][$SourceIndex]['Name'];
        $EventDataIndex = array_search($DataName, array_column($Data['DataValues'], 'Name'));
        if ($EventDataIndex === false) {
            return '';
        }
        $Value = $Data['DataValues'][$EventDataIndex]['Value'];
        $this->RegisterVariableBoolean(
            $Ident,
            $Name,
            [
                'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
            ],
            0
        );
        $this->EnableAction($Ident);
        $this->SetValueBoolean($Ident, (strtolower($Value) == 'active'));
        return '';
    }

    public function SetRecordingJobMode(string $Ident, bool $State): bool
    {
        if (stripos($Ident, 'JobState') !== 0) {
            return false;
        }
        $JobToken = $this->GetJobToken($Ident);
        if ($JobToken === null) {
            trigger_error($this->Translate('Recording job not found.'), E_USER_NOTICE);
            return false;
        }
        $Capabilities = $this->Capabilities;
        $Params = [
            'JobToken' => $JobToken,
            'Mode'     => $State ? 'Active' : 'Idle'
        ];
        $SetRecordingJobModeResult = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'SetRecordingJobMode', true, $Params);
        if ($SetRecordingJobModeResult) {
            if (($this->ReadPropertyBoolean(\ONVIF\Output\Property::EmulateStatus))) {
                $Ident = $this->GetJobIdent($JobToken);
                $Name = 'JobState:' . $JobToken;
                $this->RegisterVariableBoolean(
                    $Ident,
                    $Name,
                    [
                        'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
                    ],
                    0
                );
                $this->EnableAction($Ident);
                $this->SetValueBoolean($Ident, $State);
            }
            return true;
        }
        return false;
    }

    protected function InitFilterAndEvents(): void
    {
        parent::InitFilterAndEvents();
        if ($this->ReadPropertyString(\ONVIF\Device\Property::EventTopic) == '') {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->HasActiveParent()) {
            $this->Capabilities = @$this->GetCapabilities();
            if (!$this->Capabilities) {
                $this->SetStatus(IS_EBASE + 1);
                return;
            }
            $Events = $this->ReadAttributeArray(\ONVIF\Device\Attribute::EventProperties);
            if (!count($Events)) {
                $this->SetStatus(IS_EBASE + 1);
            } else {
                $this->SetStatus(IS_ACTIVE);
                $this->GetRecordingJobs();
            }
            return;
        }
        $this->SetStatus(IS_ACTIVE);
    }

    protected function GetRecordingJobs(): bool
    {
        $Capabilities = $this->Capabilities;
        $RecordingJobsResult = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'GetRecordingJobs', true);
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
            $this->GetRecordingJobState($RecordingJob['JobToken']);
        }
        return true;
    }

    protected function GetRecordingJobState(string $JobToken): bool
    {
        $Capabilities = $this->Capabilities;
        $Params = [
            'JobToken' => $JobToken
        ];
        $GetRecordingJobStateResult = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'GetRecordingJobState', true, $Params);
        if ($GetRecordingJobStateResult == false) {
            return false;
        }
        $Value = $GetRecordingJobStateResult->State->State;
        $Ident = $this->GetJobIdent($JobToken);
        $Name = 'JobState:' . $JobToken;
        $this->RegisterVariableBoolean(
            $Ident,
            $Name,
            [
                'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
            ],
            0
        );
        $this->EnableAction($Ident);
        $this->SetValueBoolean($Ident, (strtolower($Value) == 'active'));
        return true;
    }

    /**
     * Erzeugt den Ident der Statusvariable eines Recording-Jobs.
     *
     * Idents dürfen nur Buchstaben, Zahlen und '_' enthalten, Tokens der Geräte aber z.B. auch '-'.
     * Bestehende Idents (Token nur aus Buchstaben, Zahlen und '_') bleiben unverändert.
     * Bei anderen Zeichen werden diese entfernt und ein Hash des Tokens angehängt, damit der Ident eindeutig bleibt.
     *
     * @param string $JobToken Token des Recording-Jobs.
     *
     * @return string Gültiger Ident.
     */
    protected function GetJobIdent(string $JobToken): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $JobToken)) {
            return 'JobState' . $JobToken;
        }
        return 'JobState' . preg_replace('/[^A-Za-z0-9_]/', '', $JobToken) . substr(md5($JobToken), 0, 8);
    }

    /**
     * Ermittelt das Token eines Recording-Jobs aus dem Ident der Statusvariable.
     *
     * @param string $Ident Ident der Statusvariable.
     *
     * @return ?string Token oder null, wenn das Gerät keinen passenden Job meldet.
     */
    protected function GetJobToken(string $Ident): ?string
    {
        $Capabilities = $this->Capabilities;
        $RecordingJobsResult = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Recording], 'GetRecordingJobs', true);
        if (is_bool($RecordingJobsResult)) {
            return null;
        }
        $RecordingJobs = json_decode(json_encode($RecordingJobsResult->JobItem), true);
        if (isset($RecordingJobs['JobToken'])) {
            $RecordingJobs = [$RecordingJobs];
        }
        foreach ((array) $RecordingJobs as $RecordingJob) {
            if ($this->GetJobIdent((string) $RecordingJob['JobToken']) === $Ident) {
                return (string) $RecordingJob['JobToken'];
            }
        }
        return null;
    }

}
