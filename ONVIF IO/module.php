<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/AttributeArrayHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/WebhookHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/SemaphoreHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/ONVIF.inc.php';

/**
 * @property string $Host
 * @property string $MyIP
 * @property integer $MyPort
 * @property bool $MyHTTPS
 * @property bool $isSubscribed
 * @property bool $WaitForFirstEvent
 * @property \ONVIF\Profile $Profile
 * @property array $Warnings
 */
class ONVIFIO extends IPSModule
{
    use \ONVIFIO\DebugHelper;
    use \ONVIFIO\BufferHelper;
    use \ONVIFIO\AttributeArrayHelper;
    use \ONVIFIO\WebhookHelper;
    use \ONVIFIO\Semaphore;
    protected $lastSOAPError = '';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyBoolean('Open', false);
        $this->RegisterPropertyString('Address', '');
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('WebHookIP', '');
        $this->RegisterPropertyBoolean('WebHookHTTPS', false);
        $this->RegisterPropertyInteger('WebHookPort', 3777);
        $this->RegisterAttributeArray('VideoSources', []);
        $this->RegisterAttributeArray('AudioSources', []);
        $this->RegisterAttributeArray('VideoSourcesJPEG', []);
        $this->RegisterAttributeArray('AnalyticsTokens', []);
        $this->RegisterAttributeArray('RelayOutputs', []);
        $this->RegisterAttributeArray('DigitalInputs', []);
        $this->RegisterAttributeInteger('Timestamp_Offset', 0);
        $this->RegisterAttributeArray('XAddr', []);
        $this->RegisterAttributeArray('EventProperties', []);
        $this->RegisterAttributeInteger('NbrOfInputs', 0);
        $this->RegisterAttributeInteger('NbrOfOutputs', 0);
        $this->RegisterAttributeInteger('NbrOfVideoSources', 0);
        $this->RegisterAttributeInteger('NbrOfAudioSources', 0);
        $this->RegisterAttributeInteger('NbrOfSerialPorts', 0);
        $this->RegisterAttributeBoolean('HasSnapshotUri', false);
        $this->RegisterAttributeBoolean('HasRTSPStreaming', false);
        $this->RegisterAttributeBoolean('RuleSupport', false);
        $this->RegisterAttributeBoolean('AnalyticsModuleSupport', false);
        $this->RegisterAttributeString('ConsumerAddress', '');
        $this->RegisterAttributeString('SubscriptionReference', '');
        $this->RegisterAttributeString('SubscriptionId', '');
        $this->RegisterAttributeInteger('CapabilitiesVersion', 0);
        $this->RegisterTimer('RenewSubscription', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"Renew",true);');
        $this->RegisterTimer('PullMessages', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"PullMessages",true);');
        $this->Host = '';
        $this->MyIP = '';
        $this->MyPort = 3777;
        $this->MyHTTPS = false;
        $this->isSubscribed = false;
        $this->Profile = new \ONVIF\Profile();
        $this->Warnings = [];
        $this->WaitForFirstEvent = false;
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterMessage($this->InstanceID, FM_CHILDREMOVED);
        } else {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/ONVIFEvents/IO/' . $this->InstanceID);
        }
        parent::Destroy();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                    $this->UnregisterMessage(0, IPS_KERNELSTARTED);
                    IPS_RequestAction($this->InstanceID, 'KernelReady', true);
                break;
            case FM_CHILDREMOVED:
                $this->lock('EventProperties');
                $Events = $this->ReadAttributeArray('EventProperties');
                foreach ($Events as &$Event) {
                    $Index = array_search($Data[0], $Event['Receivers']);
                    if ($Index !== false) {
                        unset($Event['Receivers'][$Index]);
                    }
                }
                $this->WriteAttributeArray('EventProperties', $Events);
                $this->unlock('EventProperties');
                $this->ReloadForm();
                break;
        }
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RenewSubscription', 0);
        $this->SetTimerInterval('PullMessages', 0);
        if ($this->GetStatus() == IS_ACTIVE) { // block childs
            $this->SetStatus(IS_INACTIVE);
        }
        if ($this->isSubscribed) {
            $this->Unsubscribe();
        }
        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (!$this->ReadPropertyBoolean('Open')) {
            $this->SetStatus(IS_INACTIVE);
            $this->LogMessage($this->Translate('Interface closed'), KL_MESSAGE);
            return;
        }

        $Url = parse_url($this->ReadPropertyString('Address'));
        $Url['port'] = (isset($Url['port']) ? ':' . $Url['port'] : '');
        if (!isset($Url['scheme']) && !isset($Url['host'])) {
            $this->Host = '';
            $this->SetStatus(IS_EBASE + 1);
            $this->SetSummary('');
            $this->WriteAttributeString('ConsumerAddress', '');
            $this->LogMessage($this->Translate('Address is invalid'), KL_ERROR);
            return;
        }
        $MyIP = $this->ReadPropertyString('WebHookIP');
        $MyPort = $this->ReadPropertyInteger('WebHookPort');
        $MyHTTPS = $this->ReadPropertyBoolean('WebHookHTTPS');
        $Host = $Url['scheme'] . '://' . $Url['host'] . $Url['port'];
        $ReloadCapabilities = ($this->Host != $Host);
        $ReloadCapabilities = $ReloadCapabilities || ($this->MyIP != $MyIP);
        $ReloadCapabilities = $ReloadCapabilities || ($this->MyPort != $MyPort);
        $ReloadCapabilities = $ReloadCapabilities || ($this->MyHTTPS != $MyHTTPS);
        $ReloadCapabilities = $ReloadCapabilities || ($this->GetStatus() == 202);
        $ReloadCapabilities = $ReloadCapabilities || ($this->ReadAttributeString('ConsumerAddress') == '');
        $ReloadCapabilities = $ReloadCapabilities || ($this->ReadAttributeInteger('CapabilitiesVersion') == 0); //Force on initial update, Version 0
        $this->SendDebug('ReloadCapabilities', $ReloadCapabilities, 0);
        $this->SetSummary($Host);
        $this->Host = $Host;
        $this->MyIP = $MyIP;
        $this->MyPort = $MyPort;
        $this->MyHTTPS = $MyHTTPS;
        $this->Warnings = [];
        // 1.ONVIF Request GetSystemDateAndTime
        $Reachable = $this->GetSystemDateAndTime(); // können nicht alle, also nicht weiter beachten. Wird aber für login mit WSSecurityHeader benötigt, damit Zeitdifferenzen bei berücksichtigt werden.
        // 2.ONVIF Request GetScopes
        $Scopes = $this->GetScopes(); // die sagen aus ob G, S oder und T
        if (!$Scopes && !$Reachable) { // not reachable
            $this->UpdateFormField('EventHook', 'caption', $this->ReadAttributeString('ConsumerAddress'));
            $this->WriteAttributeString('SubscriptionReference', '');
            $this->WriteAttributeString('SubscriptionId', '');
            $this->UpdateFormField('SubscriptionReferenceRow', 'visible', false);
            $this->UpdateFormField('EventHookRow', 'visible', false);
            $EventList = @$this->GetEventReceiverFormValues();
            $this->UpdateFormField('Events', 'values', json_encode($EventList));
            $this->UpdateFormField('Events', 'visible', true);
            $this->LogMessage($this->lastSOAPError, KL_ERROR);
            $this->ShowLastError($this->lastSOAPError);
            $this->SetStatus(IS_EBASE + 2);
            return;
        }
        if (strpos($this->lastSOAPError, 'authorization') !== false) {
            $this->ShowLastError($this->lastSOAPError);
            return;
        }
        if (strpos($this->lastSOAPError, 'Unauthorized') !== false) {
            $this->ShowLastError($this->lastSOAPError);
            return;
        }
        if (!$Scopes) {
            $Scopes = [\ONVIF\Scopes::ProfileS];
            $this->Warnings = array_merge($this->Warnings, [$this->Translate('Failed to get scopes, device not ONVIF compliant!')]);
        }
        $Profile = new \ONVIF\Profile($Scopes);
        $this->SendDebug('ProfileBitMask', $Profile->toString(), 0);
        if ($Profile->Profile == 0) {
            $Profile->Profile = \ONVIF\Profile::S;
            $this->SendDebug('Fallback ProfileBitMask', $Profile->toString(), 0);
            $this->Warnings = array_merge($this->Warnings, [$this->Translate('No profile in scopes, device not ONVIF compliant!')]);
        }
        $this->lock('Profile');
        $this->Profile = $Profile;
        $this->unlock('Profile');
        if ($ReloadCapabilities) {
            $this->WriteAttributeInteger('CapabilitiesVersion', 1); // This is Version 1
            // 3.ONVIF Request GetServices
            $XAddr = $this->GetServices(); // besorgt XAddr, Pflicht bei T, selten bei S unterstützt.
            if (!$XAddr) {
                $XAddr = $this->GetCapabilities(); // besorgt XAddr, Pflicht für Profil S.
                if ($this->Profile->HasProfile(\ONVIF\Profile::T)) { //Profile T ist GetServices Pflicht
                    $this->Warnings = array_merge($this->Warnings, [$this->Translate('Failed to get services. Device reported ONVIF T scope, but is not compliant!')]);
                }
            }
            if ($XAddr) {
                // 4. ONVIF Request GetServiceCapabilities an \ONVIF\WSDL::Management
                //$this->GetServiceCapabilities($XAddr[\ONVIF\NS::Management], \ONVIF\WSDL::Management); // noch ohne Funktion..

                // Wenn \ONVIF\WSDL::DeviceIO unterstützt
                // 4b.ONVIF Request GetServiceCapabilities an \ONVIF\WSDL::DeviceIO
                if ($XAddr[\ONVIF\NS::DeviceIO]) {
                    $DeviceCapabilities = $this->GetServiceCapabilities($XAddr[\ONVIF\NS::DeviceIO], \ONVIF\WSDL::DeviceIO);
                    if ($DeviceCapabilities) {
                        $Capabilities = $DeviceCapabilities['Capabilities'];
                        if ($Capabilities['VideoSources'] > 0) {
                            $this->WriteAttributeInteger('NbrOfVideoSources', $Capabilities['VideoSources']);
                        }
                        if ($Capabilities['AudioSources'] > 0) {
                            $this->WriteAttributeInteger('NbrOfAudioSources', $Capabilities['AudioSources']);
                        }
                        if ($Capabilities['RelayOutputs'] > 0) {
                            $this->WriteAttributeInteger('NbrOfOutputs', $Capabilities['RelayOutputs']);
                        }
                        if ($Capabilities['DigitalInputs'] > 0) {
                            $this->WriteAttributeInteger('NbrOfInputs', $Capabilities['DigitalInputs']);
                        }
                        if ($Capabilities['SerialPorts'] > 0) {
                            $this->WriteAttributeInteger('NbrOfSerialPorts', $Capabilities['SerialPorts']);
                        }

                        // Ergebnisse sagen aus ob wir Video, Audio, Relay und Input auslesen
                        //Noch ohne Nutzung der Antworten

                        /*if ($Capabilities['VideoSources'] > 0) {
                            // 4b.ONVIF Request GetVideoSources an \ONVIF\WSDL::DeviceIO
                             $this->GetVideoSources($XAddr[\ONVIF\NS::DeviceIO], \ONVIF\WSDL::DeviceIO); // array of Token
                        }

                        if ($Capabilities['AudioSources'] > 0) {
                            // 4b.ONVIF Request GetAudioSources an \ONVIF\WSDL::DeviceIO
                             $this->GetAudioSources($XAddr[\ONVIF\NS::DeviceIO], \ONVIF\WSDL::DeviceIO); // array of Token
                        }
                         */
                        // 4b.ONVIF Request GetDigitalInputs an \ONVIF\WSDL::DeviceIO
                        $this->GetDigitalInputs(); //array of tt:DigitalInput

                        // 4b.ONVIF Request GetRelayOutputs an \ONVIF\WSDL::DeviceIO
                        if (!$this->GetRelayOutputs($XAddr[\ONVIF\NS::DeviceIO], \ONVIF\WSDL::DeviceIO)) {
                            $XAddr[\ONVIF\NS::DeviceIO] = '';
                            $this->WriteAttributeArray('XAddr', $XAddr);
                            $this->GetRelayOutputs($XAddr[\ONVIF\NS::Management], \ONVIF\WSDL::Management);
                        }

                        /*
                        if ($Capabilities['SerialPorts'] > 0) {
                            // 4b.ONVIF Request GetSerialPorts an \ONVIF\WSDL::DeviceIO
                             $this->GetSerialPorts(); // array of tt:DeviceEntity
                        }*/
                    }
                } else {
                    if ($this->Profile->HasProfile(\ONVIF\Profile::T)) { //Profile T ist GetServiceCapabilities bei DeviceIO Pflicht
                        $this->Warnings = array_merge($this->Warnings, [$this->Translate('Failed to get DeviceIO service capabilities. Device reported ONVIF T scope, but is not compliant!')]);
                    }
                    //Fallback für reine Profile S Geräte
                    $VideoSources = $this->GetVideoSources($XAddr[\ONVIF\NS::Media], \ONVIF\WSDL::Media); // array of Token
                    if ($VideoSources) {
                        $this->WriteAttributeInteger('NbrOfVideoSources', count($VideoSources['VideoSources']));
                    }
                    $AudioSources = $this->GetAudioSources($XAddr[\ONVIF\NS::Media], \ONVIF\WSDL::Media); // array of Token
                    if ($AudioSources) {
                        $this->WriteAttributeInteger('NbrOfAudioSources', count($AudioSources['AudioSources']));
                    }
                    $this->GetRelayOutputs($XAddr[\ONVIF\NS::Management], \ONVIF\WSDL::Management);
                }
                if ($XAddr[\ONVIF\NS::Media2]) {
                    // Wenn \ONVIF\WSDL::Media2 unterstützt
                    // 4c.ONVIF Request GetServiceCapabilities an \ONVIF\WSDL::Media2
                    $MediaCapabilities = $this->GetServiceCapabilities($XAddr[\ONVIF\NS::Media2], \ONVIF\WSDL::Media2); // noch ohne Funktion..
                    if ($MediaCapabilities) {
                        $Capabilities = $MediaCapabilities['Capabilities'];
                        $this->WriteAttributeBoolean('HasRTSPStreaming', $Capabilities['StreamingCapabilities']['RTSPStreaming'] || $Capabilities['StreamingCapabilities']['RTP_RTSP_TCP']);
                        if (isset($Capabilities['SnapshotUri'])) {
                            $this->WriteAttributeBoolean('HasSnapshotUri', $Capabilities['SnapshotUri']);
                        } else {
                            $this->WriteAttributeBoolean('HasSnapshotUri', true);
                        }
                    } else {
                        if ($this->Profile->HasProfile(\ONVIF\Profile::T)) { //Profile T ist GetServiceCapabilities bei Media2 Pflicht
                            $this->Warnings = array_merge($this->Warnings, [$this->Translate('Failed to get Media2 service capabilities. Device reported ONVIF T scope, but is not compliant!')]);
                        }
                    }
                    // 4c.ONVIF Request GetProfiles an \ONVIF\WSDL::Media2
                    if (!$this->GetProfiles2()) {
                        $this->SetStatus(IS_EBASE + 2);
                        return;
                    }
                } else {
                    // Wenn \ONVIF\WSDL::Media2 NICHT unterstützt
                    // 4c.ONVIF Request GetServiceCapabilities an \ONVIF\WSDL::Media
                    $MediaCapabilities = $this->GetServiceCapabilities($XAddr[\ONVIF\NS::Media], \ONVIF\WSDL::Media); // noch ohne Funktion..
                    if ($MediaCapabilities) {
                        $Capabilities = $MediaCapabilities['Capabilities'];
                        if (isset($Capabilities['StreamingCapabilities']['NoRTSPStreaming'])) {
                            $this->WriteAttributeBoolean('HasRTSPStreaming', !$Capabilities['StreamingCapabilities']['NoRTSPStreaming']);
                        } else {
                            $this->WriteAttributeBoolean('HasRTSPStreaming', $Capabilities['StreamingCapabilities']['RTP_TCP'] || $Capabilities['StreamingCapabilities']['RTP_RTSP_TCP']);
                        }
                        if (isset($Capabilities['SnapshotUri'])) {
                            $this->WriteAttributeBoolean('HasSnapshotUri', $Capabilities['SnapshotUri']);
                        } else {
                            $this->WriteAttributeBoolean('HasSnapshotUri', true);
                        }
                    } else {
                        if ($this->Profile->HasProfile(\ONVIF\Profile::T)) { //Profile T ist GetServiceCapabilities bei Media Pflicht
                            $this->Warnings = array_merge($this->Warnings, [$this->Translate('Failed to get Media service capabilities. Device reported ONVIF T scope, but is not compliant!')]);
                        }
                    }

                    // 4c.ONVIF Request GetProfiles an \ONVIF\WSDL::Media
                    if (!$this->GetProfiles()) {
                        $this->SetStatus(IS_EBASE + 2);
                        return;
                    }
                }

                // Wenn \ONVIF\WSDL::PTZ unterstützt
                // 4d.ONVIF Request GetNodes an \ONVIF\WSDL::PTZ
                if ($XAddr[\ONVIF\NS::PTZ]) {
                    $PTZCapabilities = $this->GetServiceCapabilities($XAddr[\ONVIF\NS::PTZ], \ONVIF\WSDL::PTZ); // noch ohne Funktion.. todo
                    $this->GetNodes();
                }

                if ($XAddr[\ONVIF\NS::Imaging]) {
                    $ImagingCapabilities = $this->GetServiceCapabilities($XAddr[\ONVIF\NS::Imaging], \ONVIF\WSDL::Imaging); // noch ohne Funktion.. todo
                    if ($ImagingCapabilities) {
                    } else {
                        if ($this->Profile->HasProfile(\ONVIF\Profile::T)) { //Profile T ist GetServiceCapabilities bei Imaging Pflicht
                            $this->Warnings = array_merge($this->Warnings, [$this->Translate('Failed to get Imaging service capabilities. Device reported ONVIF T scope, but is not compliant!')]);
                        }
                    }
                }
                $AllEventProperties = [];
                if ($XAddr[\ONVIF\NS::Event]) {
                    $EventCapabilities = $this->GetServiceCapabilities($XAddr[\ONVIF\NS::Event], \ONVIF\WSDL::Event); // noch ohne Funktion.. todo
                    if ($EventCapabilities) {
                    } else {
                        if ($this->Profile->HasProfile(\ONVIF\Profile::T)) { //Profile T ist GetServiceCapabilities bei Event Pflicht
                            $this->Warnings = array_merge($this->Warnings, [$this->Translate('Failed to get Event service capabilities. Device reported ONVIF T scope, but is not compliant!')]);
                        }
                    }
                    //Jetzt Events auslesen
                    // 5.ONVIF Request GetEventProperties an \ONVIF\WSDL::Events
                    $GetEventProperties = $this->GetEventProperties();
                    if ($GetEventProperties) {
                        $AllEventProperties = array_merge($AllEventProperties, $GetEventProperties);
                    }
                }
                $AnalyticsTokens = $this->ReadAttributeArray('AnalyticsTokens');
                if ($XAddr[\ONVIF\NS::Analytics]) {
                    $AnalyticsCapabilities = $this->GetServiceCapabilities($XAddr[\ONVIF\NS::Analytics], \ONVIF\WSDL::Analytics);
                    if (isset($AnalyticsCapabilities['Capabilities']['AnalyticsModuleSupport'])) {
                        $this->WriteAttributeBoolean('AnalyticsModuleSupport', $AnalyticsCapabilities['Capabilities']['AnalyticsModuleSupport']);
                    }
                    if (isset($AnalyticsCapabilities['Capabilities']['RuleSupport'])) {
                        $this->WriteAttributeBoolean('RuleSupport', $AnalyticsCapabilities['Capabilities']['RuleSupport']);
                    }

                    foreach (array_keys($AnalyticsTokens) as $AnalyticsToken) {
//                        if ($this->ReadAttributeBoolean('AnalyticsModuleSupport')) {
                        $AnalyticsModulesTopicData = $this->GetSupportedAnalyticsModules($AnalyticsToken);
                        if ($AnalyticsModulesTopicData) {
                            $AllEventProperties = array_merge($AllEventProperties, $AnalyticsModulesTopicData);
                        }
//                        }
//                        if ($this->ReadAttributeBoolean('RuleSupport')) {
                        $SupportedRuleTopicData = $this->GetSupportedRules($AnalyticsToken);
                        if ($SupportedRuleTopicData) {
                            $AllEventProperties = array_merge($AllEventProperties, $SupportedRuleTopicData);
                        }
//                        }
                    }
                } else {
                    if (count($AnalyticsTokens)) {
                        $this->Warnings = array_merge($this->Warnings, [$this->Translate('Analytics events could not be retrieved. The device reported AnalyticsTokens, but the Analytics namespace and XAddr were not reported!')]);
                    }
                }
                $this->lock('EventProperties');
                $this->WriteAttributeArray('EventProperties', $AllEventProperties);
                $this->unlock('EventProperties');
                $this->SendDebug('EventProperties', $AllEventProperties, 0);
                $EventList = @$this->GetEventReceiverFormValues();
                $this->SendDebug('Update form', json_encode($EventList), 0);
                $this->UpdateFormField('Events', 'values', json_encode($EventList));
                $this->UpdateFormField('Events', 'visible', true);
            }
        } else {
            $this->SendDebug('VideoSources H.26x', $this->ReadAttributeArray('VideoSources'), 0);
            $this->SendDebug('VideoSources JPEG', $this->ReadAttributeArray('VideoSourcesJPEG'), 0);
            $XAddr = $this->ReadAttributeArray('XAddr');
        }
        // Start Event Handler
        if ($XAddr[\ONVIF\NS::Event]) {
            if ($this->Profile->Profile == \ONVIF\Profile::T) { // Wenn NUR Profile T unterstützt wird, dann PullPoint
                // CreatePullPointSubscription an \ONVIF\WSDL::Events
                IPS_RunScriptText('IPS_Sleep(1000);IPS_RequestAction(' . $this->InstanceID . ',"CreatePullPointSubscription",true);');
            } else {
                //WSSubscription
                $this->RegisterHook('/hook/ONVIFEvents/IO/' . $this->InstanceID);
                if ($this->GetConsumerAddress()) { // yeah, we can receive events
                    IPS_RunScriptText('IPS_Sleep(1000);IPS_RequestAction(' . $this->InstanceID . ',"Subscribe",true);');
                } else { // we cannot receive events :(
                    $this->WriteAttributeString('SubscriptionReference', '');
                    $this->WriteAttributeString('SubscriptionId', '');
                    $this->UpdateFormField('SubscriptionReference', 'caption', $this->Translate('This device not support events.'));
                    $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
                }
            }
        } else {
            $this->Warnings = array_merge($this->Warnings, [$this->Translate('This device does not support ONVIF events, but it is mandatory.')]);
            $this->LogMessage($this->Translate('Interface connected'), KL_MESSAGE);
            $this->SetStatus(IS_ACTIVE);
        }
    }

    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        if ($Data['Function'] == 'GetCapabilities') {
            $Capabilities['VideoSources'] = $this->ReadAttributeArray('VideoSources');
            $Capabilities['VideoSourcesJPEG'] = $this->ReadAttributeArray('VideoSourcesJPEG');
            $Capabilities['RelayOutputs'] = $this->ReadAttributeArray('RelayOutputs');
            $Capabilities['DigitalInputs'] = $this->ReadAttributeArray('DigitalInputs');
            $Capabilities['NbrOfVideoSources'] = $this->ReadAttributeInteger('NbrOfVideoSources');
            $Capabilities['NbrOfAudioSources'] = $this->ReadAttributeInteger('NbrOfAudioSources');
            $Capabilities['NbrOfOutputs'] = $this->ReadAttributeInteger('NbrOfOutputs');
            $Capabilities['NbrOfInputs'] = $this->ReadAttributeInteger('NbrOfInputs');
            $Capabilities['NbrOfSerialPorts'] = $this->ReadAttributeInteger('NbrOfSerialPorts');
            $Capabilities['HasSnapshotUri'] = $this->ReadAttributeBoolean('HasSnapshotUri');
            $Capabilities['HasRTSPStreaming'] = $this->ReadAttributeBoolean('HasRTSPStreaming');
            $Capabilities['AnalyticsModuleSupport'] = $this->ReadAttributeBoolean('AnalyticsModuleSupport');
            $Capabilities['AnalyticsTokens'] = $this->ReadAttributeArray('AnalyticsTokens');
            $Capabilities['RuleSupport'] = $this->ReadAttributeBoolean('RuleSupport');
            $Capabilities['XAddr'] = $this->ReadAttributeArray('XAddr');
            return serialize($Capabilities);
        }
        if ($Data['Function'] == 'SetSynchronizationPoint') {
            return $this->SetSynchronizationPoint();
        }
        if ($Data['Function'] == 'GetUrl') {
            return serialize($this->Host);
        }
        if ($Data['Function'] == 'GetCredentials') {
            $Credentials['Username'] = $this->ReadPropertyString('Username');
            $Credentials['Password'] = $this->ReadPropertyString('Password');
            return serialize($Credentials);
        }
        if ($Data['Function'] == 'GetEvents') {
            if ($Data['Instance'] != 0) {
                $this->lock('EventProperties');
            }
            $Events = $this->ReadAttributeArray('EventProperties');
            $SkippedTopics = $Data['SkippedTopics'];

            $FoundEvents = [];
            if ($Data['Pattern'] == '') {
                if ($Data['Instance'] == 0) {
                    foreach ($SkippedTopics as $SkippedTopic) {
                        foreach (array_keys($Events) as $Topic) {
                            if (strpos($Topic, $SkippedTopic) !== false) {
                                unset($Events[$Topic]);
                            }
                        }
                    }
                    foreach (array_keys($Events) as $FullTopic) {
                        $TopicParts = explode('/', $FullTopic);
                        array_pop($TopicParts);
                        $Topic = '';
                        foreach ($TopicParts as $TopicPart) {
                            $Topic .= $TopicPart . '/';
                            $FoundEvents[$Topic] = [];
                        }
                        $FoundEvents[$FullTopic] = [];
                    }
                }
            } else {
                if (array_key_exists($Data['Pattern'], $Events)) {
                    $FoundEvents[$Data['Pattern']] = $Events[$Data['Pattern']];
                    if (($Data['Instance'] != 0) && (!in_array($Data['Instance'], $Events[$Data['Pattern']]['Receivers']))) {
                        $Events[$Data['Pattern']]['Receivers'][] = $Data['Instance'];
                    }
                } else {
                    foreach (array_keys($Events) as $FullTopic) {
                        if (stripos($FullTopic, $Data['Pattern']) !== false) {
                            $TopicParts = explode('/', $FullTopic);
                            foreach ($TopicParts as $TopicPart) {
                                if (stripos($TopicPart, $Data['Pattern']) === false) {
                                    array_pop($TopicParts);
                                } else {
                                    break;
                                }
                            }
                            array_pop($TopicParts);
                            $Topic = '';
                            foreach ($TopicParts as $TopicPart) {
                                $Topic .= $TopicPart . '/';
                                $FoundEvents[$Topic] = [];
                            }
                            $FoundEvents[$FullTopic] = $Events[$FullTopic];
                            if (($Data['Instance'] != 0) && (!in_array($Data['Instance'], $Events[$FullTopic]['Receivers']))) {
                                $Events[$FullTopic]['Receivers'][] = $Data['Instance'];
                            }
                        }
                    }
                }
            }
            if ($Data['Instance'] != 0) {
                $this->WriteAttributeArray('EventProperties', $Events);
                $this->unlock('EventProperties');
                $EventList = $this->GetEventReceiverFormValues();
                $this->UpdateFormField('Events', 'values', json_encode($EventList));
            }
            return serialize($FoundEvents);
        }
        if ($this->GetStatus() != IS_ACTIVE) {
            return serialize(false);
        }
        $this->SendDebug('Forward URI', $Data['URI'], 0);
        $this->SendDebug('Forward wsdl', $Data['wsdl'], 0);
        $this->SendDebug('Forward Function', $Data['Function'], 0);
        $this->SendDebug('Forward Params', $Data['Params'], 0);
        $this->SendDebug('Forward useLogin', $Data['useLogin'], 0);
        $Result = $this->SendData($Data['URI'], $Data['wsdl'], $Data['Function'], $Data['useLogin'], $Data['Params']);
        return serialize($Result);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Subscribe':
                if ($this->Subscribe()) {
                    return;
                }
                // WSSubscription failed, try PullPointSubscription
                // No break. Add additional comment above this line if intentional
            case 'CreatePullPointSubscription':
                return $this->CreatePullPointSubscription();
            case 'PullMessages':
                return $this->PullMessages();
            case 'Renew':
                return $this->Renew();
            case  'Reload':
                $this->Unsubscribe();
                if ($this->GetStatus() == IS_INACTIVE) {
                    $this->WriteAttributeString('ConsumerAddress', '');
                    return;
                }
                $this->UpdateFormField('ErrorTitle', 'caption', $this->Translate('Please wait!'));
                $this->UpdateFormField('ErrorText', 'caption', $this->Translate('Determine abilities of this device'));
                $this->UpdateFormField('ErrorPopup', 'visible', true);
                $this->WriteAttributeString('ConsumerAddress', '');
                $this->ApplyChanges();
                $this->ReloadForm();
                return;
            case  'ShowLastError':
                $Data = json_decode($Value, true);
                $this->UpdateFormField('ErrorTitle', 'caption', $Data['Title']);
                $this->UpdateFormField('ErrorText', 'caption', $Data['Message']);
                $this->UpdateFormField('ErrorPopup', 'visible', true);
                return;
            case'KernelReady':
                return $this->KernelReady();
        }
    }
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if ($this->GetStatus() == IS_ACTIVE) {
            if ($this->Profile->Profile == \ONVIF\Profile::T) {
                $Form['elements'][3]['visible'] = false;
            }
            $Form['actions'][1]['items'][0]['items'] = $this->GetDeviceDataForForm();
            $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
            if ($SubscriptionReference == '') {
                $SubscriptionReference = $this->Translate('This device not support events.');
                $Form['actions'][4]['visible'] = false;
            }
            $Form['actions'][3]['items'][1]['caption'] = $SubscriptionReference;
        }
        if ($this->GetStatus() == IS_INACTIVE) {
            $Form['actions'][1]['visible'] = false;
            $Form['actions'][3]['visible'] = false;
        }
        $ConsumerAddress = $this->ReadAttributeString('ConsumerAddress');
        if ($ConsumerAddress != '') {
            $Form['actions'][2]['visible'] = true;
        }
        $Form['actions'][2]['items'][1]['caption'] = $ConsumerAddress;

        $EventList = @$this->GetEventReceiverFormValues();
        if ($EventList) {
            $Form['actions'][4]['values'] = $EventList;
        }
        $Warnings = $this->Warnings;
        if (count($Warnings) && $this->ReadPropertyBoolean('Open')) {
            $WarningText = implode("\r\n", $Warnings);
            $Form['actions'][5]['visible'] = true;
            $Form['actions'][5]['popup']['items'][0]['caption'] = $this->Translate('Some features will not work properly');
            $Form['actions'][5]['popup']['items'][1]['caption'] = $WarningText;
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }
    protected function GetDeviceDataForForm()
    {
        $Device = $this->GetDeviceInformation();
        if ($Device) {
            $InfoItems = [
                [
                    'width'     => '450px',
                    'type'      => 'Label',
                    'caption'   => 'Model: ' . $Device['Model']
                ],
                [
                    'type'      => 'Label',
                    'caption'   => $this->Translate('Manufacturer: ') . $Device['Manufacturer']
                ],
                [
                    'type'      => 'Label',
                    'caption'   => 'Firmware: ' . $Device['FirmwareVersion']
                ],
                [
                    'type'      => 'Label',
                    'caption'   => $this->Translate('Serial number: ') . $Device['SerialNumber']
                ]
            ];
        }
        $this->lock('Profile');
        $InfoItems[] = [
            'width'     => '450px',
            'type'      => 'Label',
            'caption'   => $this->Translate('Supported ONVIF Profile: ') . $this->Profile->toString()
        ];
        $InfoItems[] = [
            'type'      => 'Label',
            'caption'   => $this->Translate('Used event handling: ') . ($this->GetTimerInterval('RenewSubscription') ? 'WS-BaseNotification' : ($this->GetTimerInterval('PullMessages') ? 'PullPoint' : $this->Translate('none')))
        ];

        $this->unlock('Profile');
        $DeviceItems = [
            [
                'width'     => '200px',
                'type'      => 'Label',
                'caption'   => $this->Translate('Videosources: ') . $this->ReadAttributeInteger('NbrOfVideoSources')
            ],
            [
                'type'      => 'Label',
                'caption'   => $this->Translate('Audiosources: ') . $this->ReadAttributeInteger('NbrOfAudioSources')
            ],
            [
                'type'      => 'Label',
                'caption'   => $this->Translate('Inputs: ') . $this->ReadAttributeInteger('NbrOfInputs')
            ],
            [
                'type'      => 'Label',
                'caption'   => $this->Translate('Outputs: ') . $this->ReadAttributeInteger('NbrOfOutputs')
            ],
            [
                'type'      => 'Label',
                'caption'   => $this->Translate('Serial ports: ') . $this->ReadAttributeInteger('NbrOfSerialPorts')
            ],
            [
                'type'      => 'Label',
                'caption'   => 'RTSP Streaming: ' . ($this->ReadAttributeBoolean('HasRTSPStreaming') ? $this->Translate('supported') : $this->Translate('not supported'))
            ],
            [
                'type'      => 'Label',
                'caption'   => 'Snapshots: ' . ($this->ReadAttributeBoolean('HasSnapshotUri') ? $this->Translate('supported') : $this->Translate('not supported'))
            ],
            [
                'type'      => 'Label',
                'caption'   => 'Analytics: ' . ($this->ReadAttributeBoolean('AnalyticsModuleSupport') ? $this->Translate('supported') : $this->Translate('not supported'))
            ],
            [
                'type'      => 'Label',
                'caption'   => 'Rules: ' . ($this->ReadAttributeBoolean('RuleSupport') ? $this->Translate('supported') : $this->Translate('not supported'))
            ]

        ];

        return  [
            [
                'type'    => 'RowLayout',
                'items'   => [
                    [
                        'type' => 'ColumnLayout',
                        'items'=> $InfoItems
                    ],
                    [
                        'type' => 'ColumnLayout',
                        'items'=> $DeviceItems
                    ]
                ]
            ]
        ];
    }

    protected function ShowLastError(string $ErrorMessage, string $ErrorTitle = 'Answer from Device:')
    {
        $Data = json_encode([
            'Message'=> $ErrorMessage,
            'Title'  => $ErrorTitle
        ]);
        IPS_RunScriptText('IPS_Sleep(1000);IPS_RequestAction(' . $this->InstanceID . ',"ShowLastError",' . var_export($Data, true) . ');');
    }

    protected function GetConsumerAddress()
    {
        if (IPS_GetOption('NATSupport')) {
            $ip = IPS_GetOption('NATPublicIP');
            if ($ip == '') {
                $ip = $this->MyIP;
                if ($ip == '') {
                    $this->SendDebug('NAT enabled ConsumerAddress', 'Invalid', 0);
                    $this->UpdateFormField('EventHook', 'caption', $this->Translate('NATPublicIP is missing in special switches!'));
                    $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                    $this->ShowLastError('Error', $this->Translate('NAT support is active, but no public address is set.'));
                    return false;
                }
            }
            $Debug = 'NAT enabled ConsumerAddress';
        } else {
            $ip = $this->MyIP;
            if ($ip == '') {
                $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                socket_bind($sock, '0.0.0.0', 0);
                $Host = parse_url($this->Host);
                $Host['port'] = isset($Host['port']) ? $Host['port'] : 80;
                @socket_connect($sock, $Host['host'], $Host['port']);
                $ip = '';
                socket_getsockname($sock, $ip);
                @socket_close($sock);
                if ($ip == '0.0.0.0') {
                    $this->UpdateFormField('EventHookRow', 'visible', true);
                    $this->SendDebug('ConsumerAddress', 'Invalid', 0);
                    $this->UpdateFormField('EventHook', 'caption', $this->Translate('Invalid'));
                    $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                    return false;
                }
            }
            $Debug = 'ConsumerAddress';
        }
        $Url = ($this->MyHTTPS ? 'https://' : 'http://') . $ip . ':' . $this->MyPort . '/hook/ONVIFEvents/IO/' . $this->InstanceID;
        $this->SendDebug($Debug, $Url, 0);
        $this->UpdateFormField('EventHookRow', 'visible', true);
        $this->UpdateFormField('EventHook', 'caption', $Url);
        $this->WriteAttributeString('ConsumerAddress', $Url);
        return true;
    }
    protected function CreatePullPointSubscription()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        if ($XAddr[\ONVIF\NS::Event] == '') {
            return false;
        }
        $empty = '';
        $Params = [
            'InitialTerminationTime' => 'PT60S'
        ];
        $CreatePullPointResult = $this->SendData($XAddr[\ONVIF\NS::Event], \ONVIF\WSDL::Event, 'CreatePullPointSubscription', true, $Params, $empty);
        if (is_a($CreatePullPointResult, 'SoapFault')) {
            $this->SetStatus(IS_EBASE + 3);
            $this->LogMessage($this->Translate('Connection lost'), KL_ERROR);
            $this->ShowLastError($CreatePullPointResult->getMessage());
            return false;
        }
        $SubscriptionReference = $CreatePullPointResult->SubscriptionReference->Address->{'_'};
        $this->SendDebug('SubscriptionReference', $SubscriptionReference, 0);
        $ReferenceUrl = parse_url($SubscriptionReference, PHP_URL_HOST);
        $ReferenceUrl = $ReferenceUrl !== null ? $ReferenceUrl : 'INVALID';
        if (strpos($this->ReadPropertyString('Address'), $ReferenceUrl) === false) {
            $this->SendDebug('Warning', 'invalid Subscription-Reference, try to fix it', 0);
            $Url = parse_url($SubscriptionReference);
            $Url['host'] = parse_url($this->ReadPropertyString('Address'), PHP_URL_HOST);
            $Url['port'] = parse_url($this->ReadPropertyString('Address'), PHP_URL_PORT);
            $SubscriptionReference = self::unparse_url($Url);
        }
        $this->WriteAttributeString('SubscriptionReference', $SubscriptionReference);
        $this->UpdateFormField('SubscriptionReference', 'caption', $SubscriptionReference);
        $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
        if (property_exists($CreatePullPointResult->SubscriptionReference, 'ReferenceParameters')) {
            $SubscriptionId = property_exists($CreatePullPointResult->SubscriptionReference->ReferenceParameters, 'any') ? $CreatePullPointResult->SubscriptionReference->ReferenceParameters->any : '';
            $this->SendDebug('SubscriptionId', $SubscriptionId, 0);
            $this->WriteAttributeString('SubscriptionId', $SubscriptionId);
        } else {
            $this->WriteAttributeString('SubscriptionId', '');
        }
        $this->isSubscribed = true;
        $this->SetTimerInterval('PullMessages', 1000);
        $this->SetStatus(IS_ACTIVE);
        $this->UpdateFormField('DeviceData', 'items', json_encode($this->GetDeviceDataForForm()));
        $this->UpdateFormField('DeviceDataPanel', 'visible', true);
        $this->UpdateFormField('DeviceDataPanel', 'expanded', true);
        $this->UpdateFormField('Events', 'visible', true);
        $this->SetSynchronizationPoint();
        return true;
    }
    protected function PullMessages()
    {
        if (!$this->isSubscribed) {
            return true;
        }
        $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
        if ($SubscriptionReference == '') {
            $this->SendDebug('ERROR PullMessages', 'No SubscriptionReference', 0);
            $this->LogMessage($this->Translate('Call PullMessages with no SubscriptionReference'), KL_ERROR);
            return $this->CreatePullPointSubscription();
        }
        $Params = [
            'Timeout'     => 'PT0S',
            'MessageLimit'=> 8
        ];
        $Response = '';
        $PullMessagesResult = $this->SendData($SubscriptionReference, \ONVIF\WSDL::Event, 'PullMessages', true, $Params, $Response);
        if (is_a($PullMessagesResult, 'SoapFault')) {
            $this->SendDebug('ERROR PullMessages', 'No Response', 0);
            $this->LogMessage($this->Translate('Error PullMessages with:') . $PullMessagesResult->getMessage(), KL_ERROR);
            $this->SetStatus(IS_EBASE + 3);
            $this->isSubscribed = false;
            $this->SetTimerInterval('PullMessages', 0);
            return false;
        }
        if (!is_object($PullMessagesResult)) {
            $this->SendDebug('ERROR PullMessages', 'No Response', 0);
            $this->LogMessage($this->Translate('Error PullMessages with no Response'), KL_ERROR);
            $this->SetStatus(IS_EBASE + 3);
            $this->isSubscribed = false;
            $this->SetTimerInterval('PullMessages', 0);
            return false;
        }
        if (!property_exists($PullMessagesResult, 'NotificationMessage')) {
            return true;
        }
        return $this->DecodeNotificationMessage($Response);
    }
    protected function Subscribe()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        if ($XAddr[\ONVIF\NS::Event] == '') {
            return false;
        }
        $Params = [
            'ConsumerReference'      => [
                'Address' => $this->ReadAttributeString('ConsumerAddress')
            ],
            'InitialTerminationTime' => 'PT60S'
        ];
        $Response = '';
        $this->WaitForFirstEvent = true;
        $SubscribeResult = $this->SendData($XAddr[\ONVIF\NS::Event], \ONVIF\WSDL::Event, 'Subscribe', true, $Params, $Response);
        if (is_a($SubscribeResult, 'SoapFault')) {
            /*$this->SetStatus(IS_EBASE + 3);
            $this->LogMessage($this->Translate('Connection lost'), KL_ERROR);
            $this->ShowLastError($SubscribeResult->getMessage());
             */
            $this->WaitForFirstEvent = false;
            return false;
        }
        $SubscriptionReference = $SubscribeResult->SubscriptionReference->Address->{'_'};
        $this->SendDebug('SubscriptionReference', $SubscriptionReference, 0);

        $ReferenceUrl = parse_url($SubscriptionReference, PHP_URL_HOST);
        $ReferenceUrl = $ReferenceUrl !== null ? $ReferenceUrl : 'INVALID';
        if (strpos($this->ReadPropertyString('Address'), $ReferenceUrl) === false) {
            $this->SendDebug('Warning', 'invalid Subscription-Reference, try to fix it', 0);
            $Url = parse_url($SubscriptionReference);
            $Url['host'] = parse_url($this->ReadPropertyString('Address'), PHP_URL_HOST);
            $Url['port'] = parse_url($this->ReadPropertyString('Address'), PHP_URL_PORT);
            $SubscriptionReference = self::unparse_url($Url);
        }
        $this->WriteAttributeString('SubscriptionReference', $SubscriptionReference);
        $this->UpdateFormField('SubscriptionReference', 'caption', $SubscriptionReference);
        $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
        if (property_exists($SubscribeResult->SubscriptionReference, 'ReferenceParameters')) {
            $SubscriptionId = property_exists($SubscribeResult->SubscriptionReference->ReferenceParameters, 'any') ? $SubscribeResult->SubscriptionReference->ReferenceParameters->any : '';
            $this->SendDebug('SubscriptionId', $SubscriptionId, 0);
            $this->WriteAttributeString('SubscriptionId', $SubscriptionId);
        } else {
            $this->WriteAttributeString('SubscriptionId', '');
        }
        $this->isSubscribed = true;
        $this->SetTimerInterval('RenewSubscription', 55 * 1000);
        $this->UpdateFormField('DeviceData', 'items', json_encode($this->GetDeviceDataForForm()));
        $this->UpdateFormField('DeviceDataPanel', 'visible', true);
        $this->UpdateFormField('DeviceDataPanel', 'expanded', true);
        $this->UpdateFormField('Events', 'visible', true);

        $this->SetSynchronizationPoint();
        for ($i = 0; $i < 400; $i++) {
            if (!$this->WaitForFirstEvent) {
                $this->SetStatus(IS_ACTIVE);
                return true;
            }
            IPS_Sleep(5);
        }
        $this->WaitForFirstEvent = false;
        $this->SetStatus(IS_EBASE + 4);
        return false;
    }
    protected function SetSynchronizationPoint()
    {
        if (!$this->isSubscribed) {
            return true;
        }
        $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
        if ($SubscriptionReference == '') {
            $this->SendDebug('ERROR SetSynchronizationPoint', 'No SubscriptionReference', 0);
            $this->LogMessage($this->Translate('Call SetSynchronizationPoint with no SubscriptionReference'), KL_ERROR);
            return false;
        }
        $SubscriptionId = $this->ReadAttributeString('SubscriptionId');
        if ($SubscriptionId != '') {
            $xml = new DOMDocument();
            $xml->loadXML($SubscriptionId);
            $ns = $xml->firstChild->namespaceURI;
            $name = $xml->firstChild->nodeName;
            $Header[] = new SoapHeader($ns, $name, new SoapVar($SubscriptionId, XSD_ANYXML), true);
        }
        $empty = '';
        $SetSynchronizationPointResult = $this->SendData($SubscriptionReference, \ONVIF\WSDL::Event, 'SetSynchronizationPoint', true, [], $empty);
        if (is_a($SetSynchronizationPointResult, 'SoapFault')) {
            $this->LogMessage($this->Translate('Error SetSynchronizationPoint with:') . '(' . $SetSynchronizationPointResult->getCode() . ')' . $SetSynchronizationPointResult->getMessage(), KL_ERROR);
            return false;
        }
        return true;
    }
    protected function Renew()
    {
        if (!$this->isSubscribed) {
            return true;
        }
        $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
        if ($SubscriptionReference == '') {
            $this->SendDebug('ERROR Renew', 'No SubscriptionReference', 0);
            $this->LogMessage($this->Translate('Call Renew with no SubscriptionReference'), KL_ERROR);
            return false;
        }
        $Action = 'http://docs.oasis-open.org/wsn/bw-2/SubscriptionManager/RenewRequest';
        $Header[] = new SoapHeader('http://www.w3.org/2005/08/addressing', 'Action', new SoapVar($Action, XSD_ANYURI, '', 'http://www.w3.org/2005/08/addressing'), true);
        $Header[] = new SoapHeader('http://www.w3.org/2005/08/addressing', 'To', new SoapVar($SubscriptionReference, XSD_ANYURI, '', 'http://www.w3.org/2005/08/addressing'), true);
        $SubscriptionId = $this->ReadAttributeString('SubscriptionId');
        if ($SubscriptionId != '') {
            $xml = new DOMDocument();
            $xml->loadXML($SubscriptionId);
            $ns = $xml->firstChild->namespaceURI;
            $name = $xml->firstChild->nodeName;
            $Header[] = new SoapHeader($ns, $name, new SoapVar($SubscriptionId, XSD_ANYXML), true);
        }
        $Params = [
            'TerminationTime' => 'PT1M'
        ];
        $empty = '';
        $RenewResult = $this->SendData($SubscriptionReference, \ONVIF\WSDL::Event, 'Renew', true, $Params, $empty, $Header);
        if (is_a($RenewResult, 'SoapFault')) {
            $this->LogMessage($this->Translate('Error Renew Subscription with:') . $RenewResult->getMessage(), KL_ERROR);
            $this->SetStatus(IS_EBASE + 3);
            $this->isSubscribed = false;
            $this->SetTimerInterval('RenewSubscription', 0);
            return false;
        }
        if (!is_object($RenewResult)) {
            $this->SendDebug('ERROR Renew', 'No Response', 0);
            $this->LogMessage($this->Translate('Error Renew with no Response'), KL_ERROR);
            $this->SetStatus(IS_EBASE + 3);
            $this->isSubscribed = false;
            $this->SetTimerInterval('RenewSubscription', 0);
            return false;
        }
        return true;
    }

    protected function Unsubscribe()
    {
        if (!$this->isSubscribed) {
            return true;
        }
        $this->SetTimerInterval('RenewSubscription', 0);
        $this->SetTimerInterval('PullMessages', 0);
        $this->isSubscribed = false;
        $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
        $this->WriteAttributeString('SubscriptionReference', '');
        if ($SubscriptionReference == '') {
            $this->SendDebug('ERROR Unsubscribe', 'No SubscriptionReference', 0);
            $this->LogMessage($this->Translate('Call Renew with no SubscriptionReference'), KL_ERROR);
            return false;
        }
        $Action = 'http://docs.oasis-open.org/wsn/bw-2/SubscriptionManager/UnsubscribeRequest';
        $Header[] = new SoapHeader('http://www.w3.org/2005/08/addressing', 'Action', new SoapVar($Action, XSD_ANYURI, '', 'http://www.w3.org/2005/08/addressing'), true);
        $Header[] = new SoapHeader('http://www.w3.org/2005/08/addressing', 'To', new SoapVar($SubscriptionReference, XSD_ANYURI, '', 'http://www.w3.org/2005/08/addressing'), true);
        $SubscriptionId = $this->ReadAttributeString('SubscriptionId');
        $this->WriteAttributeString('SubscriptionId', '');
        if ($SubscriptionId != '') {
            $xml = new DOMDocument();
            $xml->loadXML($SubscriptionId);
            $ns = $xml->firstChild->namespaceURI;
            $name = $xml->firstChild->nodeName;
            $Header[] = new SoapHeader($ns, $name, new SoapVar($SubscriptionId, XSD_ANYXML));
        }
        $empty = '';
        $UnsubscribeResult = $this->SendData($SubscriptionReference, \ONVIF\WSDL::Event, 'Unsubscribe', true, [], $empty, $Header);
        if (is_a($UnsubscribeResult, 'SoapFault')) {
            trigger_error($UnsubscribeResult->getMessage(), E_USER_NOTICE);
        }
        return true;
    }
    protected function GetEventProperties()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        if ($XAddr[\ONVIF\NS::Event] == '') {
            return false;
        }
        $Response = '';
        $EventPropertiesResult = $this->SendData($XAddr[\ONVIF\NS::Event], \ONVIF\WSDL::Event, 'GetEventProperties', true, [], $Response);
        if (is_a($EventPropertiesResult, 'SoapFault')) {
            return false;
        }

        $xml = new DOMDocument();
        $xml->loadXML($Response);
        $xpath = new DOMXPath($xml);
        foreach (\ONVIF\NS::$Namespaces as $NSKey => $Namespace) {
            $EventNS[$NSKey] = $xml->lookupPrefix($Namespace);
            $xpath->registerNamespace($NSKey, $Namespace);
        }

        $query = '//wstop:TopicSet';
        $prefixPathLen = strlen($xpath->query($query, null, true)[0]->getNodePath());
        $query = '//*[@wstop:topic="true"]/tt:MessageDescription';
        $wsTopics = $xpath->query($query);
        $TopicData = [];
        foreach ($wsTopics as $wsData) {
            $Topic = preg_replace('/\[\d*\]/', '', substr($wsData->parentNode->getNodePath(), $prefixPathLen + 1));
            $SourcesNodeList = $xpath->query('tt:Source/tt:SimpleItemDescription', $wsData, true);
            $Sources = $this->GetEventMessageDescriptionValues($SourcesNodeList, $EventNS);
            $DataNodeList = $xpath->query('tt:Data/tt:SimpleItemDescription', $wsData, true);
            $Data = $this->GetEventMessageDescriptionValues($DataNodeList, $EventNS);
            $TopicData[$Topic]['Sources'] = $Sources;
            $TopicData[$Topic]['Data'] = $Data;
            $TopicData[$Topic]['Receivers'] = [];
        }
        return $TopicData;
    }

    protected function GetProfiles2(string $Token = null, string $ConfigurationEnumeration = \ONVIF\Media2Conf::All)
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        $Params['Type'] = $ConfigurationEnumeration;
        if ($Token) {
            $Params['Token'] = $Token;
        }
        $ProfileResult = $this->SendData($XAddr[\ONVIF\NS::Media2], \ONVIF\WSDL::Media2, 'GetProfiles', true, $Params);
        if (is_a($ProfileResult, 'SoapFault')) {
            $this->LogMessage($this->lastSOAPError, KL_ERROR);
            $this->ShowLastError($this->lastSOAPError);
            return false;
        }
        if (is_object($ProfileResult->Profiles)) {
            $Profiles = [];
            $Profiles[] = json_decode(json_encode($ProfileResult), true)['Profiles'];
        } else {
            $Profiles = json_decode(json_encode($ProfileResult), true)['Profiles'];
        }

        $H264Profiles = array_filter($Profiles, function ($Profile)
        {
            if (isset($Profile['Configurations']['VideoEncoder']['Encoding'])) {
                if (strtoupper($Profile['Configurations']['VideoEncoder']['Encoding']) == 'JPEG') {
                    return false;
                }
            }
            return true;
        });
        $H264VideoSourcesItems = [];
        foreach ($H264Profiles as $Profile) {
            if (!array_key_exists('VideoEncoder', $Profile['Configurations'])) {
                continue;
            }
            $H264VideoSourcesItems[$Profile['Configurations']['VideoSource']['SourceToken']]['VideoSourceToken'] = $Profile['Configurations']['VideoSource']['SourceToken'];
            if ($Profile['Configurations']['VideoSource']['Name'] == '') {
                $H264VideoSourcesItems[$Profile['Configurations']['VideoSource']['SourceToken']]['VideoSourceName'] = $Profile['Configurations']['VideoSource']['SourceToken'];
            } else {
                $H264VideoSourcesItems[$Profile['Configurations']['VideoSource']['SourceToken']]['VideoSourceName'] = $Profile['Configurations']['VideoSource']['Name'];
            }
            $H264VideoSourcesItems[$Profile['Configurations']['VideoSource']['SourceToken']]['Profile'][] = [
                'Name'       => $Profile['Configurations']['VideoEncoder']['Name'],
                'token'      => $Profile['token'],
                'ptztoken'   => isset($Profile['Configurations']['PTZ']['token']) ? $Profile['Configurations']['PTZ']['token'] : '',
                'Encoding'   => isset($Profile['Configurations']['VideoEncoder']['Encoding']) ? $Profile['Configurations']['VideoEncoder']['Encoding'] : 'unknown',
                'Resolution' => isset($Profile['Configurations']['VideoEncoder']['Resolution']) ? $Profile['Configurations']['VideoEncoder']['Resolution'] : 'unknown',
                'RateControl'=> isset($Profile['Configurations']['VideoEncoder']['RateControl']) ? $Profile['Configurations']['VideoEncoder']['RateControl'] : 'unknown'
            ];
        }
        $H264VideoSources = array_values($H264VideoSourcesItems);
        $this->SendDebug('VideoSources H.26x', $H264VideoSources, 0);
        $this->WriteAttributeArray('VideoSources', $H264VideoSources);
        $JPEGProfiles = array_filter($Profiles, function ($Profile)
        {
            if (isset($Profile['Configurations']['VideoEncoder']['Encoding'])) {
                if (strtoupper($Profile['Configurations']['VideoEncoder']['Encoding']) == 'JPEG') {
                    return true;
                }
            }
            return false;
        });
        if (count($JPEGProfiles) == 0) { //fallback, no JPEG found
            $JPEGProfiles = $Profiles;
        }
        $JPEGVideoSourcesItems = [];
        foreach ($JPEGProfiles as $Profile) {
            if (!array_key_exists('VideoEncoder', $Profile['Configurations'])) {
                continue;
            }
            $JPEGVideoSourcesItems[$Profile['Configurations']['VideoSource']['SourceToken']]['VideoSourceToken'] = $Profile['Configurations']['VideoSource']['SourceToken'];
            if ($Profile['Configurations']['VideoSource']['Name'] == '') {
                $JPEGVideoSourcesItems[$Profile['Configurations']['VideoSource']['SourceToken']]['VideoSourceName'] = $Profile['Configurations']['VideoSource']['SourceToken'];
            } else {
                $JPEGVideoSourcesItems[$Profile['Configurations']['VideoSource']['SourceToken']]['VideoSourceName'] = $Profile['Configurations']['VideoSource']['Name'];
            }
            $JPEGVideoSourcesItems[$Profile['Configurations']['VideoSource']['SourceToken']]['Profile'][] = [
                'Name'       => $Profile['Configurations']['VideoEncoder']['Name'],
                'token'      => $Profile['token'],
                'Encoding'   => isset($Profile['Configurations']['VideoEncoder']['Encoding']) ? $Profile['Configurations']['VideoEncoder']['Encoding'] : 'unknown',
                'Resolution' => isset($Profile['Configurations']['VideoEncoder']['Resolution']) ? $Profile['Configurations']['VideoEncoder']['Resolution'] : 'unknown',
                'RateControl'=> isset($Profile['Configurations']['VideoEncoder']['RateControl']) ? $Profile['Configurations']['VideoEncoder']['RateControl'] : 'unknown'
            ];
        }
        $JPEGVideoSources = array_values($JPEGVideoSourcesItems);
        $this->SendDebug('VideoSources JPEG', $JPEGVideoSources, 0);
        $this->WriteAttributeArray('VideoSourcesJPEG', $JPEGVideoSources);
        $AnalyticsTokens = [];
        $AnalyticsProfiles = array_filter($Profiles, function ($Profile)
        {
            if (isset($Profile['Configurations']['Analytics'])) {
                return true;
            }
        });
        foreach ($AnalyticsProfiles as $AnalyticsProfile) {
            $Token = $AnalyticsProfile['Configurations']['Analytics']['token'];
            $Name = $AnalyticsProfile['Configurations']['Analytics']['Name'];
            $AnalyticsTokens[$Token] = $Name;
        }
        $this->SendDebug('AnalyticsTokens', $AnalyticsTokens, 0);
        $this->WriteAttributeArray('AnalyticsTokens', $AnalyticsTokens);
        return true;
    }
    protected function GetProfiles()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        $ProfileResult = $this->SendData($XAddr[\ONVIF\NS::Media], \ONVIF\WSDL::Media, 'GetProfiles', true);
        if (is_a($ProfileResult, 'SoapFault')) {
            $this->LogMessage($this->lastSOAPError, KL_ERROR);
            $this->ShowLastError($this->lastSOAPError);
            return false;
        }
        if (is_object($ProfileResult->Profiles)) {
            $Profiles = [];
            $Profiles[] = json_decode(json_encode($ProfileResult), true)['Profiles'];
        } else {
            $Profiles = json_decode(json_encode($ProfileResult), true)['Profiles'];
        }

        $H264Profiles = array_filter($Profiles, function ($Profile)
        {
            if (isset($Profile['VideoEncoderConfiguration']['Encoding'])) {
                if (strtoupper($Profile['VideoEncoderConfiguration']['Encoding']) == 'JPEG') {
                    return false;
                }
            }
            return true;
        });
        $H264VideoSourcesItems = [];
        foreach ($H264Profiles as $Profile) {
            if (!array_key_exists('VideoEncoderConfiguration', $Profile)) {
                continue;
            }
            $H264VideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['VideoSourceToken'] = $Profile['VideoSourceConfiguration']['SourceToken'];
            if ($Profile['VideoSourceConfiguration']['Name'] == '') {
                $H264VideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['VideoSourceName'] = $Profile['VideoSourceConfiguration']['SourceToken'];
            } else {
                $H264VideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['VideoSourceName'] = $Profile['VideoSourceConfiguration']['Name'];
            }
            $H264VideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['Profile'][] = [
                'Name'       => $Profile['VideoEncoderConfiguration']['Name'],
                'token'      => $Profile['token'],
                'ptztoken'   => isset($Profile['PTZConfiguration']['token']) ? $Profile['PTZConfiguration']['token'] : '',
                'Encoding'   => isset($Profile['VideoEncoderConfiguration']['Encoding']) ? $Profile['VideoEncoderConfiguration']['Encoding'] : 'unknown',
                'Resolution' => isset($Profile['VideoEncoderConfiguration']['Resolution']) ? $Profile['VideoEncoderConfiguration']['Resolution'] : 'unknown',
                'RateControl'=> isset($Profile['VideoEncoderConfiguration']['RateControl']) ? $Profile['VideoEncoderConfiguration']['RateControl'] : 'unknown'
            ];
        }
        $H264VideoSources = array_values($H264VideoSourcesItems);
        $this->SendDebug('VideoSources H.26x', $H264VideoSources, 0);
        $this->WriteAttributeArray('VideoSources', $H264VideoSources);

        $JPEGProfiles = array_filter($Profiles, function ($Profile)
        {
            if (isset($Profile['VideoEncoderConfiguration']['Encoding'])) {
                if (strtoupper($Profile['VideoEncoderConfiguration']['Encoding']) == 'JPEG') {
                    return true;
                }
            }
            return false;
        });
        if (count($JPEGProfiles) == 0) { //fallback, no JPEG found
            $JPEGProfiles = $Profiles;
        }
        $JPEGVideoSourcesItems = [];
        foreach ($JPEGProfiles as $Profile) {
            if (!array_key_exists('VideoEncoderConfiguration', $Profile)) {
                continue;
            }
            $JPEGVideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['VideoSourceToken'] = $Profile['VideoSourceConfiguration']['SourceToken'];
            $JPEGVideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['VideoSourceName'] = $Profile['VideoSourceConfiguration']['Name'];
            $JPEGVideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['Profile'][] = [
                'Name'       => $Profile['VideoEncoderConfiguration']['Name'],
                'token'      => $Profile['token'],
                'Encoding'   => isset($Profile['VideoEncoderConfiguration']['Encoding']) ? $Profile['VideoEncoderConfiguration']['Encoding'] : 'unknown',
                'Resolution' => isset($Profile['VideoEncoderConfiguration']['Resolution']) ? $Profile['VideoEncoderConfiguration']['Resolution'] : 'unknown',
                'RateControl'=> isset($Profile['VideoEncoderConfiguration']['RateControl']) ? $Profile['VideoEncoderConfiguration']['RateControl'] : 'unknown'
            ];
        }
        $JPEGVideoSources = array_values($JPEGVideoSourcesItems);
        $this->SendDebug('VideoSources JPEG', $JPEGVideoSources, 0);
        $this->WriteAttributeArray('VideoSourcesJPEG', $JPEGVideoSources);
        $AnalyticsTokens = [];
        $AnalyticsProfiles = array_filter($Profiles, function ($Profile)
        {
            if (isset($Profile['VideoAnalyticsConfiguration'])) {
                return true;
            }
        });
        foreach ($AnalyticsProfiles as $AnalyticsProfile) {
            $Token = $AnalyticsProfile['VideoAnalyticsConfiguration']['token'];
            $Name = $AnalyticsProfile['VideoAnalyticsConfiguration']['Name'];
            $AnalyticsTokens[$Token] = $Name;
        }
        $this->SendDebug('AnalyticsTokens', $AnalyticsTokens, 0);
        $this->WriteAttributeArray('AnalyticsTokens', $AnalyticsTokens);
        return true;
    }
    protected function GetCapabilities()
    {
        $XAddr = [
            \ONVIF\NS::Management => '/onvif/device_service',
            \ONVIF\NS::Event      => '',
            \ONVIF\NS::Media      => '/onvif/media_service',
            \ONVIF\NS::PTZ        => '',
            \ONVIF\NS::Imaging    => '',
            \ONVIF\NS::Analytics  => '',
            \ONVIF\NS::DeviceIO   => '',
            \ONVIF\NS::Media2     => '',
            //            'Recording' => '',
            //            'Replay'    => ''
        ];
        $Result = $this->SendData('', \ONVIF\WSDL::Management, 'GetCapabilities', true);
        if (!is_a($Result, 'SoapFault')) {
            $CapabilitiesResult = json_decode(json_encode($Result), true);
            if (isset($CapabilitiesResult['Capabilities']['Analytics']['XAddr'])) {
                $XAddr[\ONVIF\NS::Analytics] = parse_url($CapabilitiesResult['Capabilities']['Analytics']['XAddr'], PHP_URL_PATH);
                $this->WriteAttributeBoolean('AnalyticsModuleSupport', $CapabilitiesResult['Capabilities']['Analytics']['AnalyticsModuleSupport']);
                $this->WriteAttributeBoolean('RuleSupport', $CapabilitiesResult['Capabilities']['Analytics']['RuleSupport']);
            }
            if (isset($CapabilitiesResult['Capabilities']['Events']['XAddr'])) {
                $XAddr[\ONVIF\NS::Event] = parse_url($CapabilitiesResult['Capabilities']['Events']['XAddr'], PHP_URL_PATH);
            }
            if (isset($CapabilitiesResult['Capabilities']['Media']['XAddr'])) {
                $MediaUrl = parse_url($CapabilitiesResult['Capabilities']['Media']['XAddr'], PHP_URL_PATH);
                if (strpos($MediaUrl, '2') === false) {
                    $XAddr[\ONVIF\NS::Media] = $MediaUrl;
                } else {
                    $XAddr[\ONVIF\NS::Media2] = $MediaUrl;
                }
                $HasRTSPStreaming = false;
                if (isset($CapabilitiesResult['Capabilities']['Media']['StreamingCapabilities']['RTP_TCP'])) {
                    $HasRTSPStreaming = $CapabilitiesResult['Capabilities']['Media']['StreamingCapabilities']['RTP_TCP'];
                }
                if (isset($CapabilitiesResult['Capabilities']['Media']['StreamingCapabilities']['RTP_RTSP_TCP'])) {
                    $HasRTSPStreaming = $HasRTSPStreaming || $CapabilitiesResult['Capabilities']['Media']['StreamingCapabilities']['RTP_RTSP_TCP'];
                }
                $this->WriteAttributeBoolean('HasRTSPStreaming', $HasRTSPStreaming);
            }
            if (isset($CapabilitiesResult['Capabilities']['PTZ']['XAddr'])) {
                $XAddr[\ONVIF\NS::PTZ] = parse_url($CapabilitiesResult['Capabilities']['PTZ']['XAddr'], PHP_URL_PATH);
            }
            if (isset($CapabilitiesResult['Capabilities']['Imaging']['XAddr'])) {
                $XAddr[\ONVIF\NS::Imaging] = parse_url($CapabilitiesResult['Capabilities']['Imaging']['XAddr'], PHP_URL_PATH);
            }
            if (isset($CapabilitiesResult['Capabilities']['Extension']['DeviceIO']['XAddr'])) {
                $XAddr[\ONVIF\NS::DeviceIO] = parse_url($CapabilitiesResult['Capabilities']['Extension']['DeviceIO']['XAddr'], PHP_URL_PATH);
            }
            if (isset($CapabilitiesResult['Capabilities']['Extension']['DeviceIO']['VideoSources'])) {
                $NbrOfVideoSources = $CapabilitiesResult['Capabilities']['Extension']['DeviceIO']['VideoSources'];
                $this->WriteAttributeInteger('NbrOfVideoSources', $NbrOfVideoSources);
            }
            if (isset($CapabilitiesResult['Capabilities']['Extension']['DeviceIO']['AudioSources'])) {
                $NbrOfAudioSources = $CapabilitiesResult['Capabilities']['Extension']['DeviceIO']['AudioSources'];
                $this->WriteAttributeInteger('NbrOfAudioSources', $NbrOfAudioSources);
            }
            if (isset($CapabilitiesResult['Capabilities']['Device']['IO']['InputConnectors'])) {
                $NbrOfInputs = $CapabilitiesResult['Capabilities']['Device']['IO']['InputConnectors'];
                $this->WriteAttributeInteger('NbrOfInputs', $NbrOfInputs);
            }
            if (isset($CapabilitiesResult['Capabilities']['Device']['IO']['RelayOutputs'])) {
                $NbrOfOutputs = $CapabilitiesResult['Capabilities']['Device']['IO']['RelayOutputs'];
                $this->WriteAttributeInteger('NbrOfOutputs', $NbrOfOutputs);
            }

            /*            if (isset($CapabilitiesResult['Capabilities']['Extension']['Recording']['XAddr'])) {
                            $XAddr['Recording'] = parse_url($CapabilitiesResult['Capabilities']['Extension']['Recording']['XAddr'], PHP_URL_PATH);
                        }
                        if (isset($CapabilitiesResult['Capabilities']['Extension']['Replay']['XAddr'])) {
                            $XAddr['Replay'] = parse_url($CapabilitiesResult['Capabilities']['Extension']['Replay']['XAddr'], PHP_URL_PATH);
                        }*/
            if ($XAddr[\ONVIF\NS::Media2] == '') {
                if ($this->Profile->HasProfile(\ONVIF\Profile::T)) {
                    //media2 patchen bei Profile T
                    $XAddr[\ONVIF\NS::Media2] = '/onvif/media2_service';
                }
            }
        }
        $this->WriteAttributeArray('XAddr', $XAddr);
        return $XAddr;
    }
    protected function GetScopes()
    {
        $Scopes = $this->SendData('', \ONVIF\WSDL::Management, 'GetScopes', true);
        if (is_a($Scopes, 'SoapFault')) {
            return false;
        }
        $Scopes = json_decode(json_encode($Scopes), true);
        if (!array_key_exists('Scopes', $Scopes)) {
            return false;
        }
        $Result = [];
        foreach ($Scopes['Scopes'] as $Scope) {
            $Result[] = $Scope['ScopeItem'];
        }
        return $Result;
    }
    protected function GetNodes()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        $Nodes = $this->SendData($XAddr[\ONVIF\NS::PTZ], \ONVIF\WSDL::PTZ, 'GetNodes', true);
        if (is_a($Nodes, 'SoapFault')) {
            return false;
        }
        $Result = json_decode(json_encode($Nodes), true);
        return $Result;
    }
    protected function GetVideoSources($Uri, $WSDL)
    {
        $VideoSources = $this->SendData($Uri, $WSDL, 'GetVideoSources', true);
        if (is_a($VideoSources, 'SoapFault')) {
            return false;
        }
        $Result = json_decode(json_encode($VideoSources), true);
        return $Result;
    }
    protected function GetAudioSources($Uri, $WSDL)
    {
        $AudioSources = $this->SendData($Uri, $WSDL, 'GetAudioSources', true);
        if (is_a($AudioSources, 'SoapFault')) {
            return false;
        }
        $Result = json_decode(json_encode($AudioSources), true);
        return $Result;
    }

    protected function GetSupportedAnalyticsModules(string $AnalyticsToken)
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        $Params = [
            'ConfigurationToken' => $AnalyticsToken
        ];
        $Response = '';
        $GetSupportedAnalyticsModulesResult = $this->SendData($XAddr[\ONVIF\NS::Analytics], \ONVIF\WSDL::Analytics, 'GetSupportedAnalyticsModules', true, $Params, $Response);
        if (is_a($GetSupportedAnalyticsModulesResult, 'SoapFault')) {
            return false;
        }
        return $this->DecodeAnalyticsAndRuleResponse($Response);
    }
    protected function GetSupportedRules(string $AnalyticsToken)
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        $Params = [
            'ConfigurationToken' => $AnalyticsToken
        ];
        $Response = '';
        $GetSupportedRulesResult = $this->SendData($XAddr[\ONVIF\NS::Analytics], \ONVIF\WSDL::Analytics, 'GetSupportedRules', true, $Params, $Response);
        if (is_a($GetSupportedRulesResult, 'SoapFault')) {
            return false;
        }
        return $this->DecodeAnalyticsAndRuleResponse($Response);
    }
    protected function DecodeAnalyticsAndRuleResponse(string $ResponseXML)
    {
        $xml = new DOMDocument();
        $xml->loadXML($ResponseXML);
        $xpath = new DOMXPath($xml);
        foreach (\ONVIF\NS::$Namespaces as $NSKey => $Namespace) {
            $EventNS[$NSKey] = $xml->lookupPrefix($Namespace);
            $xpath->registerNamespace($NSKey, $Namespace);
        }
        $query = '//tt:Messages';
        $wsTopics = $xpath->query($query);
        $TopicData = [];
        foreach ($wsTopics as $wsData) {
            $Topic = $xpath->query('tt:ParentTopic', $wsData, true)[0]->nodeValue;
            $SourcesNodeList = $xpath->query('tt:Source/tt:SimpleItemDescription', $wsData, true);
            $Sources = $this->GetEventMessageDescriptionValues($SourcesNodeList, $EventNS);
            $DataNodeList = $xpath->query('tt:Data/tt:SimpleItemDescription', $wsData, true);
            $Data = $this->GetEventMessageDescriptionValues($DataNodeList, $EventNS);
            $TopicData[$Topic]['Sources'] = $Sources;
            $TopicData[$Topic]['Data'] = $Data;
            $TopicData[$Topic]['Receivers'] = [];
        }
        return $TopicData;
    }
    protected function GetServices()
    {
        $Params = [
            'IncludeCapability'=> true
        ];
        $Services = $this->SendData('', \ONVIF\WSDL::Management, 'GetServices', true, $Params);
        if (is_a($Services, 'SoapFault')) {
            return false;
        }
        $ServicesResult = json_decode(json_encode($Services), true);
        $XAddr = [
            \ONVIF\NS::Management => '/onvif/device_service',
            \ONVIF\NS::Event      => '',
            \ONVIF\NS::Media      => '/onvif/media_service',
            \ONVIF\NS::PTZ        => '',
            \ONVIF\NS::Imaging    => '',
            \ONVIF\NS::Analytics  => '',
            \ONVIF\NS::DeviceIO   => '',
            \ONVIF\NS::Media2     => '',
            //                'Recording' => '',
            //                'Replay'    => ''
        ];
        foreach ($ServicesResult['Service'] as $Service) {
            $XAddr[$Service['Namespace']] = parse_url($Service['XAddr'], PHP_URL_PATH);
        }
        $this->WriteAttributeArray('XAddr', $XAddr);
        return $XAddr;
    }
    protected function GetServiceCapabilities($Uri, $WSDL)
    {
        $ServiceCapabilities = $this->SendData($Uri, $WSDL, 'GetServiceCapabilities', true);
        if (is_a($ServiceCapabilities, 'SoapFault')) {
            return false;
        }
        $Result = json_decode(json_encode($ServiceCapabilities), true);
        return $Result;
    }
    protected function GetDeviceInformation()
    {
        $DeviceInformation = $this->SendData('', \ONVIF\WSDL::Management, 'GetDeviceInformation', true);
        if (is_a($DeviceInformation, 'SoapFault')) {
            return false;
        }
        $Result = json_decode(json_encode($DeviceInformation), true);
        return $Result;
    }
    protected function GetDigitalInputs()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        $DigitalInputResponse = $this->SendData($XAddr[\ONVIF\NS::DeviceIO], \ONVIF\WSDL::DeviceIO, 'GetDigitalInputs', true);
        if (is_a($DigitalInputResponse, 'SoapFault')) {
            $DigitalInputResponse = $this->SendData($XAddr[\ONVIF\NS::Management], \ONVIF\WSDL::DeviceIO, 'GetDigitalInputs', true);
            if (is_a($DigitalInputResponse, 'SoapFault')) {
                return false;
            }
        }
        if (property_exists($DigitalInputResponse, 'DigitalInputs')) {
            $DigitalInputs = [];
            if (is_array($DigitalInputResponse->DigitalInputs)) {
                foreach ($DigitalInputResponse->DigitalInputs as $DigitalInput) {
                    $DigitalInputProperties = json_decode(json_encode($DigitalInput), true);
                    unset($DigitalInputProperties['token']);
                    if (!count($DigitalInputProperties)) {
                        $DigitalInputProperties = $this->GetDigitalInputConfigurationOptions($DigitalInput->token);
                    }
                    $DigitalInputs[$DigitalInput->token] = $DigitalInputProperties;
                }
            } else {
                $DigitalInputProperties = json_decode(json_encode($DigitalInputResponse->DigitalInputs), true);
                unset($DigitalInputProperties['token']);
                if (!count($DigitalInputProperties)) {
                    $DigitalInputProperties = $this->GetDigitalInputConfigurationOptions($DigitalInput->token);
                }
                $DigitalInputs[$DigitalInputResponse->DigitalInputs->token] = $DigitalInputProperties;
            }
            $this->WriteAttributeInteger('NbrOfInputs', count($DigitalInputs));
            $this->WriteAttributeArray('DigitalInputs', $DigitalInputs);
        }
        return true;
    }
    protected function GetDigitalInputConfigurationOptions(string $Token)
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        $Params['Token'] = $Token;
        $DigitalInputConfigurationOptionsResponse = $this->SendData($XAddr[\ONVIF\NS::DeviceIO], \ONVIF\WSDL::DeviceIO, 'GetDigitalInputConfigurationOptions', true, $Params);
        if (is_a($DigitalInputConfigurationOptionsResponse, 'SoapFault')) {
            $DigitalInputConfigurationOptionsResponse = $this->SendData($XAddr[\ONVIF\NS::Management], \ONVIF\WSDL::DeviceIO, 'GetDigitalInputConfigurationOptions', true, $Params);
            if (is_a($DigitalInputConfigurationOptionsResponse, 'SoapFault')) {
                return [];
            }
        }
        if (property_exists($DigitalInputConfigurationOptionsResponse, 'DigitalInputOptions')) {
            return json_decode(json_encode($DigitalInputConfigurationOptionsResponse->DigitalInputOptions), true);
        }
        return [];
    }
    /*
    protected function GetSerialPorts()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        $SerialPorts = $this->SendData($XAddr[\ONVIF\NS::DeviceIO], \ONVIF\WSDL::DeviceIO, 'GetSerialPorts', true);
        if (is_a($SerialPorts, 'SoapFault')) {
            return false;
        }
        $Result = json_decode(json_encode($SerialPorts), true);
        return $Result;
    }*/

    protected function GetRelayOutputs($Uri, $WSDL)
    {
        $RelayOutputResponse = $this->SendData($Uri, $WSDL, 'GetRelayOutputs', true);
        if (is_a($RelayOutputResponse, 'SoapFault')) {
            return false;
        }
        $RelayOutputs = [];
        if (property_exists($RelayOutputResponse, 'RelayOutputs')) {
            if (is_array($RelayOutputResponse->RelayOutputs)) {
                foreach ($RelayOutputResponse->RelayOutputs as $RelayOutput) {
                    $RelayOutputs[$RelayOutput->token] = json_decode(json_encode($RelayOutput), true)['Properties'];
                }
            } else {
                $RelayOutputs[$RelayOutputResponse->RelayOutputs->token] = json_decode(json_encode($RelayOutputResponse->RelayOutputs), true)['Properties'];
            }
        }
        $this->WriteAttributeInteger('NbrOfOutputs', count($RelayOutputs));
        $this->WriteAttributeArray('RelayOutputs', $RelayOutputs);
        return true;
    }
    protected function GetSystemDateAndTime()
    {
        $camera_datetime = $this->SendData('', \ONVIF\WSDL::Management, 'GetSystemDateAndTime');
        if (is_a($camera_datetime, 'SoapFault')) {
            $this->WriteAttributeInteger('Timestamp_Offset', 0);
            return false;
        }
        if (property_exists($camera_datetime->SystemDateAndTime, 'UTCDateTime')) {
            $camera_ts = gmmktime(
                $camera_datetime->SystemDateAndTime->UTCDateTime->Time->Hour,
                $camera_datetime->SystemDateAndTime->UTCDateTime->Time->Minute,
                $camera_datetime->SystemDateAndTime->UTCDateTime->Time->Second,
                $camera_datetime->SystemDateAndTime->UTCDateTime->Date->Month,
                $camera_datetime->SystemDateAndTime->UTCDateTime->Date->Day,
                $camera_datetime->SystemDateAndTime->UTCDateTime->Date->Year
            );
            $this->WriteAttributeInteger('Timestamp_Offset', time() - $camera_ts);
            $this->SendDebug('TimeDiff', time() - $camera_ts, 0);
            return true;
        }
        if (property_exists($camera_datetime->SystemDateAndTime, 'LocalDateTime')) {
            $camera_ts = mktime(
                $camera_datetime->SystemDateAndTime->LocalDateTime->Time->Hour,
                $camera_datetime->SystemDateAndTime->LocalDateTime->Time->Minute,
                $camera_datetime->SystemDateAndTime->LocalDateTime->Time->Second,
                $camera_datetime->SystemDateAndTime->LocalDateTime->Date->Month,
                $camera_datetime->SystemDateAndTime->LocalDateTime->Date->Day,
                $camera_datetime->SystemDateAndTime->LocalDateTime->Date->Year
            );
            $this->WriteAttributeInteger('Timestamp_Offset', time() - $camera_ts);
            $this->SendDebug('TimeDiff', time() - $camera_ts, 0);
            return true;
        }
        return false;
    }

    /**
     *
     * @param string $URI
     * @param string $wsdl
     * @param string $Function
     * @param bool $UseLogin
     * @param array $Params
     */
    protected function SendData(string $URI, string $wsdl, string $Function, bool $UseLogin = false, array $Params = [], string &$Response = '', array $Header = [])
    {
        if ($URI == '') {
            $URI = $this->Host . '/onvif/device_service';
        } else {
            if (strpos($URI, 'http') === false) {
                $URI = $this->Host . $URI;
            }
        }
        $this->SendDebug('Send URI', $URI, 0);
        $this->SendDebug('Send wsdl', $wsdl, 0);
        $this->SendDebug('Send Function', $Function, 0);
        $this->SendDebug('Send Params', $Params, 0);
        $wsdl = dirname(__DIR__) . '/libs/WSDL/' . $wsdl;
        if ($UseLogin) {
            if ($this->Profile->HasProfile(\ONVIF\Profile::S) || ($Function == 'GetScopes')) { // Nur Profile S Geräte können WSSecurity
                if ($this->ReadPropertyString('Password') != '') {
                    $Header[] = \ONVIF\ONVIF::soapClientWSSecurityHeader($this->ReadPropertyString('Username'), $this->ReadPropertyString('Password'), $this->ReadAttributeInteger('Timestamp_Offset'));
                }
            }
            $ONVIFClient = new \ONVIF\ONVIF($wsdl, $URI, $this->ReadPropertyString('Username'), $this->ReadPropertyString('Password'), $Header);
        } else {
            $ONVIFClient = new \ONVIF\ONVIF($wsdl, $URI, null, null, $Header);
        }
        try {
            if (count($Params) == 0) {
                $Result = $ONVIFClient->client->{$Function}();
            } else {
                $Result = $ONVIFClient->client->{$Function}($Params);
            }
            $Response = $ONVIFClient->client->__getLastResponse();
            $this->SendDebug('Soap Request Headers', $ONVIFClient->client->__getLastRequestHeaders(), 0);
            $this->SendDebug('Soap Request', $ONVIFClient->client->__getLastRequest(), 0);
            $this->SendDebug('Soap Response Headers', $ONVIFClient->client->__getLastResponseHeaders(), 0);
            $this->SendDebug('Soap Response', $Response, 0);
            $this->SendDebug('Soap Result', $Result, 0);
            $this->lastSOAPError = '';
        } catch (SoapFault $e) {
            $Response = $ONVIFClient->client->__getLastResponse();
            $this->SendDebug('Soap Request Headers Error', $ONVIFClient->client->__getLastRequestHeaders(), 0);
            $this->SendDebug('Soap Request Error', $ONVIFClient->client->__getLastRequest(), 0);
            $this->SendDebug('Soap Response Headers Error', $ONVIFClient->client->__getLastResponseHeaders(), 0);
            $this->SendDebug('Soap Response Error', $Response, 0);
            $this->SendDebug('Soap Response Error (' . $e->getCode() . ')', $e->getMessage(), 0);
            $this->lastSOAPError = $e->getMessage();
            unset($ONVIFClient);
            return $e;
        }
        unset($ONVIFClient);
        return $Result;
    }
    protected function GetEventReceiverFormValues()
    {
        $EventList = [];
        $Events = $this->ReadAttributeArray('EventProperties');
        foreach ($Events as $Topic => $MessageDescription) {
            $Receivers = [];
            foreach ($MessageDescription['Receivers'] as $Receiver) {
                if (IPS_InstanceExists($Receiver)) {
                    $Receivers[] = [
                        'instanceID' => $Receiver,
                        'Name'       => IPS_GetName($Receiver),
                        'Type'       => substr(IPS_GetInstance($Receiver)['ModuleInfo']['ModuleName'], 6)
                    ];
                }
            }
            $EventList[] = [
                'Topic'      => substr(stristr($Topic, ':'), 1),
                'Sources'    => $MessageDescription['Sources'],
                'Data'       => $MessageDescription['Data'],
                'rowColor'   => (count($Receivers) > 0 ? '#FFFFFF' : ''),
                'Used'       => $this->Translate((count($Receivers) > 0) ? 'Yes' : 'No'),
                'Receivers'  => $Receivers
            ];
        }
        return $EventList;
    }
    protected function ProcessHookData()
    {
        if ($this->ReadPropertyBoolean('Open') == false) {
            http_response_code(404);
            header('Connection: close');
            header('Server: Symcon ' . IPS_GetKernelVersion());
            header('X-Powered-By: ONVIF Module');
            header('Expires: 0');
            header('Cache-Control: no-cache');
            header('Content-Type: text/plain');
            header('Content-Type: text/plain');
            echo 'File not found!';
            return;
        }
        http_response_code(200);
        header('Connection: close');
        header('Server: Symcon ' . IPS_GetKernelVersion());
        header('X-Powered-By: ONVIF Module');
        header('Expires: 0');
        header('Cache-Control: no-cache');
        header('Content-Type: text/plain');

        $Data = file_get_contents('php://input');
        $this->SendDebug('Event', $Data, 0);
        if ($this->WaitForFirstEvent) {
            $this->WaitForFirstEvent = false;
        }
        return $this->DecodeNotificationMessage($Data);
    }
    protected function DecodeNotificationMessage(string $NotificationMessageXML)
    {
        $xml = new DOMDocument();
        $xml->loadXML($NotificationMessageXML);
        if ($xml === false) {
            $this->LogMessage($this->Translate('Malformed XML event received'), KL_ERROR);
            $this->SendDebug('Event', $this->Translate('Malformed XML event received'), 0);
            return false;
        }
        $xpath = new DOMXPath($xml);
        foreach (\ONVIF\NS::$Namespaces as $NSKey => $Namespace) {
            $xpath->registerNamespace($NSKey, $Namespace);
        }
        $query = '//wsnt:NotificationMessage';
        $NotificationMessages = $xpath->query($query);
        $EventData = [];
        foreach ($NotificationMessages as $NotificationMessage) {
            $TopicNodes = $xpath->query('wsnt:Topic', $NotificationMessage, true);
            if (!count($TopicNodes)) {
                continue;
            }
            $NotificationTopic = $TopicNodes[0]->nodeValue;
            $SourcesNodeList = $xpath->query('wsnt:Message/tt:Message/tt:Source/tt:SimpleItem', $NotificationMessage, true);
            $Sources = $this->GetEventMessageValues($SourcesNodeList);
            $DataNodeList = $xpath->query('wsnt:Message/tt:Message/tt:Data/tt:SimpleItem', $NotificationMessage, true);
            $Values = $this->GetEventMessageValues($DataNodeList);
            $EventData[] = ['Topic'        => $NotificationTopic,
                'Sources'                  => $Sources,
                'DataValues'               => $Values
            ];
        }
        $this->SendDebug('Event', $EventData, 0);
        $this->SendEventDataArrayToChildren($EventData);
        return true;
    }
    protected function SendEventDataArrayToChildren(array $EventDataArray): void
    {
        foreach ($EventDataArray as $EventData) {
            $EventData['DataID'] = '{E23DD2CD-F098-268A-CE49-1CC04FE8060B}';
            $this->SendDataToChildren(json_encode($EventData));
        }
    }
    protected function KernelReady()
    {
        $this->RegisterMessage($this->InstanceID, FM_CHILDREMOVED);
        $Url = parse_url($this->ReadPropertyString('Address'));
        $Url['port'] = (isset($Url['port']) ? ':' . $Url['port'] : '');
        if (isset($Url['scheme']) && isset($Url['host'])) {
            $this->Host = $Url['scheme'] . '://' . $Url['host'] . $Url['port'];
        }
        $this->ApplyChanges();
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
    private function GetEventMessageValues(DOMNodeList $xmlNodes)
    {
        $Results = [];
        foreach ($xmlNodes as $Node) {
            $Results[] = [
                'Name'  => $Node->attributes->getNamedItem('Name')->nodeValue,
                'Value' => $Node->attributes->getNamedItem('Value')->nodeValue
            ];
        }
        return $Results;
    }
    private function GetEventMessageDescriptionValues(DOMNodeList $xmlNodes, array $ValueNS)
    {
        $Results = [];
        foreach ($xmlNodes as $Node) {
            $Results[] = [
                'Name' => $Node->attributes->getNamedItem('Name')->nodeValue,
                'Type' => $this->ConvertToMyNamespace($Node->attributes->getNamedItem('Type')->nodeValue, $ValueNS)
            ];
        }
        return $Results;
    }
    private function ConvertToMyNamespace(string $Value, array $ValueNS)
    {
        $Parts = explode(':', $Value);
        $MyNS = array_search($Parts[0], $ValueNS);
        if ($MyNS) {
            return $MyNS . ':' . $Parts[1];
        }
        return $Value;
    }
}
