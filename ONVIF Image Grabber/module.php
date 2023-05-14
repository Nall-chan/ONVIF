<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';
/**
 * @property string $ImageURL
 */
class ONVIFImageGrabber extends ONVIFModuleBase
{
    public const wsdl = \ONVIF\WSDL::Media; //'media-mod';
    public const TopicFilter = 'videosource';

    public function Create(): void
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
    public function ApplyChanges(): void
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
    public function UpdateImage(): bool
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
    public function ReceiveData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        $this->SendDebug('ReceiveEvent', $Data, 0);
        $EventProperties = $this->ReadAttributeArray('EventProperties');
        if (!array_key_exists($Data['Topic'], $EventProperties)) {
            return '';
        }
        $EventProperty = $EventProperties[$Data['Topic']];
        $FoundEventIndex = false;
        $SkipEvent = false;
        foreach ($Data['Sources'] as $Source) {
            str_replace(['Source', 'Video', 'Token'], '', $Source['Name'], $Count);
            if (!$Count) {
                continue;
            }
            $SourceIndex = array_search($Source['Name'], array_column($EventProperty['Sources'], 'Name'));
            if ($SourceIndex === false) {
                continue;
            }
            if ($EventProperty['Sources'][$SourceIndex]['Type'] != 'tt:ReferenceToken') {
                continue;
            }
            if ($Source['Value'] != $this->ReadPropertyString('VideoSource')) {
                $SkipEvent = true;
                continue;
            }
            $FoundEventIndex = $SourceIndex;
            break;
        }
        if ($FoundEventIndex !== false) {
            unset($Data['Sources'][$FoundEventIndex]);
        }
        if ($SkipEvent) {
            return '';
        }
        $PreName = str_replace($this->ReadPropertyString('EventTopic'), '', $Data['Topic']);
        $this->SetEventStatusVariable($PreName, $EventProperties[$Data['Topic']], $Data);
        return '';
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
                if (isset($ActualProfile['RateControl']['EncodingInterval'])) {
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
                }
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
            array_splice($Form['actions'], 1, 0, [[
                'type'   => 'ExpansionPanel',
                'caption'=> 'Stream properties',
                'items'  => $ExpansionPanelVideoItems
            ]]);
        }
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
            case 'RefreshProfileForm':
                $this->RefreshProfileForm($Value);
                return;
            case 'UpdateImage':
                $this->UpdateImage();
                if ((bool) $Value) {
                    $this->ReloadForm();
                }
                return;
        }
    }
    protected function IOChangeState(int $State): void
    {
        parent::IOChangeState($State);

        if ($State == IS_INACTIVE) {
            $this->SetTimerInterval('UpdateImage', 0);
        }
    }
    protected function RefreshProfileForm(string $NewVideoSource): void
    {
        $Capabilities = @$this->GetCapabilities();
        if ($Capabilities == false) {
            return;
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

    protected function GetSnapshotUri(): false|string
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

        if (($Capabilities['XAddr'][\ONVIF\NS::Media2]) != '') {
            $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Media2], 'GetSnapshotUri', true, $Params, \ONVIF\WSDL::Media2);
            if ($Result == false) {
                return false;
            }
            $SnapshotUriResult = json_decode(json_encode($Result), true);
            if (!isset($SnapshotUriResult['Uri'])) {
                return false;
            }
            $Uri = parse_url($SnapshotUriResult['Uri']);
        } else {
            $Result = $this->SendData($Capabilities['XAddr'][\ONVIF\NS::Media], 'GetSnapshotUri', true, $Params);
            if ($Result == false) {
                return false;
            }
            $SnapshotUriResult = json_decode(json_encode($Result), true);
            if (!isset($SnapshotUriResult['MediaUri']['Uri'])) {
                return false;
            }
            $Uri = parse_url($SnapshotUriResult['MediaUri']['Uri']);
        }

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
    protected function GetMediaId(): int
    {
        $MediaId = $this->FindIDByIdent('IMAGE');
        if (!$MediaId) {
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
