<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/AttributeArrayHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/onvif-client-php/inc/ONVIF.inc.php';

/**
 * @property string $Host
 * @property bool isSubscribed
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
        $this->RegisterPropertyString('NATAddress', '');
        $this->RegisterAttributeArray('VideoSources', []);
        $this->RegisterAttributeInteger('Timestamp_Offset', 0);
        /*        $this->RegisterAttributeString('XAddrMedia', '');
          $this->RegisterAttributeString('XAddrImageing', '');
          $this->RegisterAttributeString('XAddrEvents', '');
          $this->RegisterAttributeString('XAddrPTZ', '');
          $this->RegisterAttributeString('XAddrRecording', '');
          $this->RegisterAttributeString('XAddrReplay', ''); */
        $this->RegisterAttributeArray('XAddr', []);

        $this->RegisterAttributeArray('EventProperties', []);
        $this->RegisterAttributeString('ConsumerAddress', '');
        $this->RegisterAttributeString('SubscriptionReference', '');
        $this->RegisterAttributeString('SubscriptionId', '');
        $this->RegisterAttributeBoolean('HasInput', false);
        $this->RegisterAttributeBoolean('HasOutput', false);
        $this->RegisterTimer('RenewSubscription', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"Renew",true);');
        $this->Host = '';
        $this->isSubscribed = false;
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterMessage($this->InstanceID, FM_CHILDREMOVED);
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
        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->SetTimerInterval('RenewSubscription', 0);

        if ($this->isSubscribed) {
            $this->Unsubscribe();
        }

        if (!$this->ReadPropertyBoolean('Open')) {
            $this->Host = '';
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
        $Host = $Url['scheme'] . '://' . $Url['host'] . $Url['port'];
        $ReloadCapabilities = ($this->Host != $Host);
        $ReloadCapabilities = $ReloadCapabilities || ($this->GetStatus() == 202);
        $ReloadCapabilities = $ReloadCapabilities || ($this->ReadAttributeString('ConsumerAddress') == '');
        $this->SendDebug('ReloadCapabilities', $ReloadCapabilities, 0);
        $this->SetSummary($Host);
        $this->Host = $Host;
        $this->GetSystemDateAndTime(); // können nicht alle, also nicht weiter beachten. Wird aber für login benötigt, damit Zeitdifferenzen berücksichtigt werden.
        if (!$this->GetDeviceInformation()) { // not reachable
            $this->UpdateFormField('EventHook', 'caption', $this->ReadAttributeString('ConsumerAddress'));
            $this->WriteAttributeString('SubscriptionReference', '');
            $this->WriteAttributeString('SubscriptionId', '');
            $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
            $this->UpdateFormField('SubscriptionReference', 'caption', '');
            $EventList = $this->GetEventReceiverFormValues();
            $this->UpdateFormField('Events', 'values', json_encode($EventList));
            $this->UpdateFormField('Events', 'visible', true);
            $this->LogMessage($this->lastSOAPError, KL_ERROR);
            $this->ShowLastError($this->lastSOAPError);
            $this->SetStatus(IS_EBASE + 2);
            return;
        }

        if ($ReloadCapabilities) {
            if (!$this->GetProfiles()) {
                $this->SetStatus(IS_EBASE + 2);
                return;
            } else {
                if ($this->GetCapabilities()) {
                    // Prüfen ob XAddrEvents gesetzt ist
                    if ($this->GetEventProperties()) { // events are valid
                        $this->RegisterHook('/hook/ONVIFEvents/IO/' . $this->InstanceID);
                        if ($this->GetConsumerAddress()) { // yeah, we can receive events
                            $this->Subscribe();
                        } else { // we cannot receive events :(
                            $this->WriteAttributeString('SubscriptionReference', '');
                            $this->WriteAttributeString('SubscriptionId', '');
                            $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
                            $this->UpdateFormField('SubscriptionReference', 'caption', '');
                        }
                    } else { // events not possible
                        $this->EventsNotSupported();
                    }
                } else {
                    //Attribute löschen
                    $XAddr = [
                        'Events'    => '',
                        'Media'     => $this->ReadPropertyString('Address'),
                        'PTZ'       => $this->ReadPropertyString('Address'),
                        'Imaging'   => '',
                        'Recording' => '',
                        'Replay'    => ''
                    ];
                    $this->WriteAttributeArray('XAddr', $XAddr);
                    $this->EventsNotSupported();
                }
            }
        } else {
            $this->SendDebug('VideoSourcesAttribute', $this->ReadAttributeArray('VideoSources'), 0);
            $this->Subscribe();
        }
        $this->LogMessage($this->Translate('Interface connected'), KL_MESSAGE);
        $this->SetStatus(IS_ACTIVE);
    }

    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        if ($Data['Function'] == 'GetCapabilities') {
            $Capabilities['VideoSources'] = $this->ReadAttributeArray('VideoSources');
            $Capabilities['HasOutput'] = $this->ReadAttributeBoolean('HasOutput');
            $Capabilities['HasInput'] = $this->ReadAttributeBoolean('HasInput');
            $Capabilities['XAddr'] = $this->ReadAttributeArray('XAddr');
            return serialize($Capabilities);
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
                    //$FoundEvents = $Events;
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
        $Ret = $this->SendData($Data['URI'], $Data['wsdl'], $Data['Function'], $Data['useLogin'], $Data['Params']);
        return serialize($Ret);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == 'Renew') {
            return $this->Renew();
        }
        if ($Ident == 'KernelReady') {
            return $this->KernelReady();
        }
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (IPS_GetOption('NATSupport')) {
            $Form['elements'][3]['visible'] = true;
            $Form['elements'][3]['validate'] = '^.+$';
        }
        $ConsumerAddress = $this->ReadAttributeString('ConsumerAddress');
        if ($ConsumerAddress == '') {
            $ConsumerAddress = $this->Translate('This device not support events.');
            $Form['actions'][1]['visible'] = false;
            $Form['actions'][2]['visible'] = false;
        }
        $Form['actions'][0]['items'][1]['caption'] = $ConsumerAddress;
        $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
        $Form['actions'][1]['items'][1]['caption'] = $SubscriptionReference;
        $EventList = $this->GetEventReceiverFormValues();
        $Form['actions'][2]['values'] = $EventList;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    protected function EventsNotSupported()
    {
        $this->LogMessage('Events not supported: ' . $this->lastSOAPError, KL_MESSAGE);
        $this->UpdateFormField('EventHook', 'caption', $this->Translate('This device not support events.'));
        $this->UpdateFormField('SubscriptionReferenceRow', 'visible', false);
        $this->UpdateFormField('Events', 'values', json_encode([]));
        $this->UpdateFormField('Events', 'visible', false);
        $this->UnregisterHook('/hook/ONVIFEvents/IO/' . $this->InstanceID);
        $this->WriteAttributeArray('EventProperties', []);
        $this->WriteAttributeString('ConsumerAddress', '');
        $this->ShowLastError($this->Translate('This device does not support ONVIF events.'), 'Info:');
    }

    protected function ShowLastError(string $ErrorMessage, string $ErrorTitle = 'Answer from Device:')
    {
        IPS_Sleep(500);
        $this->UpdateFormField('ErrorTitle', 'caption', $ErrorTitle);
        $this->UpdateFormField('ErrorText', 'caption', $ErrorMessage);
        $this->UpdateFormField('ErrorPopup', 'visible', true);
    }

    protected function GetConsumerAddress()
    {
        if (IPS_GetOption('NATSupport')) {
            $parsed_url = parse_url($this->ReadPropertyString('NATAddress'));
            $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : 'http://';
            $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
            $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ':3777';
            $Url = $scheme . $host . $port . '/hook/ONVIFEvents/IO/' . $this->InstanceID;
            $this->SendDebug('NAT enabled ConsumerAddress', $Url, 0);
        } else {
            $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_bind($sock, '0.0.0.0', 0);
            $Host = parse_url($this->Host);
            $Host['port'] = isset($Host['port']) ? $Host['port'] : 80;
            @socket_connect($sock, $Host['host'], $Host['port']);
            $ip = '';
            socket_getsockname($sock, $ip);
            @socket_close($sock);
            if ($ip == '0.0.0.0') {
                $this->SendDebug('ConsumerAddress', 'Invalid', 0);
                $this->UpdateFormField('EventHook', 'caption', $this->Translate('Invalid'));
                $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                return false;
            }
            $Url = 'http://' . $ip . ':3777/hook/ONVIFEvents/IO/' . $this->InstanceID;
            $this->SendDebug('ConsumerAddress', $Url, 0);
        }
        $this->UpdateFormField('EventHook', 'caption', $Url);
        $this->WriteAttributeString('ConsumerAddress', $Url);
        return true;
    }

    protected function Subscribe()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        if ($XAddr['Events'] == '') {
            return false;
        }
        $Params = [
            'ConsumerReference'      => [
                'Address' => $this->ReadAttributeString('ConsumerAddress')
            ],
            'InitialTerminationTime' => 'PT1M'
        ];
        $Response = '';
        $ret = $this->SendData($XAddr['Events'], 'event-mod.wsdl', 'Subscribe', true, $Params, $Response);
        if (is_a($ret, 'SoapFault')) {
            $this->SetStatus(IS_EBASE + 3);
            $this->LogMessage($this->Translate('Connection lost'), KL_ERROR);
            $this->ShowLastError($ret->getMessage());
            return false;
        }
        $SubscriptionReference = $ret->SubscriptionReference->Address->{'_'};
        $this->SendDebug('SubscriptionReference', $SubscriptionReference, 0);
        $this->WriteAttributeString('SubscriptionReference', $SubscriptionReference);
        $this->UpdateFormField('SubscriptionReference', 'caption', $SubscriptionReference);
        $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
        if (property_exists($ret->SubscriptionReference, 'ReferenceParameters')) {
            $SubscriptionId = property_exists($ret->SubscriptionReference->ReferenceParameters, 'any') ? $ret->SubscriptionReference->ReferenceParameters->any : '';
            $this->SendDebug('SubscriptionId', $SubscriptionId, 0);
            $this->WriteAttributeString('SubscriptionId', $SubscriptionId);
        } else {
            $this->WriteAttributeString('SubscriptionId', '');
        }
        $ReferenceUrl = parse_url($SubscriptionReference, PHP_URL_HOST);
        $ReferenceUrl = $ReferenceUrl !== null ? $ReferenceUrl : 'INVALID';
        if (strpos($this->ReadPropertyString('Address'), $ReferenceUrl) === false) {
            $this->LogMessage($this->Translate('This device send a invalid Subscription-Reference.'), KL_WARNING);
            $this->ShowLastError($this->Translate('This device send a invalid Subscription-Reference.'), 'Warning:');
            return false;
        }
        $this->isSubscribed = true;
        $this->SetTimerInterval('RenewSubscription', 55 * 1000);
        return true;
    }

    protected function Renew()
    {
        $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
        if ($SubscriptionReference == '') {
            $this->SendDebug('ERROR Renew', 'No SubscriptionReference', 0);
            $this->LogMessage($this->Translate('Call Renew with no SubscriptionReference'), KL_ERROR);
            return $this->Subscribe();
        }
        $Action = '<ns2:Action env:mustUnderstand="1">http://docs.oasis-open.org/wsn/bw-2/SubscriptionManager/RenewRequest</ns2:Action>';
        $Header[] = new SoapHeader('http://www.w3.org/2005/08/addressing', 'Action', new SoapVar($Action, XSD_ANYXML), true);
        $To = '<ns2:To env:mustUnderstand="1">'.$SubscriptionReference.'</ns2:To>';
        $Header[] = new SoapHeader('http://www.w3.org/2005/08/addressing', 'To', new SoapVar($To, XSD_ANYXML), true);
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
        $ret = $this->SendData($SubscriptionReference, 'event-mod.wsdl', 'Renew', true, $Params, $empty, $Header);
        if (is_a($ret, 'SoapFault')) {
            trigger_error($ret->getMessage(), E_USER_NOTICE);
            $this->SetStatus(IS_EBASE + 3);
            $this->LogMessage($this->Translate('Connection lost'), KL_ERROR);
            $this->isSubscribed = false;
            $this->SetTimerInterval('RenewSubscription', 0);
            return false;
        }
        return true;
    }
    /*@todo Fehlt noch
     *
     */
    protected function Unsubscribe()
    {
    }

    protected function GetEventProperties()
    {
        $XAddr = $this->ReadAttributeArray('XAddr');
        if ($XAddr['Events'] == '') {
            return false;
        }
        $Response = '';
        $ret = $this->SendData($XAddr['Events'], 'event-mod.wsdl', 'GetEventProperties', true, [], $Response);
        if (is_a($ret, 'SoapFault')) {
            return false;
        }

        $xml = new DOMDocument();
        $xml->loadXML($Response);
        $xs_ns = '';
        if (strpos($Response, 'xmlns:xs') > 0) {
            $xs_ns = 'xs';
        }
        if (strpos($Response, 'xmlns:xsd') > 0) {
            $xs_ns = 'xsd';
        }
        if ($xs_ns == '') {
            $xs_ns = $xml->lookupPrefix('http://www.w3.org/2001/XMLSchema');
        }
        if ($xs_ns == '') {
            if (strpos($Response, 'xs:') > 0) {
                $xs_ns = 'xs';
            }
            if (strpos($Response, 'xsd:') > 0) {
                $xs_ns = 'xsd';
            }
        }
        if (strpos($Response, 'xmlns:tt') > 0) {
            $tt_ns = 'tt';
        } else {
            $tt_ns = $xml->lookupPrefix('http://www.onvif.org/ver10/schema');
        }

        if (strpos($Response, 'xmlns:wstop') > 0) {
            $wstop_ns = 'wstop';
        } else {
            $wstop_ns = $xml->lookupPrefix('http://docs.oasis-open.org/wsn/t-1');
        }

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace($xs_ns, 'http://www.w3.org/2001/XMLSchema');
        $xpath->registerNamespace($tt_ns, 'http://www.onvif.org/ver10/schema');
        $xpath->registerNamespace($wstop_ns, 'http://docs.oasis-open.org/wsn/t-1');
        $query = '//' . $wstop_ns . ':TopicSet';
        $prefixPathLen = strlen($xpath->query($query, null, true)[0]->getNodePath());
        $query = '//*[@' . $wstop_ns . ":topic='true']/" . $tt_ns . ":MessageDescription[@IsProperty='true']/" . $tt_ns . ':Data/' . $tt_ns . ':SimpleItemDescription'; //[@Type='" . $xs_ns . ":boolean' or @Type='" . $xs_ns . ":string' or @Type='" . $xs_ns . ":int' or @Type='" . $tt_ns . ":RelayLogicalState']";
        $wsTopics = $xpath->query($query);
        $Path = [];
        foreach ($wsTopics as $wsData) {
            $Topic = substr($wsData->parentNode->parentNode->parentNode->getNodePath(), $prefixPathLen + 1);
            $Path[$Topic]['DataName'] = $wsData->attributes->getNamedItem('Name')->nodeValue;
            $Path[$Topic]['DataType'] = $wsData->attributes->getNamedItem('Type')->nodeValue;
            $wsSource = $xpath->query('../../' . $tt_ns . ':Source/' . $tt_ns . ':SimpleItemDescription', $wsData, true);
            $Path[$Topic]['SourceName'] = '';
            $Path[$Topic]['SourceType'] = '';
            if (count($wsSource) == 1) {
                $Path[$Topic]['SourceName'] = $wsSource[0]->attributes->getNamedItem('Name')->nodeValue;
                $Path[$Topic]['SourceType'] = $wsSource[0]->attributes->getNamedItem('Type')->nodeValue;
            }
            $Path[$Topic]['Receivers'] = [];
        }
        $this->lock('EventProperties');
        $this->WriteAttributeArray('EventProperties', $Path);
        $this->unlock('EventProperties');
        $EventList = $this->GetEventReceiverFormValues();
        $this->SendDebug('Update form', json_encode($EventList), 0);
        $this->UpdateFormField('Events', 'values', json_encode($EventList));
        $this->UpdateFormField('Events', 'visible', true);
        return true;
    }

    protected function GetProfiles()
    {
        $ret = $this->SendData('', 'media-mod.wsdl', 'GetProfiles', true);
        if (is_a($ret, 'SoapFault')) {
            $this->LogMessage($this->lastSOAPError, KL_ERROR);
            $this->ShowLastError($this->lastSOAPError);
            return false;
        }
        $res = json_decode(json_encode($ret), true)['Profiles'];
        $this->SendDebug('Profiles', $res, 0);
        $Profiles = array_filter($res, function ($Profile)
        {
            if (isset($Profile['VideoEncoderConfiguration']['Encoding'])) {
                if (strtoupper($Profile['VideoEncoderConfiguration']['Encoding']) == 'JPEG') {
                    return false;
                }
            }
            return true;
        });
        $VideoSourcesItems = [];
        foreach ($Profiles as $Profile) {
            if (!array_key_exists('VideoEncoderConfiguration', $Profile)) {
                continue;
            }
            $VideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['VideoSourceToken'] = $Profile['VideoSourceConfiguration']['SourceToken'];
            $VideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['VideoSourceName'] = $Profile['VideoSourceConfiguration']['Name'];
            $VideoSourcesItems[$Profile['VideoSourceConfiguration']['SourceToken']]['Profile'][] = [
                'Name'       => $Profile['VideoEncoderConfiguration']['Name'],
                'token'      => $Profile['token'],
                'ptztoken'   => isset($Profile['PTZConfiguration']['token']) ? $Profile['PTZConfiguration']['token'] : '',
                'Encoding'   => $Profile['VideoEncoderConfiguration']['Encoding'],
                'Resolution' => $Profile['VideoEncoderConfiguration']['Resolution'],
                'RateControl'=> $Profile['VideoEncoderConfiguration']['RateControl']
            ];
        }
        $VideoSources = array_values($VideoSourcesItems);
        $this->SendDebug('VideoSourcesAttribute', $VideoSources, 0);
        $this->WriteAttributeArray('VideoSources', $VideoSources);
        return true;
    }

    protected function GetCapabilities()
    {
        $Result = $this->SendData('', 'devicemgmt-mod.wsdl', 'GetCapabilities', true);
        if (is_a($Result, 'SoapFault')) {
            return false;
        }
        $ret = json_decode(json_encode($Result), true);
        $XAddr = [
            'Events'    => '',
            'Media'     => '',
            'PTZ'       => '',
            'Imaging'   => '',
            'Recording' => '',
            'Replay'    => ''
        ];
        if (isset($ret['Capabilities']['Events']['XAddr'])) {
            $XAddr['Events'] = $ret['Capabilities']['Events']['XAddr'];
            // $this->WriteAttributeString('XAddrEvents', $ret['Capabilities']['Events']['XAddr']);
        }
        if (isset($ret['Capabilities']['Media']['XAddr'])) {
            $XAddr['Media'] = $ret['Capabilities']['Media']['XAddr'];
            // $this->WriteAttributeString('XAddrMedia', $ret['Capabilities']['Media']['XAddr']);
        }
        if (isset($ret['Capabilities']['PTZ']['XAddr'])) {
            $XAddr['PTZ'] = $ret['Capabilities']['PTZ']['XAddr'];
            // $this->WriteAttributeString('XAddrPTZ', $ret['Capabilities']['PTZ']['XAddr']);
        }
        if (isset($ret['Capabilities']['Imaging']['XAddr'])) {
            $XAddr['Imaging'] = $ret['Capabilities']['Imaging']['XAddr'];
            // $this->WriteAttributeString('XAddrImageing', $ret['Capabilities']['Imaging']['XAddr']);
        }
        if (isset($ret['Capabilities']['Extension']['Recording']['XAddr'])) {
            $XAddr['Recording'] = $ret['Capabilities']['Extension']['Recording']['XAddr'];
            // $this->WriteAttributeString('XAddrRecording', $ret['Capabilities']['Extension']['Recording']['XAddr']);
        }
        if (isset($ret['Capabilities']['Extension']['Replay']['XAddr'])) {
            $XAddr['Replay'] = $ret['Capabilities']['Extension']['Replay']['XAddr'];
            //$this->WriteAttributeString('XAddrReplay', $ret['Capabilities']['Extension']['Replay']['XAddr']);
        }
        $this->WriteAttributeArray('XAddr', $XAddr);

        $HasInput = false;
        $HasOutput = false;
        if (isset($ret['Capabilities']['Device']['IO']['InputConnectors'])) {
            $HasInput = ($ret['Capabilities']['Device']['IO']['InputConnectors'] > 0);
        }
        if (isset($ret['Capabilities']['Device']['IO']['RelayOutputs'])) {
            $HasOutput = ($ret['Capabilities']['Device']['IO']['RelayOutputs'] > 0);
        } else {
            if (isset($ret['Capabilities']['Extension']['DeviceIO']['RelayOutputs'])) {
                $HasOutput = ($ret['Capabilities']['Extension']['DeviceIO']['RelayOutputs'] > 0);
            }
        }
        $this->WriteAttributeBoolean('HasInput', $HasInput);
        $this->WriteAttributeBoolean('HasOutput', $HasOutput);
        return true;
    }

    protected function GetDeviceInformation()
    {
        $ret = $this->SendData('', 'devicemgmt-mod.wsdl', 'GetDeviceInformation', true);
        if (is_a($ret, 'SoapFault')) {
            return false;
        }
        $res = json_decode(json_encode($ret), true);
        return true;
    }

    protected function GetSystemDateAndTime()
    {
        $camera_datetime = $this->SendData('', 'devicemgmt-mod.wsdl', 'GetSystemDateAndTime');
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
            $URI = $this->ReadPropertyString('Address');
        } else {
            if (strpos($URI, 'http') === false) {
                $URI = $this->Host . $URI;
            }
        }
        $this->SendDebug('Send URI', $URI, 0);
        $this->SendDebug('Send wsdl', $wsdl, 0);
        $this->SendDebug('Send Function', $Function, 0);
        $this->SendDebug('Send Params', $Params, 0);
        $wsdl = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'onvif-client-php' . DIRECTORY_SEPARATOR . 'WSDL' . DIRECTORY_SEPARATOR . $wsdl;
        if ($UseLogin) {
            $ONVIFClient = new ONVIF($wsdl, $URI, $this->ReadPropertyString('Username'), $this->ReadPropertyString('Password'), $Header, $this->ReadAttributeInteger('Timestamp_Offset'));
        } else {
            $ONVIFClient = new ONVIF($wsdl, $URI, null, null, $Header);
        }
        try {
            if (count($Params) == 0) {
                $Result = $ONVIFClient->client->{$Function}();
            } else {
                $Result = $ONVIFClient->client->{$Function}($Params);
            }
            $Response = $ONVIFClient->client->__getLastResponse();
            $this->SendDebug('Soap Request', $ONVIFClient->client->__getLastRequest(), 0);
            $this->SendDebug('Soap Response', $Response, 0);
            $this->lastSOAPError = '';
        } catch (SoapFault $e) {
            $this->SendDebug('Soap Request Error', $ONVIFClient->client->__getLastRequest(), 0);
            $this->SendDebug('Soap Response Error', $ONVIFClient->client->__getLastResponse(), 0);
            $this->SendDebug('Soap Response Error Message', $e->getMessage(), 0);
            $Response = $ONVIFClient->client->__getLastResponse();
            $this->lastSOAPError = $e->getMessage();
            return $e;
        }
        return $Result;
    }

    protected function GetEventReceiverFormValues()
    {
        $EventList = [];
        $Events = $this->ReadAttributeArray('EventProperties');
        foreach ($Events as $ListTopic => $ListEvent) {
            $Receivers = [];
            foreach ($ListEvent['Receivers'] as $Receiver) {
                if (IPS_InstanceExists($Receiver)) {
                    $Receivers[] = [
                        'instanceID' => $Receiver,
                        'Name'       => IPS_GetName($Receiver),
                        'Type'       => substr(IPS_GetInstance($Receiver)['ModuleInfo']['ModuleName'], 6)
                    ];
                }
            }
            if ($ListEvent['SourceType'] != '') {
                $ListEvent['SourceType'] = substr(stristr($ListEvent['SourceType'], ':'), 1);
            }
            if ($ListEvent['DataType'] != '') {
                $ListEvent['DataType'] = substr(stristr($ListEvent['DataType'], ':'), 1);
            }
            $EventList[] = [
                'Topic'      => substr(stristr($ListTopic, ':'), 1),
                'SourceName' => $ListEvent['SourceName'],
                'SourceType' => $ListEvent['SourceType'],
                'DataName'   => $ListEvent['DataName'],
                'DataType'   => $ListEvent['DataType'],
                'rowColor'   => (count($Receivers) > 0 ? '#FFFFFF' : ''),
                'Used'       => (count($Receivers) > 0) ? 'Yes' : 'No',
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
        $xml = simplexml_load_string($Data);
        $Notifications = $xml->xpath('//wsnt:NotificationMessage');
        $EventData = [];
        foreach ($Notifications as $Notification) {
            $NotificationDataChildren = $Notification->children('wsnt', true);
            $NotificationTopic = (string) $NotificationDataChildren->Topic;
            $NotificationMessage = $NotificationDataChildren->Message->children('tt', true)->Message;
            if (count($NotificationMessage->Source->children('tt', true)) == 0) {
                $NotificationSourceAttributes['Name'] = '';
                $NotificationSourceAttributes['Value'] = '';
            } else {
                $NotificationSourceAttributes = ((array) $NotificationMessage->Source->children('tt', true)[0]->attributes())['@attributes'];
            }
            $NotificationDataAttributes = ((array) $NotificationMessage->Data->children('tt', true)[0]->attributes())['@attributes'];
            $EventData[] = ['Topic'       => $NotificationTopic,
                'SourceName'              => $NotificationSourceAttributes['Name'],
                'SourceValue'             => $NotificationSourceAttributes['Value'],
                'DataName'                => $NotificationDataAttributes['Name'],
                'DataValue'               => $NotificationDataAttributes['Value']
            ];
        }
        $this->SendDebug('Event', $EventData, 0);
        $this->SendEventDataToChildren($EventData);
    }

    protected function SendEventDataToChildren(array $EventDataArray)
    {
        foreach ($EventDataArray as $EventData) {
            $EventData['DataID'] = '{E23DD2CD-F098-268A-CE49-1CC04FE8060B}';
            $this->SendDebug('ToChild', json_encode($EventData), 0);
            $this->SendDataToChildren(json_encode($EventData));
        }
    }

    private function KernelReady()
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CHILDREMOVED);
        $Url = parse_url($this->ReadPropertyString('Address'));
        $Url['port'] = (isset($Url['port']) ? ':' . $Url['port'] : '');
        if (isset($Url['scheme']) && isset($Url['host'])) {
            $this->Host = $Url['scheme'] . '://' . $Url['host'] . $Url['port'];
        }
        $this->ApplyChanges();
    }
}
