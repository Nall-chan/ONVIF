<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';
eval('declare(strict_types=1);namespace ONVIFMediaStream {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');

/**
 * @property string $PTZ_token
 * @property string $PTZ_xAddr
 */
class ONVIFMediaStream extends ONVIFModuleBase
{
    use \ONVIFMediaStream\WebhookHelper;
    const wsdl = 'media-mod.wsdl';
    const PTZwsdl = 'ptz-mod.wsdl';
    const TopicFilter = 'videosource';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('VideoSource', '');
        $this->RegisterPropertyString('Profile', '');
        $this->RegisterPropertyBoolean('EnablePTZ', false);
        $this->PTZ_WSDL = '';
        $this->PTZ_token = '';
        $this->PTZ_xAddr = '';
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/ONVIF/PTZ/' . $this->InstanceID);
        }
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->PTZ_WSDL = '';
        $this->PTZ_token = '';
        $this->PTZ_xAddr = '';
        if ($this->ReadPropertyString('VideoSource') == '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetMedia('');
            return;
        }
        if ($this->ReadPropertyString('Profile') == '') {
            $this->SetStatus(IS_INACTIVE);
            $this->SetMedia('');
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $StreamURL = $this->GetStreamUri();
        $UsePTZ = $this->ReadPropertyBoolean('EnablePTZ');
        if ($StreamURL) {
            $this->SetMedia($StreamURL);
            if ($UsePTZ) {
                $UsePTZ = $this->GetPTZCapas();
            }
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetMedia('');
            $this->SetStatus(IS_EBASE + 1);
        }
        if ($UsePTZ) {
            $this->RegisterHook('/hook/ONVIF/PTZ/' . $this->InstanceID);
            $this->WritePTZinHTMLBox();
        } else {
            $this->UnregisterHook('/hook/ONVIF/PTZ/' . $this->InstanceID);
            $this->UnregisterVariable('PTZControlHtml');
        }
    }

    public function GetConfigurationForm()
    {
        $Capas = @$this->GetCapabilities();
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
        $VideoSourcesOptions = [];
        $ProfileOptions = [];
        $ProfileOptions[] = [
            'caption' => 'none',
            'value'   => ''
        ];
        $ActualProfile = null;
        $this->SendDebug('Capas', $Capas['VideoSources'], 0);
        foreach ($Capas['VideoSources'] as $VideoSource) {
            $VideoSourcesOptions[] = [
                'caption' => $VideoSource['VideoSourceName'],
                'value'   => $VideoSource['VideoSourceToken']
            ];
            if ($this->ReadPropertyString('VideoSource') == $VideoSource['VideoSourceToken']) {
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
        $Form['elements'][2] = $this->GetConfigurationFormEventTopic($Form['elements'][2], true);
        $Actions = [];
        if ($ActualProfile != null) {
            if (isset($ActualProfile['VideoSourceConfiguration']['Name'])) {
                $Actions[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $this->Translate('Videosource-Name:')
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
                            'caption' => $this->Translate('Videoencoder-Profilename:')
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
                            'caption' => $this->Translate('Resolution:')
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
                            'caption' => $this->Translate('Framerate:')
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
                            'caption' => $this->Translate('Encoding-Interval:')
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
                            'caption' => $this->Translate('Bitratelimit:')
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

    public function StartLeft()
    {
        if (!$this->ReadPropertyBoolean('EnablePTZ')) {
            return false;
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'PanTilt' => [
                    'x' => 1
                ]
            ]
        ];
        $ret = $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }

    public function StartRight()
    {
        if (!$this->ReadPropertyBoolean('EnablePTZ')) {
            return false;
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'PanTilt' => [
                    'x' => -1
                ]
            ]
        ];
        $ret = $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }

    public function StartUp()
    {
        if (!$this->ReadPropertyBoolean('EnablePTZ')) {
            return false;
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'PanTilt' => [
                    'y' => 1
                ]
            ]
        ];
        $ret = $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }

    public function StartDown()
    {
        if (!$this->ReadPropertyBoolean('EnablePTZ')) {
            return false;
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'PanTilt' => [
                    'y' => -1
                ]
            ]
        ];
        $ret = $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }

    public function StopPTZ()
    {
        if (!$this->ReadPropertyBoolean('EnablePTZ')) {
            return false;
        }
        $Params = ['ProfileToken' => $this->ReadPropertyString('Profile')];
        $ret = $this->SendData($this->PTZ_xAddr, 'Stop', true, $Params, self::PTZwsdl);
    }

    public function StopPanTilt()
    {
        if (!$this->ReadPropertyBoolean('EnablePTZ')) {
            return false;
        }
        $Params = ['ProfileToken' => $this->ReadPropertyString('Profile'), 'PanTilt' => true];
        $ret = $this->SendData($this->PTZ_xAddr, 'Stop', true, $Params, self::PTZwsdl);
    }

    public function StopZoom()
    {
        if (!$this->ReadPropertyBoolean('EnablePTZ')) {
            return false;
        }
        $Params = ['ProfileToken' => $this->ReadPropertyString('Profile'), 'Zoom' => true];
        $ret = $this->SendData($this->PTZ_xAddr, 'Stop', true, $Params, self::PTZwsdl);
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        if ($Ident == 'RefreshProfileForm') {
            $this->RefreshProfileForm($Value);
        }
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
        }
        $PreName = str_replace($this->ReadPropertyString('EventTopic'), '', $Data['Topic']);
        return $this->SetEventStatusVariable($PreName, $EventProperties[$Data['Topic']], $Data);
    }

    protected function RefreshProfileForm($NewVideoSource)
    {
        $Capas = @$this->GetCapabilities();
        if ($Capas == false) {
            return false;
        }
        $ProfileOptions = [];
        $ProfileOptions[] = [
            'caption' => 'none',
            'value'   => ''
        ];
        foreach ($Capas['VideoSources'] as $VideoSource) {
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

    /**
     * @todo Rückgabewert korrekt auswerten und ggfls. Funktion deaktivieren und Form aktualisieren.
     *
     * @return void
     */
    protected function GetPTZCapas()
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        $Params = ['PTZConfigurationToken' => $this->PTZ_token];
        $ret = $this->SendData($this->PTZ_xAddr, 'GetConfiguration', true, $Params, self::PTZwsdl);
        if ($ret == false) {
            return false;
        }
        return true;
    }

    protected function GetStreamUri()
    {
        $Capas = @$this->GetCapabilities();
        if ($Capas == false) {
            return false;
        }
        $this->PTZ_xAddr = $Capas['XAddr']['PTZ'];
        foreach ($Capas['VideoSources'] as $VideoSource) {
            if ($this->ReadPropertyString('VideoSource') == $VideoSource['VideoSourceToken']) {
                foreach ($VideoSource['Profile'] as $Profile) {
                    if ($Profile['token'] == $this->ReadPropertyString('Profile')) {
                        $this->PTZ_token = $Profile['ptztoken'];
                        break;
                    }
                }
                break;
            }
        }
        $Params = [
            'StreamSetup'  => [
                'Stream'    => 'RTP-Unicast',
                'Transport' => [
                    'Protocol' => 'RTSP',
                ],
            ],
            'ProfileToken' => $this->ReadPropertyString('Profile')
        ];
        $ret = $this->SendData($Capas['XAddr']['Media'], 'GetStreamUri', true, $Params);
        if ($ret == false) {
            return false;
        }
        $res = json_decode(json_encode($ret), true);
        if (!isset($res['MediaUri']['Uri'])) {
            return false;
        }
        $Uri = parse_url($res['MediaUri']['Uri']);
        $Credentials = $this->GetCredentials();
        if (($Credentials['Username'] != '') || ($Credentials['Password'] != '')) {
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
            IPS_SetName($mId, 'Stream');
            IPS_SetIdent($mId, 'STREAM');
        }
        IPS_SetMediaFile($mId, $StreamURL, false);
    }

    protected function WritePTZinHTMLBox()
    {
        $mId = @$this->GetIDForIdent('STREAM');
        if ($mId == false) {
            return;
        }
        $vId = $this->RegisterVariableString('PTZControlHtml', 'PTZ Control for Webfront', '~HTMLBox', 0);
        IPS_SetHidden($vId, true);
        $JS = file_get_contents(__DIR__ . '/../libs/PTZControls.js');
        $this->SetValue('PTZControlHtml', "<script>\r\n" . $JS . 'initPTZ(' . $mId . ',' . $this->InstanceID . ");\r\n</script>\r\n");
    }

    protected function ProcessHookData()
    {
        http_response_code(200);
        header('Connection: close');
        header('Server: Symcon ' . IPS_GetKernelVersion());
        header('X-Powered-By: ONVIF Module');
        header('Expires: 0');
        header('Cache-Control: no-cache');
        header('Content-Type: text/plain');
        if ($this->GetStatus() != IS_ACTIVE) {
            echo 'Instance is inactive.';
            return;
        }
        if ((!isset($_GET['action'])) || (!isset($_GET['value']))) {
            echo $this->Translate('Invalid parameters.');
            return;
        }
        switch ($_GET['action']) {
            case 'StopPTZ':
                $this->StopPTZ();
                echo 'OK';
                return;
            case 'StartPTZ':
                switch ($_GET['value']) {
                    case 'left':
                        $this->StartLeft();
                        echo 'OK';
                        return;
                    case 'right':
                        $this->StartRight();
                        echo 'OK';
                        return;
                    case 'top':
                        $this->StartUp();
                        echo 'OK';
                        return;
                    case 'bottom':
                        $this->StartDown();
                        echo 'OK';
                        return;
                    case 'bottom':
                        $this->StartDown();
                        echo 'OK';
                        return;
                    default:
                        echo $this->Translate('Invalid parameters.');
                        return;
                }
                break;
        }
        echo $this->Translate('Invalid parameters.');
        return;
    }
}
