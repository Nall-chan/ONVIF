<?php

declare(strict_types=1);

use ONVIF\Tools\WsdlSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../libs/wsdl.php';
require_once __DIR__ . '/../tools/WsdlSnapshot.php';

/**
 * Prüft die WSDL-Dateien unter libs/WSDL (erzeugt mit tools/update-wsdl.php).
 *
 * @project       ONVIF
 * @file          WSDLTest.php
 * @package       ONVIF
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       2.60
 */
class WSDLTest extends TestCase
{
    private const WsdlDir = __DIR__ . '/../libs/WSDL';
    private const SnapshotDir = __DIR__ . '/wsdl-snapshot';
    private const ModificationMarker = 'has been modified from the original ONVIF specification';

    /**
     * SOAP-Funktionen, die die Module über SendData aufrufen.
     * Neue Aufrufe hier ergänzen, damit ein WSDL-Update sie nicht unbemerkt entfernt.
     */
    private const UsedFunctions = [
        'ContinuousMove', 'CreatePullPointSubscription', 'GetAudioSources', 'GetCapabilities', 'GetConfiguration',
        'GetDeviceInformation', 'GetDigitalInputConfigurationOptions', 'GetDigitalInputs', 'GetEventProperties',
        'GetNode', 'GetNodes', 'GetPresets', 'GetProfiles', 'GetRecordingJobState', 'GetRecordingJobs',
        'GetRelayOutputs', 'GetRules', 'GetScopes', 'GetSerialPorts', 'GetServiceCapabilities', 'GetServices',
        'GetSnapshotUri', 'GetStreamUri', 'GetSupportedAnalyticsModules', 'GetSupportedRules',
        'GetSystemDateAndTime', 'GetVideoSources', 'GotoHomePosition', 'GotoPreset', 'PullMessages', 'Renew',
        'SetRecordingJobMode', 'SetRelayOutputState', 'SetSynchronizationPoint', 'Stop', 'Subscribe', 'Unsubscribe',
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function wsdlProvider(): array
    {
        $Cases = [];
        foreach ((new ReflectionClass(\ONVIF\WSDL::class))->getConstants() as $Name => $Path) {
            $Cases[$Name] = [$Name, $Path];
        }
        return $Cases;
    }

    #[DataProvider('wsdlProvider')]
    public function testWsdlMatchesSnapshot(string $Name, string $Path): void
    {
        $this->requireSoap();
        $Snapshot = self::SnapshotDir . '/' . WsdlSnapshot::fileName($Name);
        $this->assertFileExists($Snapshot, 'Snapshot missing, run tools/update-wsdl.php');
        $this->assertSame(
            str_replace("\r\n", "\n", (string) file_get_contents($Snapshot)),
            WsdlSnapshot::create(self::WsdlDir . '/' . $Path),
            'Functions or types of ' . $Path . ' differ from ' . WsdlSnapshot::Directory . '/' . WsdlSnapshot::fileName($Name)
        );
    }

    public function testUsedFunctionsExist(): void
    {
        $this->requireSoap();
        $Available = [];
        foreach ((new ReflectionClass(\ONVIF\WSDL::class))->getConstants() as $Path) {
            $Available = array_merge($Available, WsdlSnapshot::functionNames(self::WsdlDir . '/' . $Path));
        }
        $this->assertSame([], array_values(array_diff(self::UsedFunctions, $Available)), 'SOAP functions used by the modules are missing in libs/WSDL');
    }

    public function testNoRemoteImports(): void
    {
        $Remote = [];
        $Iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::WsdlDir, FilesystemIterator::SKIP_DOTS));
        foreach ($Iterator as $File) {
            /** @var SplFileInfo $File */
            if (!in_array($File->getExtension(), ['wsdl', 'xsd'], true)) {
                continue;
            }
            $Dom = new DOMDocument();
            $this->assertTrue($Dom->load($File->getPathname(), LIBXML_NONET), 'Invalid XML: ' . $File->getPathname());
            $XPath = new DOMXPath($Dom);
            $XPath->registerNamespace('wsdl', 'http://schemas.xmlsoap.org/wsdl/');
            $XPath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
            $Attributes = $XPath->query('//xs:import/@schemaLocation | //xs:include/@schemaLocation | //xs:redefine/@schemaLocation | //wsdl:import/@location');
            foreach ($Attributes as $Attribute) {
                if (preg_match('#^\s*https?://#i', $Attribute->value)) {
                    $Remote[] = $File->getFilename() . ': ' . $Attribute->value;
                }
            }
        }
        $this->assertSame([], $Remote, 'libs/WSDL must not load schemas from the network');
    }

    public function testModifiedFilesCarryNotice(): void
    {
        $VersionFile = self::WsdlDir . '/VERSION.json';
        $this->assertFileExists($VersionFile, 'libs/WSDL/VERSION.json missing, run tools/update-wsdl.php');
        $Version = json_decode((string) file_get_contents($VersionFile), true);
        $Wrong = [];
        foreach ($Version['Files'] as $Path => $Rules) {
            $HasNotice = str_contains((string) file_get_contents(self::WsdlDir . '/' . $Path), self::ModificationMarker);
            if ($HasNotice !== (count($Rules) > 0)) {
                $Wrong[] = $Path . (count($Rules) ? ': modified, notice missing' : ': unmodified, notice present');
            }
        }
        $this->assertSame([], $Wrong, 'Modification notice (tools/update-wsdl.php) does not match the applied rules');
    }

    private function requireSoap(): void
    {
        if (!extension_loaded('soap')) {
            $this->markTestSkipped('ext/soap not available');
        }
    }
}
