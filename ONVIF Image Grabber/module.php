<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';
/**
 * @property string $ImageURL
 */
class ONVIFImageGrabber extends ONVIFModuleBase
{
    const wsdl = 'media-mod.wsdl';
    const TopicFilter = 'videosource';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('VideoSource', '');
        $this->RegisterPropertyString('Profile', '');
        $this->RegisterPropertyInteger('Interval', 0);
        $this->RegisterPropertyBoolean('UseCaching', true);
        $this->RegisterTimer('UpdateImage', 0, 'ONVIF_UpdateImage(' . $this->InstanceID . ');');
        $this->ImageURL = false;
    }
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $MediaId = $this->GetMediaId();
        IPS_SetMediaCached($MediaId, $this->ReadPropertyBoolean('UseCaching'));

        if ($this->ReadPropertyString('VideoSource') == '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimerInterval('UpdateImage', 0);
            return;
        }
        if ($this->ReadPropertyString('Profile') == '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimerInterval('UpdateImage', 0);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $this->ImageURL = $SnapshotURL = $this->GetSnapshotUri();
        if ($SnapshotURL) {
            $this->SetStatus(IS_ACTIVE);
            $this->UpdateImage();
            $this->SetTimerInterval('UpdateImage', $this->ReadPropertyInteger('Interval') * 1000);
        } else {
            $this->SetTimerInterval('UpdateImage', 0);
            $this->SetStatus(IS_EBASE + 1);
        }
    }
    public function UpdateImage()
    {
        $URL = $this->ImageURL;
        if ($URL == false) {
            return false;
        }
        if ($this->ParentID == 0) {
            return false;
        }
        if (!$this->HasActiveParent()) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC | CURLAUTH_DIGEST);
        $this->SendDebug('Request Image', $URL, 0);
        $Result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $Error = curl_error($ch);
        curl_close($ch);
        if (($Result === false) || ($http_code >= 400)) {
            $this->SendDebug('Request Image ' . $http_code, $Error, 0);
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($Error, E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $MediaId = $this->GetMediaId();
        IPS_SetMediaContent($MediaId, base64_encode($Result));
        return true;
    }
    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        $this->SendDebug('ReceiveEvent', $Data, 0);
        $EventProperties = $this->ReadAttributeArray('EventProperties');
        if (!array_key_exists($Data['Topic'], $EventProperties)) {
            return false;
        }
        if ($Data['SourceName'] != '') {
            if ($Data['SourceValue'] != $this->ReadPropertyString('VideoSource')) {
                return false;
            }
            $Data['SourceName'] = '';
        }
        $PreName = str_replace($this->ReadPropertyString('EventTopic'), '', $Data['Topic']);
        return $this->SetEventStatusVariable($PreName, $EventProperties[$Data['Topic']], $Data);
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
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
                        'caption' => 'Error on read capabilities.'
                    ]]
                ]
            ];
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $VideoSourcesOptions = [];
        $ProfileOptions = [];
        $ProfileOptions[] = [
            'caption' => 'none',
            'value'   => ''
        ];
        $ActualSources = null;
        $ActualProfile = null;
        foreach ($Capabilities['VideoSourcesJPEG'] as $VideoSource) {
            $VideoSourcesOptions[] = [
                'caption' => $VideoSource['VideoSourceName'],
                'value'   => $VideoSource['VideoSourceToken']
            ];
            if ($this->ReadPropertyString('VideoSource') == $VideoSource['VideoSourceToken']) {
                $ActualSources = $VideoSource;
                foreach ($VideoSource['Profile'] as $Profile) {
                    $ProfileOptions[] = [
                        'caption' => $Profile['Name'],
                        'value'   => $Profile['token']
                    ];
                    if ($this->ReadPropertyString('Profile') == $Profile['token']) {
                        $ActualProfile = $Profile;
                    }
                }
            }
        }
        $Form['elements'][0]['options'] = $VideoSourcesOptions;
        $Form['elements'][1]['options'] = $ProfileOptions;
        $Form['elements'][5] = $this->GetConfigurationFormEventTopic($Form['elements'][5], true);
        $this->SendDebug('ActualProfile', $ActualProfile, 0);
        if ($ActualProfile != null) {
            $ExpansionPanelVideoItems = [];
            if ($ActualSources != null) {
                $ExpansionPanelVideoItems[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Videosource-Name:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualSources['VideoSourceName']
                        ]
                    ]
                ];
            }
            if ($ActualProfile != null) {
                $ExpansionPanelVideoItems[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Videoencoder-Profilename:'
                        ],
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $ActualProfile['Name']
                        ],
                        [
                            'type'      => 'PopupButton',
                            'width'     => '300px',
                            'caption'   => 'Show Image',
                            'popup'     => [
                                'caption'   => 'Image',
                                'items'     => [[
                                    'type'   => 'Image',
                                    'center' => true,
                                    'mediaID'=> $this->GetMediaId()
                                ]]
                            ]
                        ]
                    ]
                ];

                $ExpansionPanelVideoItems[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Encoding:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['Encoding']
                        ]
                    ]
                ];
                $ExpansionPanelVideoItems[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Resolution:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['Resolution']['Width'] . ' x ' . $ActualProfile['Resolution']['Height']
                        ]
                    ]
                ];
                $ExpansionPanelVideoItems[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Framerate:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['RateControl']['FrameRateLimit']
                        ]
                    ]
                ];
                $ExpansionPanelVideoItems[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Encoding-Interval:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['RateControl']['EncodingInterval']
                        ]
                    ]
                ];
                $ExpansionPanelVideoItems[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Bitratelimit:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['RateControl']['BitrateLimit']
                        ]
                    ]
                ];
            }
            $Form['actions'][] = [
                'type'   => 'ExpansionPanel',
                'caption'=> 'Stream properties',
                'items'  => $ExpansionPanelVideoItems
            ];
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        if ($Ident == 'RefreshProfileForm') {
            $this->RefreshProfileForm($Value);
            return true;
        }
    }
    protected function IOChangeState($State)
    {
        parent::IOChangeState($State);

        if ($State == IS_INACTIVE) {
            $this->SetTimerInterval('UpdateImage', 0);
        }
    }
    protected function RefreshProfileForm($NewVideoSource)
    {
        $Capabilities = @$this->GetCapabilities();
        if ($Capabilities == false) {
            return false;
        }
        $ProfileOptions = [];
        $ProfileOptions[] = [
            'caption' => 'none',
            'value'   => ''
        ];
        foreach ($Capabilities['VideoSourcesJPEG'] as $VideoSource) {
            if ($NewVideoSource == $VideoSource['VideoSourceToken']) {
                foreach ($VideoSource['Profile'] as $Profile) {
                    $ProfileOptions[] = [
                        'caption' => $Profile['Name'],
                        'value'   => $Profile['token']
                    ];
                }
            }
        }
        $this->UpdateFormField('Profile', 'options', json_encode($ProfileOptions));
    }

    protected function GetSnapshotUri()
    {
        $Capabilities = @$this->GetCapabilities();
        if ($Capabilities == false) {
            return false;
        }
        foreach ($Capabilities['VideoSourcesJPEG'] as $VideoSource) {
            if ($this->ReadPropertyString('VideoSource') == $VideoSource['VideoSourceToken']) {
                foreach ($VideoSource['Profile'] as $Profile) {
                    if ($Profile['token'] == $this->ReadPropertyString('Profile')) {
                        break;
                    }
                }
                break;
            }
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile')
        ];
        $Result = $this->SendData($Capabilities['XAddr']['Media'], 'GetSnapshotUri', true, $Params);
        if ($Result == false) {
            return false;
        }
        $SnapshotUriResult = json_decode(json_encode($Result), true);
        if (!isset($SnapshotUriResult['MediaUri']['Uri'])) {
            return false;
        }
        $Uri = parse_url($SnapshotUriResult['MediaUri']['Uri']);
        $Credentials = $this->GetCredentials();
        if (($Credentials['Username'] != '') || ($Credentials['Password'] != '')) {
            $Uri['user'] = $Credentials['Username'];
            $Uri['pass'] = $Credentials['Password'];
        }
        $MediaURL = self::unparse_url($Uri);
        $this->SendDebug('MediaURL', $MediaURL, 0);
        return $MediaURL;
    }
    protected function GetMediaId()
    {
        $MediaId = @$this->GetIDForIdent('IMAGE');
        if ($MediaId == false) {
            $MediaId = IPS_CreateMedia(MEDIATYPE_IMAGE);
            IPS_SetParent($MediaId, $this->InstanceID);
            IPS_SetName($MediaId, $this->Translate('Image'));
            IPS_SetIdent($MediaId, 'IMAGE');
            $filename = 'media' . DIRECTORY_SEPARATOR . 'ONVIF_' . $this->InstanceID . '.jpg';
            IPS_SetMediaFile($MediaId, $filename, false);
        }
        return $MediaId;
    }
}
