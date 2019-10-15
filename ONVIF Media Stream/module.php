<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';

class ONVIFMediaStream extends ONVIFModuleBase
{
    const wsdl = 'media-mod.wsdl';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('VideoSource', '');
        $this->RegisterPropertyString('Profile', '');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        if ($this->ReadPropertyString('VideoSource') == '') {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if ($this->ReadPropertyString('Profile') == '') {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $StreamURL = $this->GetStreamUri();
        if ($StreamURL) {
            $this->SetMedia($StreamURL);
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_EBASE + 1);
        }
    }

    public function GetConfigurationForm()
    {
        $Capas = @$this->GetCapabilities();
        $Profiles = @$this->GetProfiles();
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (($Capas == false) or ( $Profiles == false)) {

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
        $VideoSourcesOptions = [];
        foreach ($Capas['VideoSources'] as $VideoSource) {
            $VideoSourcesOptions[] = [
                'caption' => $VideoSource,
                'value'   => $VideoSource
            ];
        }
        $Form['elements'][0]['options'] = $VideoSourcesOptions;

        $ProfileOptions = [];
        $ActualProfile = null;
        foreach ($Profiles as $Profile) {
            $ProfileOptions[] = [
                'caption' => $Profile['Name'],
                'value'   => $Profile['token']
            ];
            if ($this->ReadPropertyString('Profile') == $Profile['token']) {
                $ActualProfile = $Profile;
            }
        }
        $Form['elements'][1]['options'] = $ProfileOptions;
        $Actions = [];
        if ($ActualProfile != null) {
            if (isset($ActualProfile['VideoSourceConfiguration']['Name'])) {
                $Actions[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'VideoSourceName:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['VideoSourceConfiguration']['Name']
                        ]
                    ]
                ];
            }
            if (isset($ActualProfile['VideoEncoderConfiguration']['Name'])) {
                $Actions[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'VideoEncoderProfileName:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['VideoEncoderConfiguration']['Name']
                        ]
                    ]
                ];
            }
            if (isset($ActualProfile['VideoEncoderConfiguration']['Encoding'])) {
                $Actions[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Encoding:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['VideoEncoderConfiguration']['Encoding']
                        ]
                    ]
                ];
            }
            if (isset($ActualProfile['VideoEncoderConfiguration']['Resolution'])) {
                $Actions[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Resolution:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['VideoEncoderConfiguration']['Resolution']['Width'] . ' x ' . $ActualProfile['VideoEncoderConfiguration']['Resolution']['Height']
                        ]
                    ]
                ];
            }
            if (isset($ActualProfile['VideoEncoderConfiguration']['RateControl']['FrameRateLimit'])) {
                $Actions[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Framerate:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['VideoEncoderConfiguration']['RateControl']['FrameRateLimit']
                        ]
                    ]
                ];
            }
            if (isset($ActualProfile['VideoEncoderConfiguration']['RateControl']['EncodingInterval'])) {
                $Actions[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'Encoding-Interval:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['VideoEncoderConfiguration']['RateControl']['EncodingInterval']
                        ]
                    ]
                ];
            }
            if (isset($ActualProfile['VideoEncoderConfiguration']['RateControl']['BitrateLimit'])) {
                $Actions[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => 'BitrateLimit:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['VideoEncoderConfiguration']['RateControl']['BitrateLimit']
                        ]
                    ]
                ];
            }
        }
        $Form['actions'] = $Actions;

        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }

    protected function FilterVideoSource($Value)
    {
        return $Value["VideoSourceConfiguration"]["SourceToken"] == $this->ReadPropertyString('VideoSource');
    }

    protected function GetProfiles()
    {
        $ret = $this->SendData('', 'GetProfiles', true);
        if ($ret == false) {
            return false;
        }
        $res = json_decode(json_encode($ret), true);
        $Result = array_filter($res['Profiles'], [$this, 'FilterVideoSource']);
        $this->SendDebug('GetProfiles', $Result, 0);
        $this->SetSummary('');
        foreach ($Result as $Profile) {
            if ($this->ReadPropertyString('Profile') == $Profile['token']) {
                $this->SetSummary($Profile['VideoSourceConfiguration']['Name']);
            }
        }
        return $Result;
    }

    protected function GetStreamUri()
    {
        $Params = [
            'StreamSetup'  => [
                'Stream'    => 'RTP-Unicast',
                'Transport' => [
                    'Protocol' => 'RTSP',
                ],
            ],
            'ProfileToken' => $this->ReadPropertyString('Profile')
        ];
        $ret = $this->SendData('', 'GetStreamUri', true, $Params);
        if ($ret == false) {
            return false;
        }
        $res = json_decode(json_encode($ret), true);
        if (!isset($res['MediaUri']['Uri'])) {
            return false;
        }
        $Uri = parse_url($res['MediaUri']['Uri']);
        $Credentials = $this->GetCredentials();
        if (($Credentials['Username'] != '') or ( $Credentials['Password'] != '')) {
            $Uri['user'] = $Credentials['Username'];
            $Uri['pass'] = $Credentials['Password'];
        }
        $MediaURL = self::unparse_url($Uri);
        $this->SendDebug('MediaURL', $MediaURL, 0);
        return $MediaURL;
    }

    protected function SetMedia($StreamURL)
    {
        $mId = @$this->GetIDForIdent('STREAM');
        if ($mId == false) {
            $mId = IPS_CreateMedia(MEDIATYPE_STREAM);
            IPS_SetParent($mId, $this->InstanceID);
            IPS_SetName($mId, 'STREAM');
            IPS_SetIdent($mId, 'STREAM');
        }
        IPS_SetMediaFile($mId, $StreamURL, false);
    }

}
