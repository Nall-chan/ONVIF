<?php

declare(strict_types=1);

namespace ONVIF;

/**
 * @property-write string __last_request_headers
 * @property-write string __last_response_headers
 *
 */
class ONVIFsoapClient extends \SoapClient
{
    private string $User;
    private string $Pass;
    private array $Options;

    public function __construct(string $wsdl, array $options = [])
    {
        parent::__construct($wsdl, $options);
        if (isset($options['login'])) {
            $this->User = $options['login'];
        } else {
            $this->User = '';
        }
        if (isset($options['password'])) {
            $this->Pass = $options['password'];
        } else {
            $this->User = '';
        }
        $this->Options = $options;
    }
    public function __doRequest(string $request, string $location, string $action, int $version, bool $one_way = false): ?string
    {
        $headers = [
            'Method: POST',
            'Connection: ' . ($this->Options['keep_alive'] ? 'Keep-Alive' : 'close'),
            'User-Agent: ' . $this->Options['user_agent'],
            'Content-Type: application/soap+xml; charset=utf-8',
        ];

        $ch = curl_init($location);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->Options['connection_timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTP09_ALLOWED, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        if (isset($this->Options['authentication'])) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERNAME, $this->User);
            curl_setopt($ch, CURLOPT_PASSWORD, $this->Pass);
        }
        $response = curl_exec($ch);
        //$this->CurlInfo = curl_getinfo($ch);
        $http_code = curl_getinfo($ch)['http_code'];
        if ($http_code != 0) {
            $this->__last_request_headers = curl_getinfo($ch)['request_header'];
        }
        $curl_errno = curl_errno($ch);
        if ($curl_errno) {
            throw new \SoapFault((string) $curl_errno, curl_error($ch));
            return '';
        }
        if (!is_bool($response)) {
            $Parts = explode("\r\n\r\n<?xml", $response);
            $Headers = explode("\r\n\r\n", array_shift($Parts));
            $LastHeader = array_pop($Headers);
            if ($LastHeader == '') {
                $LastHeader = array_pop($Headers);
            }
            $this->__last_response_headers = $LastHeader;
            if (count($Parts)) {
                $response = '<?xml' . implode("\r\n\r\n<?xml", $Parts);
            } else {
                $response = '';
            }
        }
        if ($http_code > 400) { /*&& ($response == ''))*/
            throw new \SoapFault('http:' . $http_code, explode("\r\n", $this->__last_response_headers)[0]);
            return '';
        }
        return is_bool($response) ? '' : $response;
    }
}

class ONVIF
{
    public $client;

    public function __construct(string $wsdl, string $service, ?string $username = null, ?string $password = null, array $Headers = [], int $Timeout = 5)
    {
        $Options = [
            'trace'              => true,
            'exceptions'         => true,
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'connection_timeout' => $Timeout,
            'user_agent'         => 'Symcon ONVIF-Lib by Nall-chan',
            'keep_alive'         => false,
            'soap_version'       => SOAP_1_2,
            'location'           => $service
        ];
        if (($username != null) || ($password != null)) {
            $username = ($username == null ? '' : $username);
            $password = ($password == null ? '' : $password);
            $Options['login'] = $username;
            $Options['password'] = $password;
            $Options['authentication'] = SOAP_AUTHENTICATION_DIGEST;
        }
        $this->client = new ONVIFsoapClient($wsdl, $Options);
        $this->client->__setSoapHeaders($Headers);
        ini_set('default_socket_timeout', (string) $Timeout);
        return;
    }

    public static function soapClientWSSecurityHeader(string $user, string $password, int $ts_offset = 0): \SoapHeader
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
        $root = new \SimpleXMLElement('<root/>');

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

        return new \SoapHeader($ns_wsse, 'Security', new \SoapVar($auth, XSD_ANYXML), true);
    }
}
