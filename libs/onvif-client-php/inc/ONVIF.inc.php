<?php

declare(strict_types=1);

/**
 * ONVIF base class
 *
 *
 * @author Nickola Trupcheff <n.trupcheff@gmail.com>
 * @version 0.1
 */

/**
 * The ONVIF class
 *
 * Contains some needed functions it is used by the other classes. Not to be used directly.
 */
class ONVIF
{
    public $wsdl;
    public $version;
    public $client;
    protected $login;

    /**
     * If you have troubles authorizing try syncing the time with the camera...
     * and crazy as it sounds - capitalizing the first letter of the username
     *
     * @param string $wsdl URL for the modified devicemgmt.wsdl WSDL included with the library
     * @param string $service Camera ONVIF URL
     * @param string $username Camera username
     * @param string $password Camera password
     */
    public function __construct($wsdl, $service, $username = null, $password = null, $Headers = [], $ts_offset = 0)
    {
        $this->wsdl = $wsdl;
        $Options = [
            'trace'              => true,
            'exceptions'         => true,
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'ssl_method '        => SOAP_SSL_METHOD_TLS,
            'connection_timeout' => 5,
            'user_agent'         => 'Symcon ONVIF-Lib by Nall-chan',
            'keep_alive'         => false,
            'soap_version'       => SOAP_1_2,
            'compression'        => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'stream_context'     => stream_context_create(
                [
                    'ssl'  => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ],
                    'http' => [
                        'protocol_version' => 1.1,
                        'timeout'          => 5
                    ],
                ]
            )
        ];
        if (($username != null) || ($password != null)) {
            $username = ($username == null ? '' : $username);
            $password = ($password == null ? '' : $password);
            $Headers[] = $this->soapClientWSSecurityHeader($username, $password, $ts_offset);
            $Options['login'] = $username;
            $Options['password'] = $password;
            $Options['authentication'] = SOAP_AUTHENTICATION_DIGEST;
        }
        $this->client = new SoapClient($this->wsdl, $Options);
        $this->client->__setLocation($service);
        $this->client->__setSoapHeaders($Headers);
        ini_set('default_socket_timeout', '5');
        return;
    }

    public function obj_dump($object, $level = 1)
    {
        foreach ($object as $okey => $oval) {
            if (is_array($oval) || is_object($oval)) {
                for ($i = 0; $i < $level * 3; $i++) {
                    echo ' ';
                }
                echo $okey . "\n";
                $this->obj_dump($oval, $level + 1);
            } elseif (is_bool($oval)) {
                for ($i = 0; $i < $level * 3; $i++) {
                    echo ' ';
                }
                printf("%s: %s\n", $okey, $oval ? 'true' : 'false');
            } else {
                for ($i = 0; $i < $level * 3; $i++) {
                    echo ' ';
                }
                printf("%s: %s\n", $okey, $oval);
            }
        }
    }

    public function response_dump($name, $response)
    {
        echo "================================================================================\n";
        echo "$name\n";
        echo "--------------------------------------------------------------------------------\n";
        $this->obj_dump($response, 1);
        echo "================================================================================\n";
    }

    protected function soapClientWSSecurityHeader($user, $password, $ts_offset = 0)
    {
        $ts = time() - $ts_offset;

        // Creating date using yyyy-mm-ddThh:mm:ssZ format
        $tm_created = gmdate('Y-m-d\TH:i:s\Z', $ts);

        // Generating and encoding a random number
        $simple_nonce = mt_rand();
        $encoded_nonce = base64_encode((string) $simple_nonce);

        // Compiling WSS string
        $passdigest = base64_encode(sha1($simple_nonce . $tm_created . $password, true));

        // Initializing namespaces
        $ns_wsse = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $ns_wsu = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
        $password_type = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest';
        $encoding_type = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';

        // Creating WSS identification header using SimpleXML
        $root = new SimpleXMLElement('<root/>');

        $security = $root->addChild('wsse:Security', null, $ns_wsse);

        //the timestamp element is not required by all servers
        $timestamp = $security->addChild('wsu:Timestamp', null, $ns_wsu);
        $timestamp->addAttribute('wsu:Id', 'Timestamp-28');
        $timestamp->addChild('wsu:Created', $tm_created, $ns_wsu);
        #		$timestamp->addChild('wsu:Expires', $tm_expires, $ns_wsu);

        $usernameToken = $security->addChild('wsse:UsernameToken', null, $ns_wsse);
        $usernameToken->addChild('wsse:Username', $user, $ns_wsse);
        $usernameToken->addChild('wsse:Password', $passdigest, $ns_wsse)->addAttribute('Type', $password_type);
        $usernameToken->addChild('wsse:Nonce', $encoded_nonce, $ns_wsse)->addAttribute('EncodingType', $encoding_type);
        $usernameToken->addChild('wsu:Created', $tm_created, $ns_wsu);

        // Recovering XML value from that object
        $root->registerXPathNamespace('wsse', $ns_wsse);
        $full = $root->xpath('/root/wsse:Security');
        $auth = $full[0]->asXML();

        return new SoapHeader($ns_wsse, 'Security', new SoapVar($auth, XSD_ANYXML), true);
    }
}
