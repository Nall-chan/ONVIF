<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/AttributeArrayHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/onvif-client-php/inc/ONVIF.inc.php';

/**
 * @property string $Host
 */
class ONVIFIO extends IPSModule
{

    use \ONVIFIO\DebugHelper,
        \ONVIFIO\BufferHelper,
        \ONVIFIO\AttributeArrayHelper,
        \ONVIFIO\WebhookHelper;
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
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RenewSubscription', 0);

        //Never delete this line!
        parent::ApplyChanges();
        $this->Host = '';
        if (!$this->ReadPropertyBoolean('Open')) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $Url = parse_url($this->ReadPropertyString('Address'));
        $Url['port'] = (isset($Url['port']) ? ':' . $Url['port'] : '');
        if (!isset($Url['scheme']) and ! isset($Url['host'])) {
            $this->SetStatus(IS_EBASE + 1);
            $this->SetSummary('');
            $this->WriteAttributeString('ConsumerAddress', '');
            return;
        }
        $Host = $Url['scheme'] . '://' . $Url['host'] . $Url['port'];
        $this->SetSummary($Host);
        $this->Host = $Host;
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (!$this->GetVideoSources()) { // not reachable
            $this->UpdateFormField('Eventhook', 'caption', $this->ReadAttributeString('ConsumerAddress'));
            $this->WriteAttributeString('SubscriptionReference', '');
            $this->WriteAttributeString('SubscriptionId', '');
            $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
            $this->UpdateFormField('SubscriptionReference', 'caption', '');
            $Events = $this->ReadAttributeArray('EventProperties');
            $EventList = [];
            foreach ($Events as $ListTopic => $ListEvent) {
                $EventList [] = [
                    'Topic'      => $ListTopic,
                    'SourceName' => $ListEvent['Source']['Name'],
                    'SourceType' => $ListEvent['Source']['Type'],
                    'DataName'   => $ListEvent['Data']['Name'],
                    'DataType'   => $ListEvent['Data']['Type']
                ];
            }
            $Form['actions'][2]['values'] = $EventList;
            $this->UpdateFormField('Events', 'visible', true);
            $this->SetStatus(IS_EBASE + 2);
        }
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
                $this->UpdateFormField('Eventhook', 'caption', 'This device not support events.');
                $this->UpdateFormField('SubscriptionReferenceRow', 'visible', false);
                $this->UpdateFormField('Events', 'values', json_encode([]));
                $this->UpdateFormField('Events', 'visible', false);
                $this->UnregisterHook('/hook/ONFIVEvents/IO/' . $this->InstanceID);
                $this->WriteAttributeArray('EventProperties', []);
                $this->WriteAttributeString('ConsumerAddress', '');
            }
            $this->SetStatus(IS_ACTIVE);
        }
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
            return false;
        }
        $SubscriptionReference = $ret->SubscriptionReference->Address->{'_'};
        $this->SendDebug('SubscriptionReference', $SubscriptionReference, 0);
        $SubscriptionId = property_exists($ret->SubscriptionReference->ReferenceParameters, 'any') ? $ret->SubscriptionReference->ReferenceParameters->any : '';
        $this->SendDebug('SubscriptionId', $SubscriptionId, 0);
        $this->WriteAttributeString('SubscriptionReference', $SubscriptionReference);
        $this->WriteAttributeString('SubscriptionId', $SubscriptionId);
        $this->UpdateFormField('SubscriptionReference', 'caption', $SubscriptionReference);
        $this->UpdateFormField('SubscriptionReferenceRow', 'visible', true);
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
            //trigger_error($ret->getMessage(), E_USER_WARNING);
            return false;
        }

        $xml = new DOMDocument();
        $xml->loadXML($Response);
        $xs_ns = $xml->lookupPrefix('http://www.w3.org/2001/XMLSchema');
        $tt_ns = $xml->lookupPrefix('http://www.onvif.org/ver10/schema');
        $wstop_ns = $xml->lookupPrefix('http://docs.oasis-open.org/wsn/t-1');
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace($xs_ns, 'http://www.w3.org/2001/XMLSchema');
        $xpath->registerNamespace($tt_ns, 'http://www.onvif.org/ver10/schema');
        $xpath->registerNamespace($wstop_ns, 'http://docs.oasis-open.org/wsn/t-1');
        $query = '//wstop:TopicSet';
        $prefixPathlen = strlen($xpath->query($query, NULL, true)[0]->getNodePath());
        $query = "//*[@" . $wstop_ns . ":topic='true']/" . $tt_ns . ":MessageDescription/" . $tt_ns . ":Data/" . $tt_ns . ":SimpleItemDescription[@Type='" . $xs_ns . ":boolean' or @Type='" . $xs_ns . ":string' or @Type='" . $xs_ns . ":int']";
        $wsTopics = $xpath->query($query);
        $Path = [];
        foreach ($wsTopics as $wsData) {
            $Topic = substr($wsData->parentNode->parentNode->parentNode->getNodePath(), $prefixPathlen + 1);
            $Path[$Topic]['Data'] = ['Name' => $wsData->attributes->getNamedItem("Name")->nodeValue, 'Type' => $wsData->attributes->getNamedItem("Type")->nodeValue];
            $wsSource = $xpath->query("../../" . $tt_ns . ":Source/" . $tt_ns . ":SimpleItemDescription", $wsData, true);
            $Path[$Topic]['Source'] = ['Name' => '', 'Type' => ''];
            if (count($wsSource) == 1) {
                $Path[$Topic]['Source'] = ['Name' => $wsSource[0]->attributes->getNamedItem("Name")->nodeValue, 'Type' => $wsSource[0]->attributes->getNamedItem("Type")->nodeValue];
            }
        }
        $this->WriteAttributeArray('EventProperties', $Path);
        $EventList = [];
        foreach ($Path as $ListTopic => $ListEvent) {
            $EventList [] = [
                'Topic'      => $ListTopic,
                'SourceName' => $ListEvent['Source']['Name'],
                'SourceType' => $ListEvent['Source']['Type'],
                'DataName'   => $ListEvent['Data']['Name'],
                'DataType'   => $ListEvent['Data']['Type']
            ];
        }
        $this->SendDebug('Update form', json_encode($EventList), 0);
        $this->UpdateFormField('Events', 'values', json_encode($EventList));
        $this->UpdateFormField('Events', 'visible', true);
        return true;
    }

    protected function GetVideoSources()
    {
        $ret = $this->SendData('', 'media-mod.wsdl', 'GetVideoSources', true);
        if (is_a($ret, 'SoapFault')) {
            //trigger_error($ret->getMessage(), E_USER_WARNING);
            return false;
        }
        $VideoSources = [];
        if (is_array($ret->VideoSources)) {
            foreach ($ret->VideoSources as $VideoSource) {
                $VideoSources[] = $VideoSource->token;
            }
        } else {
            $VideoSources[] = $ret->VideoSources->token;
        }
        $this->SendDebug('VideoSources', $VideoSources, 0);
        $this->WriteAttributeArray('VideoSources', $VideoSources);
        return true;
    }

    protected function GetCapabilities()
    {
        $ret = $this->SendData('', 'devicemgmt-mod.wsdl', 'GetCapabilities', true);
        if (is_a($ret, 'SoapFault')) {
            //trigger_error($ret->getMessage(), E_USER_WARNING);
            return false;
        }
        $ret = json_decode(json_encode($ret), true);
        $HasInput = isset($ret['Capabilities']['Device']['IO']['InputConnectors']);
        $HasOutput = isset($ret['Capabilities']['Device']['IO']['RelayOutputs']);
        if (isset($ret['Capabilities']['Extension']['DeviceIO']['RelayOutputs'])) {
            $HasOutput = true;
        }
        $this->WriteAttributeBoolean('HasInput', $HasInput);
        $this->WriteAttributeBoolean('HasOutput', $HasOutput);
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
        if ($Data['Function'] == 'GetCredentials') {
            $Credentials['Username'] = $this->ReadPropertyString('Username');
            $Credentials['Password'] = $this->ReadPropertyString('Password');
            return serialize($Credentials);
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

    protected function SendToChilds(ONVIFData $Data)
    {
        $this->SendDebug('SendToChilds', $Data, 0);
        $this->SendDataToChildren($Data->ToJSON('{E23DD2CD-F098-268A-CE49-1CC04FE8060B}'));
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
        } catch (SoapFault $e) {
            $this->SendDebug('Soap Error', $e->getMessage(), 0);
            $this->SendDebug('Soap Error', $ONVIFclient->client->__getLastRequest(), 0);
            $Response = $ONVIFclient->client->__getLastResponse();
            return $e; //
        }
        return $Result;
    }

    public function GetConfigurationForm()
    {

        $this->SendDebug('GetConfigurationForm', 'Start', 0);
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (IPS_GetOption('NATSupport')) {
            $Form['elements'][2]['visible'] = true;
        }
        $ConsumerAddress = $this->ReadAttributeString('ConsumerAddress');
        if ($ConsumerAddress == '') {
            $ConsumerAddress = 'This device not support events.';
            $Form['actions'][1]['visible'] = false;
            $Form['actions'][2]['visible'] = false;
        }
        $Form['actions'][0]['items'][1]['caption'] = $ConsumerAddress;
        $SubscriptionReference = $this->ReadAttributeString('SubscriptionReference');
        /* if ($SubscriptionReference == '') {
          $SubscriptionReference = 'Invalid';
          } */
        $Form['actions'][1]['items'][1]['caption'] = $SubscriptionReference;
        $EventList = [];
        $Events = $this->ReadAttributeArray('EventProperties');
        foreach ($Events as $ListTopic => $ListEvent) {
            $EventList [] = [
                'Topic'      => $ListTopic,
                'SourceName' => $ListEvent['Source']['Name'],
                'SourceType' => $ListEvent['Source']['Type'],
                'DataName'   => $ListEvent['Data']['Name'],
                'DataType'   => $ListEvent['Data']['Type']
            ];
        }
        $Form['actions'][2]['values'] = $EventList;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        $this->SendDebug('GetConfigurationForm', 'Ende', 0);

        return json_encode($Form);
    }

    protected function ProcessHookData()
    {
        $Data = file_get_contents("php://input");
        //header("HTTP/1.0 200 OK");
        $this->SendDebug('Event', $Data, 0);
        $xml = simplexml_load_string($Data);
        $Notifications = $xml->xpath('//wsnt:NotificationMessage');
        $EventData = [];
        foreach ($Notifications as $Notification) {
            $NotificationDataChilds = $Notification->children('wsnt', true);
            $NotificatioTopic = (string) $NotificationDataChilds->Topic;
            $NotificationMessage = $NotificationDataChilds->Message->children('tt', true)->Message;
            $NotificationSourceAttributes = ((array) $NotificationMessage->Source->children('tt', true)[0]->attributes())['@attributes'];
            $NotificationDataAttributes = ((array) $NotificationMessage->Data->children('tt', true)[0]->attributes())['@attributes'];
            $EventData[] = ['Topic'  => $NotificatioTopic,
                'Source' => $NotificationSourceAttributes,
                'Data'   => $NotificationDataAttributes
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
