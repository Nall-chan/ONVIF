<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../libs/Security.php';

/**
 * Prüft die Eingabeprüfung für WS-Discovery-Adressen und die Maskierung sensibler Daten.
 */
class SecurityTest extends TestCase
{
    public static function validXAddrs(): array
    {
        return [
            'IPv4, Standardport'                      => ['http://192.168.1.10:80/onvif/device_service', '192.168.1.10', 'http://192.168.1.10'],
            'IPv4, anderer Port'                      => ['http://192.168.1.10:8080/onvif/device_service', '192.168.1.10', 'http://192.168.1.10:8080'],
            'https'                                   => ['https://192.168.1.10/onvif/device_service', '192.168.1.10', 'https://192.168.1.10'],
            'DNS-Name'                                => ['http://cam-garten.example.lan/onvif/device_service', '192.168.1.10', 'http://cam-garten.example.lan'],
            'DNS-Name mit Port'                       => ['https://cam.local:8443/onvif/device_service', '192.168.1.10', 'https://cam.local:8443'],
            'IPv6'                                    => ['http://[fd00::1]:80/onvif/device_service', 'fd00::1', 'http://[fd00::1]'],
            'IPv6 andere Schreibw.'                   => ['http://[FD00:0:0:0:0:0:0:1]/onvif/device_service', 'fd00::1', 'http://[FD00:0:0:0:0:0:0:1]'],
            'IPv6 mit Zone'                           => ['http://[fe80::1%25eth0]/onvif/device_service', 'fe80::1%eth0', 'http://[fe80::1%25eth0]'],
            'Pfad'                                    => ['http://192.168.1.10/onvif/device_service', '192.168.1.10', 'http://192.168.1.10'],
            'DNS-Name (nicht gegen Absender prüfbar)' => ['http://other.example/192.168.1.10', '192.168.1.10', 'http://other.example/192.168.1.10'],
        ];
    }

    #[DataProvider('validXAddrs')]
    public function testValidXAddr(string $XAddr, string $Sender, string $Expected): void
    {
        $this->assertSame($Expected, \ONVIF\Security::SanitizeXAddr($XAddr, $Sender));
    }

    public static function invalidXAddrs(): array
    {
        return [
            'Code-Injection'       => ["http://192.168.1.10/'.phpinfo().'", '192.168.1.10'],
            'Hochkomma im Host'    => ["http://cam'x/onvif/device_service", '192.168.1.10'],
            'Anführungszeichen'    => ['http://cam"x/onvif/device_service', '192.168.1.10'],
            'Semikolon im Pfad'    => ['http://192.168.1.10/a;b', '192.168.1.10'],
            'Dollar im Pfad'       => ['http://192.168.1.10/$x', '192.168.1.10'],
            'Leerzeichen'          => ['http://192.168.1.10/a b', '192.168.1.10'],
            'Zeilenumbruch'        => ["http://192.168.1.10/\n", '192.168.1.10'],
            'Fremde IP'            => ['http://10.0.0.99/onvif/device_service', '192.168.1.10'],
            'Zugangsdaten'         => ['http://user:pass@192.168.1.10/onvif/device_service', '192.168.1.10'],
            'Query'                => ['http://192.168.1.10/onvif/device_service?x=1', '192.168.1.10'],
            'Fragment'             => ['http://192.168.1.10/onvif/device_service#x', '192.168.1.10'],
            'Schema ftp'           => ['ftp://192.168.1.10/onvif/device_service', '192.168.1.10'],
            'Schema file'          => ['file:///etc/passwd', '192.168.1.10'],
            'Kein Schema'          => ['192.168.1.10/onvif/device_service', '192.168.1.10'],
            'Port zu groß'         => ['http://192.168.1.10:99999/onvif/device_service', '192.168.1.10'],
            'Pfad mit ..'          => ['http://192.168.1.10/a/../b;x', '192.168.1.10'],
            'IPv6 Zone Injection'  => ["http://[fe80::1%25eth0'x]/onvif/device_service", 'fe80::1'],
            'IPv4 mit Zone'        => ['http://192.168.1.10%25x/onvif/device_service', '192.168.1.10'],
            'Hostname Unterstrich' => ['http://ca_m/onvif/device_service', '192.168.1.10'],
            'Leer'                 => ['', '192.168.1.10'],
        ];
    }

    #[DataProvider('invalidXAddrs')]
    public function testInvalidXAddr(string $XAddr, string $Sender): void
    {
        $this->assertNull(\ONVIF\Security::SanitizeXAddr($XAddr, $Sender));
    }

    public function testMaskUrl(): void
    {
        $Masked = \ONVIF\Security::MaskUrl('rtsp://admin:geheim%21@192.168.1.10:554/stream?x=1');
        $this->assertSame('rtsp://***@192.168.1.10:554/stream?x=1', $Masked);
        $this->assertSame('http://192.168.1.10/snap.jpg', \ONVIF\Security::MaskUrl('http://192.168.1.10/snap.jpg'));
        $this->assertSame('http://host/a?b=c@d', \ONVIF\Security::MaskUrl('http://host/a?b=c@d'));
    }

    public function testMaskHeaders(): void
    {
        $Headers = "POST /onvif/device_service HTTP/1.1\r\nHost: cam\r\nAuthorization: Basic YWRtaW46Z2VoZWlt\r\nContent-Type: application/soap+xml\r\n";
        $Masked = \ONVIF\Security::MaskHeaders($Headers);
        $this->assertStringNotContainsString('YWRtaW46Z2VoZWlt', $Masked);
        $this->assertStringContainsString('Authorization: Basic ***', $Masked);
        $this->assertStringContainsString('Content-Type: application/soap+xml', $Masked);
        $Digest = \ONVIF\Security::MaskHeaders("Authorization: Digest username=\"admin\", response=\"abc\"\r\n");
        $this->assertStringNotContainsString('response', $Digest);
    }
}
