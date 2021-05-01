<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ONVIFModuleBase.php';
eval('declare(strict_types=1);namespace ONVIFMediaStream {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');

/**
 * @property string $PTZ_token
 * @property string $PTZ_xAddr
 * @property array $PTZ_Presets
 * @property bool $PTZ_HasHome
 * @property int $PTZ_MaxPresets
 * @property array $PresetTokenList
 * @property string $AuthorizationKey
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
        //Stream
        $this->RegisterPropertyString('VideoSource', '');
        $this->RegisterPropertyString('Profile', '');
        // Invert Controls
        $this->RegisterPropertyBoolean('InvertPanControl', false);
        $this->RegisterPropertyBoolean('InvertTiltControl', false);
        $this->RegisterPropertyBoolean('InvertZoomControl', false);
        //IPS Variables
        $this->RegisterPropertyBoolean('EnablePanTiltVariable', false);
        $this->RegisterPropertyBoolean('EnableZoomVariable', false);
        $this->RegisterPropertyBoolean('EnableSpeedVariable', false);
        $this->RegisterPropertyBoolean('EnableTimeVariable', false);
        //HTML-Box
        $this->RegisterPropertyBoolean('EnablePanTiltHTML', false);
        $this->RegisterPropertyBoolean('EnableZoomHTML', false);
        //SVG-Design in HTML-Box
        $this->RegisterPropertyInteger('PanTiltControlWidth', 100);
        $this->RegisterPropertyInteger('PanTiltControlHeight', 100);
        $this->RegisterPropertyInteger('PanTiltControlOpacity', 60);
        $this->RegisterPropertyInteger('ZoomControlWidth', 50);
        $this->RegisterPropertyInteger('ZoomControlHeight', 100);
        $this->RegisterPropertyInteger('ZoomControlOpacity', 60);
        //Default Speed
        $this->RegisterPropertyFloat('PanDefaultSpeed', 1);
        $this->RegisterPropertyFloat('TiltDefaultSpeed', 1);
        $this->RegisterPropertyFloat('ZoomDefaultSpeed', 1);
        // Presets
        $this->RegisterPropertyBoolean('EnablePresetVariable', false);
        $this->RegisterPropertyBoolean('EnablePresetProfile', false);
        $this->RegisterPropertyString('PresetProfile', json_encode([]));
        // Buffer
        $this->PTZ_token = '';
        $this->PTZ_xAddr = '';
        $this->PTZ_Presets = [];
        $this->PTZ_HasHome = false;
        $this->PTZ_MaxPresets = 0;
        $this->PresetTokenList = [];
        $this->AuthorizationKey = '';
        // Profile
        $this->RegisterProfileIntegerEx(
            'ONVIF.PanTilt',
            'Move',
            '',
            '',
            [
                [0, '◄◄', 'HollowLargeArrowLeft', -1],
                [1, '▲▲', 'HollowLargeArrowUp', -1],
                [2, 'Stop', 'Move', -1],
                [3, '▼▼', 'HollowLargeArrowDown', -1],
                [4, '►►', 'HollowLargeArrowRight', -1]
            ]
        );
        $this->RegisterProfileIntegerEx(
            'ONVIF.Zoom',
            'Move',
            '',
            '',
            [
                [0, '↑↑', 'HollowDoubleArrowUp', -1],
                [1, 'Stop', 'Move', -1],
                [2, '↓↓', 'HollowDoubleArrowDown', -1]
            ]
        );
        $this->RegisterProfileFloatEx(
            'ONVIF.Speed',
            'Speedo',
            '',
            '',
            [
                [0, $this->Translate('default'), '', -1],
                [0.1, '%.1f', '', -1]
            ],
            5,
            0.5,
            1
        );
        $this->RegisterProfileFloatEx(
            'ONVIF.Time',
            'Clock',
            '',
            '',
            [
                [0, $this->Translate('default'), '', -1],
                [0.1, '%.1f ' . $this->Translate('sec.'), '', -1]
            ],
            1,
            0.1,
            1
        );
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

        $this->PTZ_token = '';
        $this->PTZ_xAddr = '';
        $this->PTZ_Presets = [];
        $this->PTZ_HasHome = false;
        $this->PTZ_MaxPresets = 0;
        $this->PresetTokenList = [];
        $this->AuthorizationKey = '';

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
        if ($StreamURL) {
            $this->SetMedia($StreamURL);
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetMedia('');
            $this->SetStatus(IS_EBASE + 1);
        }

        if ($this->ReadPropertyBoolean('EnablePanTiltHTML') || $this->ReadPropertyBoolean('EnableZoomHTML')) {
            $this->RegisterHook('/hook/ONVIF/PTZ/' . $this->InstanceID);
            $this->WritePTZInHTMLBox();
        } else {
            $this->UnregisterHook('/hook/ONVIF/PTZ/' . $this->InstanceID);
            $this->UnregisterVariable('PTZControlHtml');
        }
        if ($this->ReadPropertyBoolean('EnablePanTiltVariable')) {
            $this->RegisterVariableInteger('PT', $this->Translate('Move'), 'ONVIF.PanTilt', 3);
            $this->SetValueInteger('PT', 2);
            $this->EnableAction('PT');
        } else {
            $this->UnregisterVariable('PT');
        }
        if ($this->ReadPropertyBoolean('EnableZoomVariable')) {
            $this->RegisterVariableInteger('ZOOM', $this->Translate('Zoom'), 'ONVIF.Zoom', 4);
            $this->SetValueInteger('ZOOM', 1);
            $this->EnableAction('ZOOM');
        } else {
            $this->UnregisterVariable('ZOOM');
        }
        if ($this->ReadPropertyBoolean('EnableSpeedVariable')) {
            $this->RegisterVariableFloat('SPEED', $this->Translate('Speed'), 'ONVIF.Speed', 1);
            $this->SetValueFloat('SPEED', 0);
            $this->EnableAction('SPEED');
        } else {
            $this->UnregisterVariable('SPEED');
        }
        if ($this->ReadPropertyBoolean('EnableTimeVariable')) {
            $this->RegisterVariableFloat('TIME', $this->Translate('Time'), 'ONVIF.Time', 2);
            $this->SetValueFloat('TIME', 0);
            $this->EnableAction('TIME');
        } else {
            $this->UnregisterVariable('TIME');
        }
        $PresetProfileName = 'ONVIF.Preset.' . $this->InstanceID;
        if ($this->ReadPropertyBoolean('EnablePresetVariable')) {
            $UsePresetName = $this->ReadPropertyBoolean('EnablePresetProfile');
            $Presets = json_decode($this->ReadPropertyString('PresetProfile'));
            $PresetTokenList = [];
            $PresetAssociations = [];
            foreach ($Presets as $Preset) {
                $PresetTokenList[$Preset->VariableValue] = $Preset->PresetToken;
                if ($Preset->PresetActive) {
                    $PresetAssociations[] = [
                        $Preset->VariableValue,
                        $UsePresetName ? ($Preset->PresetName == '' ? $Preset->VariableValue : $Preset->PresetName) : $Preset->VariableValue,
                        '',
                        -1
                    ];
                }
            }
            $this->PresetTokenList = $PresetTokenList;
            $this->RegisterProfileIntegerEx($PresetProfileName, 'Move', '', '', $PresetAssociations);
            $this->RegisterVariableInteger('PRESET', $this->Translate('Pre-position'), $PresetProfileName, 5);
            $this->EnableAction('PRESET');
        } else {
            $this->UnregisterVariable('PRESET');
            $this->UnregisterProfile($PresetProfileName);
        }
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
        @$this->GetPTZCapabilities();
        $VideoSourcesOptions = [];
        $ProfileOptions = [];
        $ProfileOptions[] = [
            'caption' => 'none',
            'value'   => ''
        ];
        $ActualSources = null;
        $ActualProfile = null;
        foreach ($Capabilities['VideoSources'] as $VideoSource) {
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
        $Form['elements'][2] = $this->GetConfigurationFormEventTopic($Form['elements'][2], true);
        $PTZValues = [];
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
                            'caption' => $this->Translate('Videosource-Name:')
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualSources['VideoSourceName']
                        ]
                    ]
                ];
            }
            if ($ActualProfile != null) {
                $mId = $this->GetMediaId();
                $ExpansionPanelVideoItems[] = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $this->Translate('Videoencoder-Profilename:')
                        ],
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $ActualProfile['Name']
                        ],
                        [
                            'type'      => 'PopupButton',
                            'width'     => '300px',
                            'caption'   => 'Show Stream',
                            'popup'     => [
                                'caption'   => 'Image',
                                'items'     => [[
                                    'type'   => 'Image',
                                    'center' => true,
                                    'mediaID'=> $mId
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
                            'caption' => $this->Translate('Resolution:')
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
                            'caption' => $this->Translate('Framerate:')
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
                            'caption' => $this->Translate('Encoding-Interval:')
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
                            'caption' => $this->Translate('Bitratelimit:')
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => $ActualProfile['RateControl']['BitrateLimit']
                        ]
                    ]
                ];
            }
            $Actions[] = [
                'type'   => 'ExpansionPanel',
                'caption'=> $this->Translate('Stream properties'),
                'items'  => $ExpansionPanelVideoItems
            ];

            if ($ActualProfile['ptztoken'] != '') {
                $Form['elements'][3]['enabled'] = true;
                $ExpansionPanelPTZItems = [];
                $ExpansionPanelPTZItems[] =
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $this->Translate('Token:')
                        ],
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $ActualProfile['ptztoken']
                        ]
                    ]
                ];
                $Presets = $this->PTZ_Presets;
                $PTZPresetItems = [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $this->Translate('Pre-positions:')
                        ],
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => count($Presets)
                        ]
                    ]
                ];
                if (count($Presets) == 0) {
                    $PTZPresetItems['items'][] =
                    [
                        'type'     => 'PopupButton',
                        'caption'  => $this->Translate('No pre-positions'),
                        'width'    => '300px',
                        'popup'    => [],
                        'enabled'  => false
                    ];
                } else {
                    $Form['elements'][4]['enabled'] = true;
                    foreach ($Presets as $PresetIndex => $Preset) {
                        $PTZValues[] =
                        [
                            'PresetIndex'  => $PresetIndex,
                            'PresetName'   => $Preset['Name'],
                            'PresetToken'  => $Preset['token'],
                            'PresetActive' => true,
                            'VariableValue'=> $PresetIndex,
                            'rowColor'     => '#C0FFC0'
                        ];
                    }
                    $PTZPresetItems['items'][] = [
                        'type'    => 'PopupButton',
                        'caption' => $this->Translate('Show pre-positions'),
                        'width'   => '300px',
                        'popup'   => [
                            'caption'=> $this->Translate('Presets'),
                            'items'  => [
                                [
                                    'type'   => 'List',
                                    'caption'=> '',
                                    'add'    => false,
                                    'delete' => false,
                                    'sort'   => [
                                        'column'   => 'PresetToken',
                                        'direction'=> 'ascending'
                                    ],
                                    'columns'=> [
                                        [
                                            'caption' => 'Pre-position index',
                                            'name'    => 'PresetIndex',
                                            'width'   => '120px'
                                        ], [
                                            'caption'    => 'Pre-position token',
                                            'name'       => 'PresetToken',
                                            'width'      => '120px'
                                        ],
                                        [
                                            'caption'    => 'Pre-position name',
                                            'name'       => 'PresetName',
                                            'width'      => 'auto'
                                        ]

                                    ],
                                    'values'=> $PTZValues
                                ]
                            ]
                        ]
                    ];
                }
                $ExpansionPanelPTZItems[] = $PTZPresetItems;
                $ExpansionPanelPTZItems[] =
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $this->Translate('max. pre-positions:')
                        ],
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $this->PTZ_MaxPresets
                        ]
                    ]
                ];
                $ExpansionPanelPTZItems[] =
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $this->Translate('Has Home Position:')
                        ],
                        [
                            'type'    => 'Label',
                            'width'   => '200px',
                            'caption' => $this->PTZ_HasHome ? $this->Translate('Yes') : $this->Translate('No')
                        ]
                    ]
                ];
                $Actions[] = [
                    'type'   => 'ExpansionPanel',
                    'caption'=> $this->Translate('PTZ properties'),
                    'items'  => $ExpansionPanelPTZItems
                ];
            }
            $Actions[] = [
                'type'   => 'ExpansionPanel',
                'caption'=> $this->Translate('Testcenter'),
                'items'  => [[
                    'type'=> 'TestCenter'
                ]]
            ];
            $Form['actions'] = $Actions;
        }
        $Form['elements'][4]['popup']['items'][1]['visible'] = $this->ReadPropertyBoolean('EnablePresetVariable');
        $PresetProfile = json_decode($this->ReadPropertyString('PresetProfile'), true);
        $ValidTokens = array_column($PTZValues, 'PresetToken');
        foreach ($PresetProfile as &$Profile) {
            $KnownToken = array_search($Profile['PresetToken'], $ValidTokens);
            if ($KnownToken === false) {
                $Profile['rowColor'] = '#FFC0C0'; // rot Token ungültig
            } else {
                $Profile['rowColor'] = '#FFFFFF'; // weiß token gültig
                unset($ValidTokens[$KnownToken]);
            }
        }
        $UsedValues = array_column($PresetProfile, 'VariableValue');
        foreach ($PTZValues as $NewPTZToken) {
            if (in_array($NewPTZToken['PresetToken'], $ValidTokens)) {
                for ($i = $NewPTZToken['VariableValue']; $i < 256; $i++) {
                    if (!in_array($i, $UsedValues)) {
                        $NewPTZToken['VariableValue'] = $i;
                        $UsedValues[] = $i;
                        break;
                    }
                }
                $PresetProfile[] = $NewPTZToken;
            }
        }
        $Form['elements'][4]['popup']['items'][1]['values'] = $PresetProfile;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return json_encode($Form);
    }

    public function MoveLeft()
    {
        return $this->MoveLeftSpeedTime(0, 0);
    }
    public function MoveLeftTime(float $Time)
    {
        return $this->MoveLeftSpeedTime(0, $Time);
    }
    public function MoveLeftSpeed(float $Speed)
    {
        return $this->MoveLeftSpeedTime($Speed, 0);
    }
    public function MoveLeftSpeedTime(float $Speed, float $Time)
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        if ($Speed == 0) {
            $Speed = $this->ReadPropertyFloat('PanDefaultSpeed');
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'PanTilt' => [
                    'x' => ($this->ReadPropertyBoolean('InvertPanControl') ? $Speed : -$Speed),
                    'y' => 0
                ]
            ]
        ];
        if ($Time != 0) {
            $Params['Timeout'] = sprintf('PT%.2FS', $Time);
        }
        return $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }
    public function MoveRight()
    {
        return $this->MoveRightSpeedTime(0, 0);
    }
    public function MoveRightSpeed(float $Speed)
    {
        return $this->MoveRightSpeedTime($Speed, 0);
    }
    public function MoveRightTime(float $Time)
    {
        return $this->MoveRightSpeedTime(0, $Time);
    }
    public function MoveRightSpeedTime(float $Speed, float $Time)
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        if ($Speed == 0) {
            $Speed = $this->ReadPropertyFloat('PanDefaultSpeed');
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'PanTilt' => [
                    'x' => ($this->ReadPropertyBoolean('InvertPanControl') ? -$Speed : $Speed),
                    'y' => 0
                ]
            ]
        ];
        if ($Time != 0) {
            $Params['Timeout'] = sprintf('PT%.2FS', $Time);
        }
        return $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }

    public function MoveUp()
    {
        return $this->MoveUpSpeedTime(0, 0);
    }
    public function MoveUpSpeed(float $Speed)
    {
        return $this->MoveUpSpeedTime($Speed, 0);
    }
    public function MoveUpTime(float $Time)
    {
        return $this->MoveUpSpeedTime(0, $Time);
    }
    public function MoveUpSpeedTime(float $Speed, float $Time)
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        if ($Speed == 0) {
            $Speed = $this->ReadPropertyFloat('TiltDefaultSpeed');
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'PanTilt' => [
                    'x' => 0,
                    'y' => ($this->ReadPropertyBoolean('InvertTiltControl') ? $Speed : -$Speed)
                ]
            ]
        ];
        if ($Time != 0) {
            $Params['Timeout'] = sprintf('PT%.2FS', $Time);
        }
        return $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }

    public function MoveDown()
    {
        return $this->MoveDownSpeedTime(0, 0);
    }
    public function MoveDownSpeed(float $Speed)
    {
        return $this->MoveDownSpeedTime($Speed, 0);
    }
    public function MoveDownTime(float $Time)
    {
        return $this->MoveDownSpeedTime(0, $Time);
    }

    public function MoveDownSpeedTime(float $Speed, float $Time)
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        if ($Speed == 0) {
            $Speed = $this->ReadPropertyFloat('TiltDefaultSpeed');
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'PanTilt' => [
                    'x' => 0,
                    'y' => ($this->ReadPropertyBoolean('InvertTiltControl') ? -$Speed : $Speed)
                ]
            ]
        ];
        if ($Time != 0) {
            $Params['Timeout'] = sprintf('PT%.2FS', $Time);
        }
        return $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }
    public function ZoomNear()
    {
        return $this->ZoomNearSpeedTime(0, 0);
    }
    public function ZoomNearSpeed(float $Speed)
    {
        return $this->ZoomNearSpeedTime($Speed, 0);
    }
    public function ZoomNearTime(float $Time)
    {
        return $this->ZoomNearSpeedTime(0, $Time);
    }
    public function ZoomNearSpeedTime(float $Speed, float $Time)
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        if ($Speed == 0) {
            $Speed = $this->ReadPropertyFloat('ZoomDefaultSpeed');
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'Zoom' => [
                    'x' => ($this->ReadPropertyBoolean('InvertZoomControl') ? $Speed : -$Speed)
                ]
            ]
        ];
        if ($Time != 0) {
            $Params['Timeout'] = sprintf('PT%.2FS', $Time);
        }
        return $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }
    public function ZoomFar()
    {
        return $this->ZoomFarSpeedTime(0, 0);
    }
    public function ZoomFarSpeed(float $Speed)
    {
        return $this->ZoomFarSpeedTime($Speed, 0);
    }
    public function ZoomFarTime(float $Time)
    {
        return $this->ZoomFarSpeedTime(0, $Time);
    }
    public function ZoomFarSpeedTime(float $Speed, float $Time)
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        if ($Speed == 0) {
            $Speed = $this->ReadPropertyFloat('ZoomDefaultSpeed');
        }
        $Params = [
            'ProfileToken' => $this->ReadPropertyString('Profile'),
            'Velocity'     => [
                'Zoom' => [
                    'x' => ($this->ReadPropertyBoolean('InvertZoomControl') ? -$Speed : $Speed)
                ]
            ]
        ];
        if ($Time != 0) {
            $Params['Timeout'] = sprintf('PT%.2FS', $Time);
        }
        return $this->SendData($this->PTZ_xAddr, 'ContinuousMove', true, $Params, self::PTZwsdl);
    }
    public function StopPTZ()
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        $Params = ['ProfileToken' => $this->ReadPropertyString('Profile'), 'PanTilt' => true, 'Zoom' => true];
        return $this->SendData($this->PTZ_xAddr, 'Stop', true, $Params, self::PTZwsdl);
    }

    public function MoveStop()
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        $Params = ['ProfileToken' => $this->ReadPropertyString('Profile'), 'PanTilt' => true];
        return $this->SendData($this->PTZ_xAddr, 'Stop', true, $Params, self::PTZwsdl);
    }

    public function ZoomStop()
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        $Params = ['ProfileToken' => $this->ReadPropertyString('Profile'), 'Zoom' => true];
        return $this->SendData($this->PTZ_xAddr, 'Stop', true, $Params, self::PTZwsdl);
    }

    public function GotoPreset(int $Preset)
    {
        $PresetTokenList = $this->PresetTokenList;
        if (!array_key_exists($Preset, $PresetTokenList)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Invalid Preset.'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $Token = $PresetTokenList[$Preset];
        return $this->GotoPresetToken($Token);
    }

    public function GotoPresetToken(string $PresetToken)
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        $Params = ['ProfileToken' => $this->ReadPropertyString('Profile'), 'PresetToken' => $PresetToken];
        return $this->SendData($this->PTZ_xAddr, 'GotoPreset', true, $Params, self::PTZwsdl);
    }

    public function GotoPresetHomePosition()
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        $Params = ['ProfileToken' => $this->ReadPropertyString('Profile')];
        return $this->SendData($this->PTZ_xAddr, 'GotoHomePosition', true, $Params, self::PTZwsdl);
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
        if ($Ident == 'RefreshEnablePresetProfileForm') {
            $this->RefreshPresetProfileForm($Value);
            return true;
        }
        $Speed = 0;
        if (@$this->GetIDForIdent('SPEED')) {
            $Speed = $this->GetValue('SPEED');
        }
        $Time = 0;
        if (@$this->GetIDForIdent('TIME')) {
            $Time = $this->GetValue('TIME');
        }
        switch ($Ident) {
        case 'PRESET':
            if ($this->GotoPreset($Value)) {
                $this->SetValue('PRESET', $Value);
                return true;
            }
            return false;
        case 'TIME':
        case 'SPEED':
            $this->SetValueFloat($Ident, $Value);
        return true;
        case 'PT':
            $Result = false;
            switch ($Value) {
                case 0:
                    $Result = $this->MoveLeftSpeedTime($Speed, $Time);
                break;
                case 1:
                    $Result = $this->MoveUpSpeedTime($Speed, $Time);
                break;
                case 2:
                    $Result = $this->MoveStop();
                break;
                case 3:
                    $Result = $this->MoveDownSpeedTime($Speed, $Time);
                break;
                case 4:
                    $Result = $this->MoveRightSpeedTime($Speed, $Time);
                break;
                default:
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Invalid Value.'), E_USER_NOTICE);
                    restore_error_handler();
                    return false;

            }
            if ($Result) {
                $this->SetValueInteger('PT', $Value);
            }
            return $Result;
        case 'ZOOM':
            $Result = false;
            switch ($Value) {
                case 0:
                    $Result = $this->ZoomFarSpeedTime($Speed, $Time);
                break;
                case 1:
                    $Result = $this->ZoomStop();
                break;
                case 2:
                    $Result = $this->ZoomNearSpeedTime($Speed, $Time);
                break;
                    default:
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate('Invalid Value.'), E_USER_NOTICE);
                    restore_error_handler();
                    return false;

            }
            if ($Result) {
                $this->SetValueInteger('ZOOM', $Value);
            }
            return $Result;
    }
        set_error_handler([$this, 'ModulErrorHandler']);
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        restore_error_handler();
        return false;
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

    protected function IOChangeState($State)
    {
        parent::IOChangeState($State);

        if ($State == IS_INACTIVE) {
            $this->SetMedia('');
            if (@$this->GetIDForIdent('PTZControlHtml')) {
                $this->SetValueString('PTZControlHtml', '');
            }
        }
    }
    protected function RefreshPresetProfileForm($EnablePresetProfileForm)
    {
        $this->UpdateFormField('PresetProfile', 'visible', $EnablePresetProfileForm);
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
        foreach ($Capabilities['VideoSources'] as $VideoSource) {
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
        $this->UpdateFormField('ConfigurePTZ', 'enabled', false);
        $this->UpdateFormField('ConfigurePrePositions', 'enabled', false);
    }

    protected function GetPTZCapabilities()
    {
        if ($this->PTZ_token == '') {
            return false;
        }
        ob_start();
        $PTZConfigurationToken = ['PTZConfigurationToken' => $this->PTZ_token];
        $PTZConfiguration = @$this->SendData($this->PTZ_xAddr, 'GetConfiguration', true, $PTZConfigurationToken, self::PTZwsdl);
        if ($PTZConfiguration == false) {
            return false;
        }
        $NodeToken = ['NodeToken'=>$PTZConfiguration->PTZConfiguration->NodeToken];
        $PTZNode = @$this->SendData($this->PTZ_xAddr, 'GetNode', true, $NodeToken, self::PTZwsdl);
        if ($PTZNode === false) {
            $this->PTZ_HasHome = false;
            $this->PTZ_MaxPresets = 0;
        } else {
            if (property_exists($PTZNode->PTZNode, 'HomeSupported')) {
                $this->PTZ_HasHome = $PTZNode->PTZNode->HomeSupported;
            }
            if (property_exists($PTZNode->PTZNode, 'MaximumNumberOfPresets')) {
                $this->PTZ_MaxPresets = $PTZNode->PTZNode->MaximumNumberOfPresets;
            }
        }
        // Presets
        $ProfileToken = ['ProfileToken' => $this->ReadPropertyString('Profile')];
        $PresetResult = @$this->SendData($this->PTZ_xAddr, 'GetPresets', true, $ProfileToken, self::PTZwsdl);
        ob_clean();
        if (is_bool($PresetResult)) {
            $this->PTZ_Presets = [];
        } else {
            $Presets = [];
            if (is_object($PresetResult->Preset)) {
                $Presets[] = json_decode(json_encode($PresetResult->Preset), true);
            } else {
                $Presets = json_decode(json_encode($PresetResult->Preset), true);
            }
            foreach ($Presets as &$Preset) {
                if ($Preset['Name'] = '') {
                    $Preset['Name'] = $Preset['token'];
                }
            }
            $this->PTZ_Presets = $Presets;
        }
        return true;
    }

    protected function GetStreamUri()
    {
        $Capabilities = @$this->GetCapabilities();
        if ($Capabilities == false) {
            return false;
        }
        $this->PTZ_xAddr = $Capabilities['XAddr']['PTZ'];
        foreach ($Capabilities['VideoSources'] as $VideoSource) {
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
        $Result = $this->SendData($Capabilities['XAddr']['Media'], 'GetStreamUri', true, $Params);
        if ($Result == false) {
            return false;
        }
        $GetStreamUriResult = json_decode(json_encode($Result), true);
        if (!isset($GetStreamUriResult['MediaUri']['Uri'])) {
            return false;
        }
        $Uri = parse_url($GetStreamUriResult['MediaUri']['Uri']);
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
        IPS_SetMediaFile($this->GetMediaId(), $StreamURL, false);
    }
    protected function GetMediaId()
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
    protected function WritePTZInHTMLBox()
    {
        $this->RegisterVariableString('PTZControlHtml', 'PTZ Control for Webfront', '~HTMLBox', 5);
        $mId = @$this->GetIDForIdent('STREAM');
        if ($mId == false) {
            $this->SetValueString('PTZControlHtml', '');
            return;
        }
        $this->AuthorizationKey = $Key = base64_encode('token:' . IPS_CreateTemporaryMediaStreamToken($mId, 900));
        $Key = urlencode($Key);
        $ImgSrc = '<img class="stream" src="proxy/' . $mId . '?authorization=' . $Key . '">';
        $PanTiltSVG = '';
        if ($this->ReadPropertyBoolean('EnablePanTiltHTML')) {
            $PanTiltSVG = str_replace(
                [
                    '%%InstanceId%%',
                    '%%width%%',
                    '%%height',
                    '%%opacity%%'
                ],
                [
                    $this->InstanceID,
                    $this->ReadPropertyInteger('PanTiltControlWidth'),
                    $this->ReadPropertyInteger('PanTiltControlHeight'),
                    sprintf('%F', $this->ReadPropertyInteger('PanTiltControlOpacity') / 100)
                ],
                file_get_contents(__DIR__ . '/../libs/PanTiltControl.svg')
            );
        }
        $ZoomSVG = '';
        if ($this->ReadPropertyBoolean('EnableZoomHTML')) {
            $ZoomSVG = str_replace(
                [
                    '%%InstanceId%%',
                    '%%width%%',
                    '%%height',
                    '%%opacity%%'
                ],
                [
                    $this->InstanceID,
                    $this->ReadPropertyInteger('ZoomControlWidth'),
                    $this->ReadPropertyInteger('ZoomControlHeight'),
                    sprintf('%F', $this->ReadPropertyInteger('ZoomControlOpacity') / 100)
                ],
                file_get_contents(__DIR__ . '/../libs/ZoomControl.svg')
            );
        }
        $JS = str_replace(
            [
                '%%InstanceId%%',
                '%%Authorization%%'
            ],
            [
                $this->InstanceID,
                $Key
            ],
            file_get_contents(__DIR__ . '/../libs/PTZControl.js')
        );

        $JSCode = '<script>' . $JS . /*'initPTZ(' . $this->InstanceID . ');*/'</script>';
        $HTMLData = '<div class="extended"><div class="ipsContainer media">' .
        $ImgSrc .
        '<div style="position:absolute; right:0px; bottom:0px; margin:10px">' .
        $ZoomSVG .
        $PanTiltSVG .
        '</div>' .
        $JSCode .
        '</div></div>';

        $this->SetValueString('PTZControlHtml', $HTMLData);
    }
    protected function ProcessHookData()
    {
        if (!isset($_GET['authorization']) || ($_GET['authorization'] != $this->AuthorizationKey)) {
            http_response_code(401);
            header('HTTP/1.1 403 Forbidden');
            header('Connection: close');
            header('Server: Symcon ' . IPS_GetKernelVersion());
            header('X-Powered-By: ONVIF Module');
            header('Expires: 0');
            header('Cache-Control: no-cache');
            header('Content-Type: text/plain');
            echo $this->Translate('Access denied');
            return;
        }
        http_response_code(200);
        header('HTTP/1.1 200 OK');
        header('Connection: close');
        header('Server: Symcon ' . IPS_GetKernelVersion());
        header('X-Powered-By: ONVIF Module');
        header('Expires: 0');
        header('Cache-Control: no-cache');
        header('Content-Type: text/plain');
        if ((!isset($_GET['action'])) || (!isset($_GET['value']))) {
            echo $this->Translate('Invalid parameters.');
            return;
        }

        if ($this->GetStatus() != IS_ACTIVE) {
            echo 'Instance is inactive.';
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
                    if ($this->MoveLeft()) {
                        echo 'OK';
                    }
                    return;
                case 'right':
                    if ($this->MoveRight()) {
                        echo 'OK';
                    }
                    return;
                case 'up':
                    if ($this->MoveUp()) {
                        echo 'OK';
                    }
                    return;
                case 'down':
                    if ($this->MoveDown()) {
                        echo 'OK';
                    }
                    return;
                case 'near':
                    if ($this->ZoomNear()) {
                        echo 'OK';
                    }
                    return;
                case 'far':
                    if ($this->ZoomFar()) {
                        echo 'OK';
                    }
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
