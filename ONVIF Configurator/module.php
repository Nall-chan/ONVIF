<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ONVIFModuleBase.php';

class ONVIFConfigurator extends ONVIFModuleBase
{
    const wsdl = \ONVIF\WSDL::Management;
    const GUID_ONVIF_DIGITAL_INPUT = '{73097230-1ECC-FEEB-5969-C85148DFA76E}';
    const GUID_ONVIF_DIGITAL_OUTPUT = '{A44B3114-1F72-1FD1-96FB-D7E970BD8614}';
    const GUID_ONVIF_MEDIA_STREAM = '{FA889450-38B6-7E20-D4DC-F2C6D0B074FB}';
    const GUID_ONVIF_IMAGE_GRABBER = '{18EA97C1-3CEC-80B7-4CAA-D91F8A2A0599}';
    const GUID_ONVIF_EVENT = '{62584C2E-4542-4EBF-1E92-299F4CF364E4}';

    public function GetConfigurationForm()
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
        $Capabilities = @$this->GetCapabilities();
        if ($Capabilities == false) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Error on read of Capabilities.'
                    ]]
                ]
            ];
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $this->SendDebug('VideoSources', $Capabilities['VideoSources'], 0);
        $this->SendDebug('HasSnapshotUri', $Capabilities['HasSnapshotUri'], 0);
        $this->SendDebug('VideoSourcesJPEG', $Capabilities['VideoSourcesJPEG'], 0);
        $this->SendDebug('NbrOfInputs', $Capabilities['NbrOfInputs'], 0);
        $this->SendDebug('NbrOfOutputs', $Capabilities['NbrOfOutputs'], 0);
        $this->SendDebug('AnalyticsTokens', $Capabilities['AnalyticsTokens'], 0);

        //Events
        $OtherEvents = array_keys($this->GetEvents('', 0, [':VideoSource', ':PTZ', '/Relay', '/DigitalInput']));
        $LastTopic = '';
        $Events = [];
        foreach ($OtherEvents as $Event) {
            if ($LastTopic == '') {
                $LastTopic = $Event;
                $Events[] = $Event;
            }
            if (strpos($Event, $LastTopic) !== 0) {
                $LastTopic = $Event;
                $Events[] = $Event;
            }
        }
        $EventValues = [];
        $IPSEventInstances = $this->GetInstanceList(self::GUID_ONVIF_EVENT, ['EventTopic']);

        foreach ($Events as $Topic) {
            $Device = [
                'instanceID'  => 0,
                'type'        => 'Events',
                'VideoSource' => '',
                'name'        => 'Event: (' . $Topic . ')',
                'Location'    => ''
            ];
            $InstanceID = array_search($Topic, $IPSEventInstances);
            if ($InstanceID !== false) {
                unset($IPSEventInstances[$InstanceID]);
                $Device['instanceID'] = $InstanceID;
                $Device['name'] = IPS_GetName($InstanceID);
                $Device['Location'] = stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true);
            }

            $Device['create'] = [
                'moduleID'      => self::GUID_ONVIF_EVENT,
                'configuration' => [
                    'EventTopic' => $Topic
                ],
                'location'      => [$this->Translate('ONVIF Devices'), IPS_GetName($this->InstanceID)]
            ];
            $EventValues[] = $Device;
        }
        foreach ($IPSEventInstances as $InstanceID => $Topic) {
            $Device = [
                'instanceID'  => $InstanceID,
                'type'        => 'Events',
                'EventTopic'  => $Topic,
                'name'        => IPS_GetName($InstanceID),
                'Location'    => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
            ];
            $StreamValues[] = $Device;
        }

        // Inputs

        $InputEvents = $this->GetEvents('input', 0);
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
        $InputValues = $this->GetConfigurationArray(self::GUID_ONVIF_DIGITAL_INPUT, $Capabilities['NbrOfInputs'] > 0, $InputTopics);

        // Outputs
        $OutputEvents = $this->GetEvents('relay', 0);
        if (count($OutputEvents) == 0) {
            $OutputEvents = $this->GetEvents('port', 0);
        }
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
        $OutputValues = $this->GetConfigurationArray(self::GUID_ONVIF_DIGITAL_OUTPUT, $Capabilities['NbrOfOutputs'] > 0, $OutputTopics);

        // Stream H264
        $StreamCreateParams = [
            'moduleID'      => self::GUID_ONVIF_MEDIA_STREAM,
            'configuration' => [],
            'location'      => [$this->Translate('ONVIF Devices'), IPS_GetName($this->InstanceID)]
        ];
        $StreamValues = [];
        $IPSStreamInstances = $this->GetInstanceList(self::GUID_ONVIF_MEDIA_STREAM, ['Profile', 'VideoSource']);
        foreach ($Capabilities['VideoSources'] as $VideoSource) {
            foreach ($VideoSource['Profile'] as $ProfileIndex => $Profile) {
                $InstanceID = array_search($Profile['token'] . ':' . $VideoSource['VideoSourceToken'], $IPSStreamInstances);
                if ($InstanceID !== false) {
                    unset($IPSStreamInstances[$InstanceID]);
                    $Device = [
                        'instanceID'  => $InstanceID,
                        'type'        => 'Media Stream',
                        'VideoSource' => $VideoSource['VideoSourceToken'],
                        'name'        => IPS_GetName($InstanceID),
                        'Location'    => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true),
                        'create'      => $StreamCreateParams,
                    ];
                    $Device['create']['configuration'] = [
                        'VideoSource' => $VideoSource['VideoSourceToken'],
                        'Profile'     => $Profile['token']
                    ];
                    unset($VideoSource['Profile'][$ProfileIndex]);
                    $StreamValues[] = $Device;
                }
            }
            if (count($VideoSource['Profile'])) { // weitere Profile vorhanden, dann nächste Instanz anbieten
                $Device = [
                    'instanceID'  => 0,
                    'type'        => 'Media Stream',
                    'VideoSource' => $VideoSource['VideoSourceToken'],
                    'name'        => $VideoSource['VideoSourceName'],
                    'Location'    => ''
                ];
                $Create = [];
                foreach ($VideoSource['Profile'] as $ProfileIndex => $Profile) {
                    $Create[$VideoSource['VideoSourceName'] . ' (' . $Profile['Name'] . ')'] = $StreamCreateParams;
                    $Create[$VideoSource['VideoSourceName'] . ' (' . $Profile['Name'] . ')']['configuration'] = [
                        'VideoSource' => $VideoSource['VideoSourceToken'],
                        'Profile'     => $Profile['token']
                    ];
                }
                if (count($Create) == 1) {
                    $Device['name'] = $VideoSource['VideoSourceName'] . ' (' . $Profile['Name'] . ')';
                    $Create = array_shift($Create);
                }
                $Device['create'] = $Create;
                $StreamValues[] = $Device;
            }
        }
        foreach ($IPSStreamInstances as $InstanceID => $VideoSource) {
            $Device = [
                'instanceID'  => $InstanceID,
                'type'        => 'Media Stream',
                'VideoSource' => explode(':', $VideoSource)[1],
                'name'        => IPS_GetName($InstanceID),
                'Location'    => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
            ];
            $StreamValues[] = $Device;
        }
        // Stream JPEG
        $StreamJPEGCreateParams = [
            'moduleID'      => self::GUID_ONVIF_IMAGE_GRABBER,
            'configuration' => [],
            'location'      => [$this->Translate('ONVIF Devices'), IPS_GetName($this->InstanceID)]
        ];
        $StreamJPEGValues = [];
        $IPSStreamJPEGInstances = $this->GetInstanceList(self::GUID_ONVIF_IMAGE_GRABBER, ['Profile', 'VideoSource']);
        if ($Capabilities['HasSnapshotUri']) {
            foreach ($Capabilities['VideoSourcesJPEG'] as $VideoSourceJPEG) {
                foreach ($VideoSourceJPEG['Profile'] as $ProfileIndex =>$Profile) {
                    $InstanceID = array_search($Profile['token'] . ':' . $VideoSourceJPEG['VideoSourceToken'], $IPSStreamJPEGInstances);
                    if ($InstanceID !== false) {
                        unset($IPSStreamJPEGInstances[$InstanceID]);
                        $Device = [
                            'instanceID'  => $InstanceID,
                            'type'        => 'Image Grabber',
                            'VideoSource' => $VideoSourceJPEG['VideoSourceToken'],
                            'name'        => IPS_GetName($InstanceID),
                            'Location'    => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true),
                            'create'      => $StreamJPEGCreateParams
                        ];
                        $Device['create']['configuration'] = [
                            'VideoSource' => $VideoSourceJPEG['VideoSourceToken'],
                            'Profile'     => $Profile['token']
                        ];
                        unset($VideoSourceJPEG['Profile'][$ProfileIndex]);
                        $StreamJPEGValues[] = $Device;
                    }
                }
                if (count($VideoSourceJPEG['Profile'])) { // weitere Profile vorhanden, dann nächste Instanz anbieten
                    $Device = [
                        'instanceID'  => 0,
                        'type'        => 'Image Grabber',
                        'VideoSource' => $VideoSourceJPEG['VideoSourceToken'],
                        'name'        => $VideoSourceJPEG['VideoSourceName'],
                        'Location'    => ''
                    ];
                    $Create = [];
                    foreach ($VideoSourceJPEG['Profile'] as $Profile) {
                        $Create[$VideoSourceJPEG['VideoSourceName'] . ' (' . $Profile['Name'] . ')'] = $StreamJPEGCreateParams;
                        $Create[$VideoSourceJPEG['VideoSourceName'] . ' (' . $Profile['Name'] . ')']['configuration'] = [
                            'VideoSource' => $VideoSourceJPEG['VideoSourceToken'],
                            'Profile'     => $Profile['token']
                        ];
                    }
                    if (count($Create) == 1) {
                        $Device['name'] = $VideoSourceJPEG['VideoSourceName'] . ' (' . $Profile['Name'] . ')';
                        $Create = array_shift($Create);
                    }
                    $Device['create'] = $Create;
                    $StreamJPEGValues[] = $Device;
                }
            }
        }
        foreach ($IPSStreamJPEGInstances as $InstanceID => $VideoSourceJPEG) {
            $Device = [
                'instanceID'  => $InstanceID,
                'type'        => 'Image Grabber',
                'VideoSource' => explode(':', $VideoSourceJPEG)[1],
                'name'        => IPS_GetName($InstanceID),
                'Location'    => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
            ];
            $StreamJPEGValues[] = $Device;
        }

        $Values = array_merge($EventValues, $InputValues, $OutputValues, $StreamValues, $StreamJPEGValues);
        $Form['actions'][0]['values'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }

    protected function GetInstanceList(string $GUID, array $ConfigParam = [])
    {
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID($GUID), [$this, 'FilterInstances']);
        if (count($ConfigParam)) {
            $InstanceIDList = array_flip(array_values($InstanceIDList));
            array_walk($InstanceIDList, [$this, 'GetConfigParam'], $ConfigParam);
        }
        $this->SendDebug('Filter', $InstanceIDList, 0);
        return $InstanceIDList;
    }

    protected function FilterInstances(int $InstanceID)
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }

    protected function GetConfigParam(&$item1, int $InstanceID, array $ConfigParam)
    {
        $item1 = implode(':', array_intersect_key(json_decode(IPS_GetConfiguration($InstanceID), true), array_flip($ConfigParam)));
    }

    protected function GetConfigurationArray(string $GUID, bool $isValid, array $CreateParams = [])
    {
        $IPSInstances = $this->GetInstanceList($GUID);

        $Values = [];
        if (count($IPSInstances) > 0) {
            foreach ($IPSInstances as $IPSInstance) {
                $InstanceValues = [
                    'instanceID'  => $IPSInstance,
                    'type'        => substr(IPS_GetModule($GUID)['ModuleName'], 6),
                    'VideoSource' => '',
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
                    'VideoSource' => '',
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
