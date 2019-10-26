<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';

class ONVIFConfigurator extends ONVIFModuleBase
{
    const wsdl = 'devicemgmt-mod.wsdl';
    const GUID_ONVIF_DIGITAL_INPUT = '{73097230-1ECC-FEEB-5969-C85148DFA76E}';
    const GUID_ONVIF_DIGITAL_OUTPUT = '{A44B3114-1F72-1FD1-96FB-D7E970BD8614}';
    const GUID_ONVIF_MEDIA_STREAM = '{FA889450-38B6-7E20-D4DC-F2C6D0B074FB}';

    public function GetConfigurationForm()
    {
        $Capas = $this->GetCapabilities();
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($Capas == false) {

            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                    'type'    => 'Label',
                    'caption' => 'Error on read capabilities.'
                        ]]
                ]
            ];
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        if (!$this->HasActiveParent()) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                    'type'    => 'Label',
                    'caption' => 'Instance has no active parent.'
                        ]]
                ]
            ];
        }

        $this->SendDebug('VideoSources', $Capas['VideoSources'], 0);
        $this->SendDebug('HasInput', $Capas['HasInput'], 0);
        $this->SendDebug('HasOutput', $Capas['HasOutput'], 0);


        $InputEvents = $this->GetEvents('input', 0);
        $this->SendDebug('GetEvents', $InputEvents, 0);
        $InputTopics = [];
        foreach (array_keys($InputEvents) as $Topic) {
            $InputTopics[$Topic] = [
                'moduleID'      => self::GUID_ONVIF_DIGITAL_INPUT,
                'configuration' => [
                    'EventTopic' => $Topic
                ],
                'location'      => [$this->Translate('ONVIF Devices'), IPS_GetName($this->InstanceID)]
            ];
        }
        if (count($InputTopics) == 1) {
            $InputTopics = array_shift($InputTopics);
        }

        $InputValues = $this->GetConfigurationArray(self::GUID_ONVIF_DIGITAL_INPUT, $Capas['HasInput'], $InputTopics);

        $OutputEvents = [];
        if ($Capas['HasOutput']) {
            $OutputEvents = $this->GetEvents('relay', 0);
            if (count($OutputEvents) == 0) {
                $OutputEvents = $this->GetEvents('port', 0);
            }
        }
        $this->SendDebug('GetEvents', $OutputEvents, 0);
        $OutputTopics = [];
        foreach (array_keys($OutputEvents) as $Topic) {
            $OutputTopics[$Topic] = [
                'moduleID'      => self::GUID_ONVIF_DIGITAL_OUTPUT,
                'configuration' => [
                    'EventTopic' => $Topic
                ],
                'location'      => [$this->Translate('ONVIF Devices'), IPS_GetName($this->InstanceID)]
            ];
        }
        if (count($OutputTopics) == 1) {
            $OutputTopics = array_shift($OutputTopics);
        }

        $OutputValues = $this->GetConfigurationArray(self::GUID_ONVIF_DIGITAL_OUTPUT, $Capas['HasOutput'], $OutputTopics);


        $StreamCreateParams = [
            'moduleID'      => self::GUID_ONVIF_MEDIA_STREAM,
            'configuration' => [],
            'location'      => [$this->Translate('ONVIF Devices'), IPS_GetName($this->InstanceID)]
        ];
        $StreamValues = [];
        $IPSStreamInstances = $this->GetInstanceList(self::GUID_ONVIF_MEDIA_STREAM, 'VideoSource');

        foreach ($Capas['VideoSources'] as $VideoSource) {
            $Device = [
                'instanceID'  => 0,
                'type'        => 'Media Stream',
                'videosource' => $VideoSource['VideoSourceToken'],
                'name'        => $VideoSource['VideoSourceName'],
                'Location'    => ''
            ];
            $InstanceID = array_search($VideoSource['VideoSourceToken'], $IPSStreamInstances);

            if ($InstanceID !== false) {
                unset($IPSStreamInstances[$InstanceID]);
                $Device['instanceID'] = $InstanceID;
                $Device['name'] = IPS_GetName($InstanceID);
                $Device['Location'] = stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true);
            }
            $Create = [];
            foreach ($VideoSource['Profile'] as $Profile) {
                $Create[$VideoSource['VideoSourceName'] . ' (' . $Profile['Name'] . ')'] = $StreamCreateParams;
                $Create[$VideoSource['VideoSourceName'] . ' (' . $Profile['Name'] . ')']['configuration'] = [
                    'VideoSource' => $VideoSource['VideoSourceToken'],
                    'Profile'     => $Profile['token']
                ];
            }
            if (count($Create) == 1) {
                $Create = array_shift($Create);
            }
            $Device['create'] = $Create;
            $StreamValues[] = $Device;
        }
        foreach ($IPSStreamInstances as $InstanceID => $VideoSource) {
            $Device = [
                'instanceID'  => $InstanceID,
                'type'        => 'Media Stream',
                'videosource' => $VideoSource,
                'name'        => IPS_GetName($InstanceID),
                'Location'    => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
            ];
            $StreamValues[] = $Device;
        }
        $Values = array_merge($InputValues, $OutputValues, $StreamValues);
        $Form['actions'][0]['values'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }

    private function GetInstanceList(string $GUID, string $ConfigParam = null)
    {
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID($GUID), [$this, 'FilterInstances']);
        if ($ConfigParam != null) {
            $InstanceIDList = array_flip(array_values($InstanceIDList));
            array_walk($InstanceIDList, [$this, 'GetConfigParam'], $ConfigParam);
        }
        return $InstanceIDList;
    }

    private function FilterInstances(int $InstanceID)
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }

    private function GetConfigParam(&$item1, $InstanceID, $ConfigParam)
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
    }

    private function GetConfigurationArray(string $GUID, bool $isValid, array $CreateParams = [])
    {
        $IPSInstances = $this->GetInstanceList($GUID);

        $Values = [];
        if (count($IPSInstances) > 0) {
            foreach ($IPSInstances as $IPSInstance) {
                $InstanceValues = [
                    'instanceID'  => $IPSInstance,
                    'type'        => substr(IPS_GetModule($GUID)['ModuleName'], 6),
                    'videosource' => '',
                    'name'        => IPS_GetName($IPSInstance),
                    'Location'    => stristr(IPS_GetLocation($IPSInstance), IPS_GetName($IPSInstance), true)
                ];
                if ($isValid) {
                    if (count($CreateParams) > 0) {
                        $InstanceValues['create'] = $CreateParams;
                    }
                }
                $Values[] = $InstanceValues;
            }
        } else {
            if ($isValid) {
                $InstanceValues = [
                    'instanceID'  => 0,
                    'type'        => substr(IPS_GetModule($GUID)['ModuleName'], 6),
                    'videosource' => '',
                    'name'        => substr(IPS_GetModule($GUID)['ModuleName'], 6),
                    'Location'    => ''
                ];
                if (count($CreateParams) > 0) {
                    $InstanceValues['create'] = $CreateParams;
                }
                $Values[] = $InstanceValues;
            }
        }
        return $Values;
    }

}
