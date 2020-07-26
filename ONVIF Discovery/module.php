<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ONVIFDiscovery {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFDiscovery {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace ONVIFDiscovery {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/onvif-client-php/inc/ONVIF.inc.php';

/**
 * @property array $Devices
 * @property array $DevicesError
 * @property int $DevicesTotal
 * @property int $DevicesProcessed
 */
class ONVIFDiscovery extends IPSModule
{
    use \ONVIFDiscovery\BufferHelper;
    use
        \ONVIFDiscovery\DebugHelper;
    use
        \ONVIFDiscovery\Semaphore;
    const WS_DISCOVERY_MESSAGE = '<?xml version="1.0" encoding="UTF-8"?><e:Envelope xmlns:e="http://www.w3.org/2003/05/soap-envelope" xmlns:w="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:d="http://schemas.xmlsoap.org/ws/2005/04/discovery" xmlns:dn="http://www.onvif.org/ver10/network/wsdl"><e:Header><w:MessageID>uuid:[UUID]</w:MessageID><w:To e:mustUnderstand="true">urn:schemas-xmlsoap-org:ws:2005:04:discovery</w:To><w:Action e:mustUnderstand="true">http://schemas.xmlsoap.org/ws/2005/04/discovery/Probe</w:Action></e:Header><e:Body><d:Probe><d:Types>dn:NetworkVideoTransmitter</d:Types></d:Probe></e:Body></e:Envelope>';

    /**
     * The maximum number of seconds that will be allowed for the discovery request.
     */
    const WS_DISCOVERY_TIMEOUT = 5;

    /**
     * The multicast address to use in the socket for the discovery request.
     */
    const WS_DISCOVERY_MULTICAST_ADDRESS = '239.255.255.250';

    /**
     * The port that will be used in the socket for the discovery request.
     */
    const WS_DISCOVERY_MULTICAST_PORT = 3702;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterAttributeString('Username', '');
        $this->RegisterAttributeString('Password', '');
        $this->Devices = [];
        $this->DevicesError = [];
        $this->DevicesTotal = 0;
        $this->DevicesProcessed = 0;
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->ApplyChanges();
                break;
        }
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        // Wenn Kernel nicht bereit, dann warten... KR_READY kommt ja gleich
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        //Hier den Scan starten. Passiert ja nur beim starten von IPS oder wenn die Instanz erstellt wurde.
        $this->Discover();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm()
    {
        $Devices = $this->Devices;
        $DevicesError = $this->DevicesError;
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $InstanceIDListConfigurator = IPS_GetInstanceListByModuleID('{C6A79C49-19D5-8D45-FFE5-5D77165FAEE6}');
        $DevicesAddress = [];
        $DeviceValues = [];
        foreach ($InstanceIDListConfigurator as $InstanceIDConfigurator) {
            $IO = IPS_GetInstance($InstanceIDConfigurator)['ConnectionID'];
            if ($IO > 0) {
                $DevicesAddress[$InstanceIDConfigurator] = IPS_GetProperty($IO, 'Address');
            }
        }
        foreach ($Devices as $IP => $Device) {
            $AddDevice = [
                'instanceID'      => 0,
                'IPAddress'       => $IP,
                'name'            => $Device['Model'],
                'Manufacturer'    => $Device['Manufacturer'],
                'Model'           => $Device['Model'],
                'FirmwareVersion' => $Device['FirmwareVersion'],
                'SerialNumber'    => $Device['SerialNumber'],
            ];
            foreach ($Device['Address'] as $Address) {
                $ConfigIo = [
                    'Address' => $Address,
                    'Open'    => true];
                $InstanceIDConfigurator = array_search($Address, $DevicesAddress);

                if ($InstanceIDConfigurator !== false) {
                    $AddDevice['name'] = IPS_GetLocation($InstanceIDConfigurator);
                    $AddDevice['instanceID'] = $InstanceIDConfigurator;
                    unset($DevicesAddress[$InstanceIDConfigurator]);
                } else {
                    $ConfigIo['Username'] = $this->ReadAttributeString('Username');
                    $ConfigIo['Password'] = $this->ReadAttributeString('Password');
                }
                $AddDevice['create'][$Device['Model'] . ' (' . $Address . ')'] = [
                    [
                        'moduleID'      => '{C6A79C49-19D5-8D45-FFE5-5D77165FAEE6}',
                        'configuration' => new stdClass()
                    ],
                    [
                        'moduleID'      => '{F40CA9A7-3B4D-4B26-7214-3A94B6074DFB}',
                        'configuration' => $ConfigIo
                    ]
                ];
            }
            if (count($AddDevice['create']) == 1) {
                $AddDevice['create'] = array_shift($AddDevice['create']);
            }
            $DeviceValues[] = $AddDevice;
        }
        // Todo
        // Konfiguratoren in Symcon ohne Device fehlen:
        // DevicesAddress
        $Form['actions'][0]['items'][0]['items'][0]['value'] = $this->ReadAttributeString('Username');
        $Form['actions'][0]['items'][0]['items'][1]['value'] = $this->ReadAttributeString('Password');
        $Form['actions'][1]['values'] = $DeviceValues;
        if ($this->DevicesProcessed == $this->DevicesTotal) {
            if (count($DevicesError) > 0) {
                $ErrorValues = [];
                foreach ($DevicesError as $IPAddress => $ErrorMessage) {
                    $ErrorValues[] = ['IPAddress' => $IPAddress, 'ErrorMessage' => $ErrorMessage];
                }
                $Form['actions'][2]['visible'] = true;
                $Form['actions'][2]['popup']['items'][1]['values'] = $ErrorValues;
            }
            $Form['actions'][3]['visible'] = false;
        }

        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == 'Save') {
            $Data = explode(':', $Value);
            $this->WriteAttributeString('Username', urldecode($Data[0]));
            $this->WriteAttributeString('Password', urldecode($Data[1]));
            $this->ApplyChanges();
            return;
        }
        if ($Ident == 'ScanDevice') {
            $Data = unserialize($Value);
            $this->ScanDevice($Data['IP'], $Data['xAddrs']);
        }
    }

    protected function Discover()
    {
        $this->LogMessage($this->Translate('Background discovery of ONVIF devices started'), KL_NOTIFY);
        $this->Devices = [];
        $this->DevicesError = [];
        $this->DevicesTotal = 0;
        $this->DevicesProcessed = -1;
        $this->ReloadForm();
        $discoveryList = $this->DiscoverDevices();
        $this->DevicesTotal = count($discoveryList);
        //$this->UpdateFormField('ScanProgress', 'visible', true);
        $this->UpdateFormField('ScanProgress', 'maximum', count($discoveryList));
        $this->UpdateFormField('ScanProgress', 'current', 0);
        $this->UpdateFormField('ScanProgress', 'caption', '0 / ' . count($discoveryList));
        $this->LogMessage(sprintf($this->Translate('Background discovery of ONVIF found %d devices'), count($discoveryList)), KL_NOTIFY);
        $i = 0;
        $this->DevicesProcessed = 0;
        foreach ($discoveryList as $IP => $Device) {
            $i++;
            $ScriptText = 'IPS_RequestAction(' . $this->InstanceID . ', \'ScanDevice\',\'' . serialize(['IP' => $IP, 'xAddrs' => $Device]) . '\');';
            IPS_RunScriptText($ScriptText);
            if ($i % 3) {
                IPS_Sleep(400);
            }
        }
    }

    protected function DiscoverDevices()
    {
        $discoveryTimeout = time() + self::WS_DISCOVERY_TIMEOUT;
        $uuid = self::uuidV4();
        $discoveryMessage = str_replace('[UUID]', $uuid, self::WS_DISCOVERY_MESSAGE);
        $discoveryPort = self::WS_DISCOVERY_MULTICAST_PORT;
        $discoveryList = [];
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);
        /* if (defined('IPPROTO_IP') && defined('MCAST_JOIN_GROUP')) {
          socket_set_option($sock, IPPROTO_IP, MCAST_JOIN_GROUP, array('group' => self::WS_DISCOVERY_MULTICAST_ADDRESS));
          } else { */
        socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
        //}

        socket_bind($sock, '0.0.0.0', 0); //self::WS_DISCOVERY_MULTICAST_PORT);
        $this->SendDebug('Start Discovery', '', 0);
        socket_sendto($sock, $discoveryMessage, strlen($discoveryMessage), 0, self::WS_DISCOVERY_MULTICAST_ADDRESS, self::WS_DISCOVERY_MULTICAST_PORT);
        $response = $from = null;
        do {
            if (0 == @socket_recvfrom($sock, $response, 9999, 0, $from, $discoveryPort)) {
                continue;
            }
            $this->SendDebug('Receive', $response, 0);
            $xml = new DOMDocument();
            if (false === $xml->loadXML($response)) {
                $this->SendDebug('Error on parse XML', $response, 0);
                continue;
            }
            if (!self::relatesToMatch($uuid, $xml)) {
                $this->SendDebug('Skip Data', 'UUID incorrect', 0);
                continue;
            }
            $xAddrs = self::getProbeMatchXAddrs($xml, $from);
            $this->SendDebug('Receive from', $from, 0);
            $this->SendDebug('Receive address', $xAddrs, 0);
            $discoveryList[$from] = $xAddrs;
            usleep(10000);
        } while (time() < $discoveryTimeout);
        socket_close($sock);
        return $discoveryList;
    }

    protected function ScanDevice(string $IP, array $IpValues)
    {
        $UseLogin = false;
        if (($this->ReadAttributeString('Username') != '') || ($this->ReadAttributeString('Password') != '')) {
            $UseLogin = true;
        }
        $wsdl = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'onvif-client-php' . DIRECTORY_SEPARATOR . 'WSDL' . DIRECTORY_SEPARATOR . 'devicemgmt-mod.wsdl';
        //$DevicesOk = [];
        //$DevicesError = [];
        $this->LogMessage(sprintf($this->Translate('Scan of ONVIF device (%s) started'), $IP), KL_NOTIFY);
        $Device = null;
        $DeviceOk = false;
        $DeviceError = [];
        foreach ($IpValues as $IpValue) {
            $this->SendDebug('Request', $IpValue, 0);
            if ($UseLogin) {
                $offset = $this->GetTimeOffset($IpValue);
                $ONVIFClient = new ONVIF($wsdl, $IpValue, $this->ReadAttributeString('Username'), $this->ReadAttributeString('Password'), [], $offset);
            } else {
                $ONVIFClient = new ONVIF($wsdl, $IpValue);
            }
            try {
                $result = $ONVIFClient->client->GetDeviceInformation();
                $this->SendDebug('Soap Request ' . $IpValue, $ONVIFClient->client->__getLastRequest(), 0);
                $this->SendDebug('Soap Response ' . $IpValue, $ONVIFClient->client->__getLastResponse(), 0);
                $this->SendDebug('Read ' . $IpValue, json_encode($result), 0);
                if ($Device === null) {
                    $Device = json_decode(json_encode($result), true);
                    $DeviceOk = true;
                }
                $Device['Address'][] = $IpValue;
            } catch (SoapFault $e) {
                $this->SendDebug('Soap Request Error ' . $IpValue, $ONVIFClient->client->__getLastRequest(), 0);
                $this->SendDebug('Soap Response Error ' . $IpValue, $ONVIFClient->client->__getLastResponse(), 0);
                $this->SendDebug('Soap Response Error Message ' . $IpValue, $e->getMessage(), 0);
                $Url = parse_url($IpValue);
                $Url['port'] = isset($Url['port']) ? ':' . $Url['port'] : '';
                $DeviceError[$Url['scheme'] . '://' . $Url['host'] . $Url['port']] = $e->getMessage();
            }
        }
        if ($DeviceOk) {
            $this->lock('Devices');
            $this->Devices = array_merge($this->Devices, [$IP => $Device]);
            $this->unlock('Devices');
        } else {
            $this->lock('DevicesError');
            $this->DevicesError = array_merge($this->DevicesError, $DeviceError);
            $this->unlock('DevicesError');
        }
        $this->lock('ScanProgress');
        $DevicesProcessed = $this->DevicesProcessed;
        $DevicesProcessed++;
        $this->DevicesProcessed = $DevicesProcessed;
        $this->UpdateFormField('ScanProgress', 'current', $DevicesProcessed);
        $this->UpdateFormField('ScanProgress', 'caption', $DevicesProcessed . ' / ' . $this->DevicesTotal);
        $this->unlock('ScanProgress');
        $this->LogMessage(sprintf($this->Translate('Scan progress of ONVIF devices: %d / %d '), $DevicesProcessed, $this->DevicesTotal), KL_NOTIFY);
        $this->SendDebug('Scan finish', $IP, 0);
        if ($DevicesProcessed == $this->DevicesTotal) {
            $this->LogMessage($this->Translate('End of background discovery of ONVIF devices'), KL_NOTIFY);
            $this->ReloadForm();
        }
    }

    protected static function relatesToMatch($uuid, $xmlDOMDoc)
    {
        $relatesNodes = $xmlDOMDoc->getElementsByTagName('RelatesTo');
        foreach ($relatesNodes as $node) {
            if (preg_match('|' . $uuid . '$|', $node->nodeValue)) {
                return true;
            }
        }
        return false;
    }

    protected static function getProbeMatchXAddrs($xmlDOMDoc, $ip)
    {
        $matches = [];
        $probeMatchNodes = $xmlDOMDoc->getElementsByTagName('ProbeMatch');
        foreach ($probeMatchNodes as $node) {
            $xAddrsNodes = $node->getElementsByTagName('XAddrs');
            foreach ($xAddrsNodes as $addrsNode) {
                $matches = array_merge($matches, explode(' ', $addrsNode->nodeValue));
            }
        }
        $filtermatches = array_filter($matches, function ($item) use ($ip)
        {
            return strpos($item, $ip);
        });
        //todo reindex
        return array_values($filtermatches);
    }

    /**
     * Roger Stringer's UUID function, http://rogerstringer.com/2013/11/15/generate-uuids-php/
     *
     * @return string A random uuid.
     */
    protected static function uuidV4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, // this sequence must start with 4
                                                mt_rand(0, 0x3fff) | 0x8000, // this sequence can start with 8, 9, A, or B
                                                        mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    protected function GetTimeOffset(string $IpValue)
    {
        $wsdl = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'onvif-client-php' . DIRECTORY_SEPARATOR . 'WSDL' . DIRECTORY_SEPARATOR . 'devicemgmt-mod.wsdl';
        $ONVIFClient = new ONVIF($wsdl, $IpValue);
        try {
            $camera_datetime = $ONVIFClient->client->GetSystemDateAndTime();
        } catch (SoapFault $e) {
            return 0;
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
            return time() - $camera_ts;
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
            return time() - $camera_ts;
        }
        return 0;
    }
}
