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
 * @property bool isConnected
 */
class ONVIFIO extends IPSModule
{

    use \ONVIFIO\DebugHelper,
        \ONVIFIO\BufferHelper,
        \ONVIFIO\AttributeArrayHelper,
        \ONVIFIO\WebhookHelper,
        \ONVIFIO\Semaphore;
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
        $this->RegisterAttributeArray('EventProperties', []);
        $this->RegisterAttributeString('ConsumerAddress', '');
        $this->RegisterAttributeString('SubscriptionReference', '');
        $this->RegisterAttributeString('SubscriptionId', '');
        $this->RegisterAttributeBoolean('HasInput', false);
        $this->RegisterAttributeBoolean('HasOutput', false);
        $this->RegisterTimer('RenewSubscription', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"Renew",true);');
        $this->Host = '';
        $this->isConnected = false;
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->RegisterMessage($this->InstanceID, FM_CHILDREMOVED);
        }
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELMESSAGE:
                if ($Data[0] == KR_READY) {
                    $this->KernelReady();
                }
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

    private function KernelReady()
    {
        $this->UnregisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage($this->InstanceID, FM_CHILDREMOVED);
        $this->LogMessage('RegisterMessage', KL_DEBUG);
        $this->ApplyChanges();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->SetTimerInterval('RenewSubscription', 0);

        if ($this->isConnected) {
            // todo
            // subscribe beenden!
        }

        $this->Host = '';
        if (!$this->ReadPropertyBoolean('Open')) {
            $this->SetStatus(IS_INACTIVE);
            $this->LogMessage('Interface closed', KL_MESSAGE);
            return;
        }

        $Url = parse_url($this->ReadPropertyString('Address'));
        $Url['port'] = (isset($Url['port']) ? ':' . $Url['port'] : '');
        if (!isset($Url['scheme']) and ! isset($Url['host'])) {
            $this->SetStatus(IS_EBASE + 1);
            $this->SetSummary('');
            $this->WriteAttributeString('ConsumerAddress', '');
            $this->LogMessage('Address is invalid', KL_ERROR);
            return;
        }
        $Host = $Url['scheme'] . '://' . $Url['host'] . $Url['port'];
        $this->SetSummary($Host);
        $this->Host = $Host;
        if (!$this->GetDeviceInformation()) { // not reachable
            $this->UpdateFormField('Eventhook', 'caption', $this->ReadAttributeString('ConsumerAddress'));
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
        if ($this->GetVideoSources()) {
            if ($this->GetCapabilities()) {
                if ($this->GetEventProperties()) { // events are valid
                    $this->RegisterHook('/hook/ONFIVEvents/IO/' . $this->InstanceID);
                    if ($this->GetConsumerAddress() === false) { // we cannot receive events :(
                        $this->WriteAttributeString('SubscriptionReference', '');
                        $this->WriteAttributeString('SubscriptionId', '');
                        $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
                        $this->UpdateFormField('SubscriptionReference', 'caption', '');
                    } else { // yeah, we can receive events
                        $this->Subscribe();
                    }
                } else { // events not possible
                    $this->EventsNotSupported();
                }
            } else {
                $this->EventsNotSupported();
            }
            $this->LogMessage('Interface connected', KL_MESSAGE);
            $this->SetStatus(IS_ACTIVE);
        }
    }

    protected function EventsNotSupported()
    {
        $this->LogMessage('Events not supported: ' . $this->lastSOAPError, KL_MESSAGE);
        $this->UpdateFormField('Eventhook', 'caption', 'This device not support events.');
        $this->UpdateFormField('SubscriptionReferenceRow', 'visible', false);
        $this->UpdateFormField('Events', 'values', json_encode([]));
        $this->UpdateFormField('Events', 'visible', false);
        $this->UnregisterHook('/hook/ONFIVEvents/IO/' . $this->InstanceID);
        $this->WriteAttributeArray('EventProperties', []);
        $this->WriteAttributeString('ConsumerAddress', '');
        $this->ShowLastError('This device does not support ONVIF events: ' . $this->lastSOAPError);
    }

    protected function ShowLastError(string $ErrorMessage)
    {
        IPS_Sleep(500);
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
            $Url = $scheme . $host . $port . '/hook/ONFIVEvents/IO/' . $this->InstanceID;
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
                $this->UpdateFormField('Eventhook', 'caption', 'Invalid');
                $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                return false;
            }
            $Url = 'http://' . $ip . ':3777/hook/ONFIVEvents/IO/' . $this->InstanceID;
            $this->SendDebug('ConsumerAddress', $Url, 0);
        }
        $this->UpdateFormField('Eventhook', 'caption', $Url);
        $this->WriteAttributeString('ConsumerAddress', $Url);
        return $Url;
    }

    protected function Subscribe()
    {
        $Params = [
            'ConsumerReference'      => [
                'Address' => $this->ReadAttributeString('ConsumerAddress')
            ],
            'InitialTerminationTime' => 'PT1M'
        ];
        $Response = '';
        $ret = $this->SendData('', 'event-mod.wsdl', 'Subscribe', true, $Params, $Response);
        if (is_a($ret, 'SoapFault')) {
            //trigger_error($ret->getMessage(), E_USER_WARNING);
            $this->SetStatus(IS_EBASE + 3);
            $this->LogMessage('Connection lost', KL_ERROR);
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
        $ReferenceUrl = parse_url($SubscriptionReference)['host'];
        if (strpos($this->ReadPropertyString('Address'), $ReferenceUrl) === false) {
            $this->LogMessage('This device send a invalid Subscription-Reference.', KL_WARNING);
            $this->ShowLastError('This device send a invalid Subscription-Reference.');
            return false;
        }
        $this->SetTimerInterval('RenewSubscription', 55 * 1000);
        return true;
    }

    protected function Renew()
    {
        $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
        if ($SubscriptionReference == '') {
            $this->SendDebug('ERROR Renew', 'No SubscriptionReference', 0);
            $this->LogMessage('Call Renew with no SubscriptionReference', KL_ERROR);
            return $this->Subscribe();
        }
        $Action = '<wsa5:Action xmlns:wsa5="http://www.w3.org/2005/08/addressing">http://docs.oasis-open.org/wsn/bw-2/SubscriptionManager/RenewRequest</wsa5:Action>';
        $Header[] = new SoapHeader('http://www.w3.org/2005/08/addressing', 'Action', new SoapVar($Action, XSD_ANYXML), true);
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
            trigger_error($ret->getMessage(), E_USER_WARNING);
            $this->SetStatus(IS_EBASE + 3);
            $this->LogMessage('Connection lost', KL_ERROR);
            $this->SetTimerInterval('RenewSubscription', 0);
            return false;
        }
        return true;
    }

    protected function GetEventProperties()
    {
        $Response = '';
        $ret = $this->SendData('', 'event-mod.wsdl', 'GetEventProperties', true, [], $Response);
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
        $prefixPathlen = strlen($xpath->query($query, NULL, true)[0]->getNodePath());
        $query = "//*[@" . $wstop_ns . ":topic='true']/" . $tt_ns . ":MessageDescription[@IsProperty='true']/" . $tt_ns . ":Data/" . $tt_ns . ":SimpleItemDescription"; //[@Type='" . $xs_ns . ":boolean' or @Type='" . $xs_ns . ":string' or @Type='" . $xs_ns . ":int' or @Type='" . $tt_ns . ":RelayLogicalState']";
        $wsTopics = $xpath->query($query);
        $Path = [];
        foreach ($wsTopics as $wsData) {
            $Topic = substr($wsData->parentNode->parentNode->parentNode->getNodePath(), $prefixPathlen + 1);
            $Path[$Topic]['DataName'] = $wsData->attributes->getNamedItem("Name")->nodeValue;
            $Path[$Topic]['DataType'] = $wsData->attributes->getNamedItem("Type")->nodeValue;
            $wsSource = $xpath->query("../../" . $tt_ns . ":Source/" . $tt_ns . ":SimpleItemDescription", $wsData, true);
            $Path[$Topic]['SourceName'] = '';
            $Path[$Topic]['SourceType'] = '';
            if (count($wsSource) == 1) {
                $Path[$Topic]['SourceName'] = $wsSource[0]->attributes->getNamedItem("Name")->nodeValue;
                $Path[$Topic]['SourceType'] = $wsSource[0]->attributes->getNamedItem("Type")->nodeValue;
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

    protected function GetVideoSources()
    {
        $ret = $this->SendData('', 'media-mod.wsdl', 'GetVideoSources', true);
        if (is_a($ret, 'SoapFault')) {
            return false;
        }
        $VideoSources = [];
        if (is_array($ret->VideoSources)) {
            foreach ($ret->VideoSources as $VideoSource) {
                $VideoSources[] = [
                    'VideoSourceToken' => $VideoSource->token,
                    'Profile'          => []
                ];
            }
        } else {
            $VideoSources[] = [
                'VideoSourceToken' => $ret->VideoSources->token,
                'Profile'          => []
            ];
        }
        if (count($VideoSources) > 0) {
            $VideoSources = $this->GetProfiles($VideoSources);
        }
        $this->SendDebug('VideoSourcesAttribute', $VideoSources, 0);
        $this->WriteAttributeArray('VideoSources', $VideoSources);
        return true;
    }

    protected function FilterProfile(&$VideoSourcesItem, $VideoSourcesIndex, $Profile)
    {
        $PossibleProfiles = array_filter($Profile, function($Profile) use($VideoSourcesItem) {
            return $Profile['VideoSourceConfiguration']['SourceToken'] == $VideoSourcesItem['VideoSourceToken'];
        });
        foreach ($PossibleProfiles as $PossibleProfile) {
            if (isset($PossibleProfile['VideoEncoderConfiguration']['Encoding'])) {
                if (strtoupper($PossibleProfile['VideoEncoderConfiguration']['Encoding']) == 'JPEG') {
                    continue;
                }
            }
            $VideoSourcesItem['Profile'][] = [
                'Name'  => $PossibleProfile['Name'],
                'token' => $PossibleProfile['token']
            ];
        }
    }

    protected function GetProfiles(array $VideoSources)
    {
        $ret = $this->SendData('', 'media-mod.wsdl', 'GetProfiles', true);
        if (is_a($ret, 'SoapFault')) {
            return false;
        }
        $res = json_decode(json_encode($ret), true);
        array_walk($VideoSources, [$this, 'FilterProfile'], $res['Profiles']);
        return $VideoSources;
    }

    protected function GetCapabilities()
    {
        $Result = $this->SendData('', 'devicemgmt-mod.wsdl', 'GetCapabilities', true);
        if (is_a($Result, 'SoapFault')) {
            return false;
        }
        $ret = json_decode(json_encode($Result), true);
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

    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        unset($Data['DataID']);
        if ($Data['Function'] == 'GetCapabilities') {
            $Capas['VideoSources'] = $this->ReadAttributeArray('VideoSources');
            $Capas['HasOutput'] = $this->ReadAttributeBoolean('HasOutput');
            $Capas['HasInput'] = $this->ReadAttributeBoolean('HasInput');
            return serialize($Capas);
        }
        if ($Data['Function'] == 'GetCapabilities') {
            $Capas['VideoSources'] = $this->ReadAttributeArray('VideoSources');
            $Capas['HasOutput'] = $this->ReadAttributeBoolean('HasOutput');
            $Capas['HasInput'] = $this->ReadAttributeBoolean('HasInput');
            return serialize($Capas);
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
                    if (($Data['Instance'] != 0) and ( !in_array($Data['Instance'], $Events[$Data['Pattern']]['Receivers']))) {
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
                            if (($Data['Instance'] != 0) and ( !in_array($Data['Instance'], $Events[$FullTopic]['Receivers']))) {
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
            $ONVIFclient = new ONVIF($wsdl, $URI, $this->ReadPropertyString('Username'), $this->ReadPropertyString('Password'), $Header);
        } else {
            $ONVIFclient = new ONVIF($wsdl, $URI, null, null, $Header);
        }
        try {
            if (count($Params) == 0) {
                $Result = $ONVIFclient->client->{$Function}();
            } else {
                $Result = $ONVIFclient->client->{$Function}($Params);
            }
            $Response = $ONVIFclient->client->__getLastResponse();
            $this->SendDebug('Result', $Result, 0);
            $this->SendDebug('Soap Request', $ONVIFclient->client->__getLastRequest(), 0);
            $this->SendDebug('Soap Response', $Response, 0);
            $this->lastSOAPError = '';
        } catch (SoapFault $e) {
            $this->SendDebug('Soap Error', $e->getMessage(), 0);
            $this->SendDebug('Soap Error', $ONVIFclient->client->__getLastRequest(), 0);
            $Response = $ONVIFclient->client->__getLastResponse();
            $this->lastSOAPError = $e->getMessage();
            return $e; //
        }
        return $Result;
    }

    public function GetConfigurationForm()
    {

        $this->SendDebug('GetConfigurationForm', 'Start', 0);
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (IPS_GetOption('NATSupport')) {
            $Form['elements'][3]['visible'] = true;
        }
        $ConsumerAddress = $this->ReadAttributeString('ConsumerAddress');
        if ($ConsumerAddress == '') {
            $ConsumerAddress = 'This device not support events.';
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
        $this->SendDebug('GetConfigurationForm', 'Ende', 0);

        return json_encode($Form);
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
            $EventList [] = [
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
            header("HTTP/1.0 404 Not found");
            return;
        }

        $Data = file_get_contents("php://input");
        $this->SendDebug('Event', $Data, 0);
        $xml = simplexml_load_string($Data);
        $Notifications = $xml->xpath('//wsnt:NotificationMessage');
        $EventData = [];
        foreach ($Notifications as $Notification) {
            $NotificationDataChilds = $Notification->children('wsnt', true);
            $NotificatioTopic = (string) $NotificationDataChilds->Topic;
            $NotificationMessage = $NotificationDataChilds->Message->children('tt', true)->Message;
            if (count($NotificationMessage->Source->children('tt',true)) == 0){
                $NotificationSourceAttributes['Name']='';
                $NotificationSourceAttributes['Value']='';
            } else {
                $NotificationSourceAttributes = ((array) $NotificationMessage->Source->children('tt', true)[0]->attributes())['@attributes'];
            }
            $NotificationDataAttributes = ((array) $NotificationMessage->Data->children('tt', true)[0]->attributes())['@attributes'];
            $EventData[] = ['Topic'       => $NotificatioTopic,
                'SourceName'  => $NotificationSourceAttributes['Name'],
                'SourceValue' => $NotificationSourceAttributes['Value'],
                'DataName'    => $NotificationDataAttributes['Name'],
                'DataValue'   => $NotificationDataAttributes['Value']
            ];
        }
        $this->SendDebug('Event', $EventData, 0);
        $this->SendEventDataToChilds($EventData);
    }

    protected function SendEventDataToChilds(array $EventDataArray)
    {
        foreach ($EventDataArray as $EventData) {
            $EventData['DataID'] = '{E23DD2CD-F098-268A-CE49-1CC04FE8060B}';
            $this->SendDebug('tochild', json_encode($EventData), 0);
            $this->SendDataToChildren(json_encode($EventData));
        }
    }

}
