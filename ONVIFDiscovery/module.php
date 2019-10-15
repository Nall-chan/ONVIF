<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ONVIFDiscovery {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/onvif-client-php/inc/ONVIF.inc.php';

class ONVIFDiscovery extends IPSModule
{

    use \ONVIFDiscovery\DebugHelper;
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
     * The port that will be used in the socket for the discovdery request.
     */
    const WS_DISCOVERY_MULTICAST_PORT = 3702;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
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
        if (defined('IPPROTO_IP') && defined('MCAST_JOIN_GROUP')) {
            socket_set_option($sock, IPPROTO_IP, MCAST_JOIN_GROUP, array('group' => self::WS_DISCOVERY_MULTICAST_ADDRESS));
        } else {
            socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
        }

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
            $xAddrs = self::getProbeMatchXAddrs($xml);
            $this->SendDebug('Receive from', $from, 0);
            $this->SendDebug('Receive address', $xAddrs, 0);
            $discoveryList[$from]['Address'] = $xAddrs;
            usleep(10000);
        } while (time() < $discoveryTimeout);
        socket_close($sock);
        $uselogin = false;
        if (($this->ReadPropertyString('Username') != '') or ( $this->ReadPropertyString('Password') != '')) {
            $uselogin = true;
        }
        $wsdl = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'onvif-client-php' . DIRECTORY_SEPARATOR . 'WSDL' . DIRECTORY_SEPARATOR . 'devicemgmt-mod.wsdl';
        foreach ($discoveryList as $IP => &$Values) {
            try {
                if ($uselogin) {
                    $ONVIFclient = new ONVIF($wsdl, $Values['Address'][0], $this->ReadPropertyString('Username'), $this->ReadPropertyString('Password'));
                } else {
                    $ONVIFclient = new ONVIF($wsdl, $Values['Address'][0]);
                }
                $result = $ONVIFclient->client->GetDeviceInformation();
                $this->SendDebug('Read ' . $IP, json_encode($result), 0);
                $Values = array_merge($Values, json_decode(json_encode($result), true));
            } catch (SoapFault $e) {
                $this->SendDebug('Soap Error ' . $IP, $e->getMessage(), 0);
                $Values = array_merge($Values, [
                    'Manufacturer'    => '<unknown>',
                    'Model'           => 'unknown ONVIF Device (' . $IP . ')',
                    'FirmwareVersion' => '',
                    'SerialNumber'    => ''
                ]);
            }
        }

        $this->SendDebug('Finish discovery', '', 0);
        return $discoveryList;
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

    protected static function getProbeMatchXAddrs($xmlDOMDoc)
    {
        $matches = [];
        $probeMatchNodes = $xmlDOMDoc->getElementsByTagName('ProbeMatch');
        foreach ($probeMatchNodes as $node) {
            $xAddrsNodes = $node->getElementsByTagName('XAddrs');
            foreach ($xAddrsNodes as $addrsNode) {
                $matches = array_merge($matches, explode(' ', $addrsNode->nodeValue));
            }
        }
        return $matches;
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

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm()
    {
        $Devices = $this->DiscoverDevices();
        /*
          ["192.168.201.119"]=>
          array(6) {
          ["Address"]=>
          array(1) {
          [0]=>
          string(48) "http://192.168.201.119:8899/onvif/device_service"
          }
          ["Manufacturer"]=>
          string(5) "IPCAM"
          ["Model"]=>
          string(5) "IPCAM"
          ["FirmwareVersion"]=>
          string(13) "HS-Camera_No1"
          ["SerialNumber"]=>
          string(12) "1cbfce8525cb"
          ["HardwareId"]=>
          string(36) "00048461-8461-a427-6127-1cbfce8525cb"
          }
         */
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $InstanceIDListConfigurators = IPS_GetInstanceListByModuleID('{C6A79C49-19D5-8D45-FFE5-5D77165FAEE6}');
        $DevicesAddress = [];
        $DeviceValues = [];
        foreach ($InstanceIDListConfigurators as $InstanceIDConfigurator) {
            $IO = IPS_GetInstance($InstanceIDConfigurator)['ConnectionID'];
            if ($IO > 0) {
                $DevicesAddress[$InstanceIDConfigurator] = IPS_GetProperty($IO, 'Address');
            }
        }
        $this->SendDebug('IPS', $DevicesAddress, 0);
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
                $Scheme = parse_url($Address, PHP_URL_SCHEME);
                $AddDevice['create'][$Device['Model'] . ' (' . $Scheme . ')'] = [
                    [
                        'moduleID'      => '{C6A79C49-19D5-8D45-FFE5-5D77165FAEE6}',
                        'configuration' => new stdClass()
                    ],
                    [
                        'moduleID'      => '{F40CA9A7-3B4D-4B26-7214-3A94B6074DFB}',
                        'configuration' => [
                            'Address' => $Address,
                            'Open'    => true]
                    ]
                ];

                $InstanceIDConfigurator = array_search($Address, $DevicesAddress);
                if ($InstanceIDConfigurator !== false) {
                    $AddDevice['name'] = IPS_GetLocation($InstanceIDConfigurator);
                    $AddDevice['instanceID'] = $InstanceIDConfigurator;
                    unset($DevicesAddress[$InstanceIDConfigurator]);
                }
            }
            if (count($AddDevice['create']) == 1) {
                $AddDevice['create'] = array_shift($AddDevice['create']);
            }
            $DeviceValues[] = $AddDevice;
        }
        $MissingConfigurators = [];
        foreach ($DevicesAddress as $InstanceIDConfigurator => $Address) {
            $MissingConfigurators[] = [
                'IPAddress'       => '',
                'Manufacturer'    => '',
                'Model'           => '',
                'FirmwareVersion' => '',
                'SerialNumber'    => '',
                'instanceID'      => $InstanceIDConfigurator,
                'name'            => IPS_GetLocation($InstanceIDConfigurator)
            ];
        }
        $Values = array_merge($DeviceValues, $MissingConfigurators);
        $Form['actions'][0]['values'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

}
