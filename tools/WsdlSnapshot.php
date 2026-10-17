<?php

declare(strict_types=1);

namespace ONVIF\Tools;

/**
 * Stabile Textdarstellung der Funktionen und Typen, die ext/soap aus einer WSDL liest.
 *
 * Der Generator (tools/update-wsdl.php) schreibt sie nach tests/wsdl-snapshot/,
 * der Unit-Test (tests/WSDLTest.php) vergleicht sie. Der Git-Diff der Snapshots zeigt
 * bei einem Update der WSDL, welche Funktionen und Typen sich geändert haben.
 *
 * @project       ONVIF
 * @file          WsdlSnapshot.php
 * @package       ONVIF
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       2.60
 */
class WsdlSnapshot
{
    public const Directory = 'tests/wsdl-snapshot';

    /**
     * Lädt eine WSDL mit ext/soap und liefert Funktionen und Typen sortiert als Text.
     *
     * @param string $WsdlFile Pfad zur WSDL-Datei
     *
     * @throws \SoapFault Wenn ext/soap die WSDL nicht laden kann
     *
     * @return string Snapshot-Text
     */
    public static function create(string $WsdlFile): string
    {
        $Client = new \SoapClient($WsdlFile, [
            'soap_version' => SOAP_1_2,
            'location'     => 'http://127.0.0.1/',
            'cache_wsdl'   => WSDL_CACHE_NONE,
            'exceptions'   => true
        ]);
        return '## Functions' . "\n" . self::sortedList($Client->__getFunctions()) . "\n\n" .
            '## Types' . "\n" . self::sortedList($Client->__getTypes()) . "\n";
    }

    /**
     * Liefert die Namen aller Funktionen einer WSDL.
     *
     * @param string $WsdlFile Pfad zur WSDL-Datei
     *
     * @return string[]
     */
    public static function functionNames(string $WsdlFile): array
    {
        $Client = new \SoapClient($WsdlFile, [
            'soap_version' => SOAP_1_2,
            'location'     => 'http://127.0.0.1/',
            'cache_wsdl'   => WSDL_CACHE_NONE,
            'exceptions'   => true
        ]);
        $Names = [];
        foreach ($Client->__getFunctions() as $Signature) {
            if (preg_match('/^\S+\s+(\w+)\(/', $Signature, $Match)) {
                $Names[] = $Match[1];
            }
        }
        return array_values(array_unique($Names));
    }

    /**
     * Dateiname des Snapshots zu einer Konstante aus \ONVIF\WSDL.
     *
     * @param string $ConstantName Name der Konstante, z. B. `Management`
     *
     * @return string
     */
    public static function fileName(string $ConstantName): string
    {
        return $ConstantName . '.txt';
    }

    /**
     * @param string[] $Entries
     */
    private static function sortedList(array $Entries): string
    {
        // ext/soap nummeriert unbenannte Typen fortlaufend; die Nummer verschiebt sich mit jeder Spec-Version
        $Entries = preg_replace('/\banonymous\d+\b/', 'anonymous', $Entries);
        $Entries = array_values(array_unique($Entries));
        sort($Entries, SORT_STRING);
        return implode("\n", $Entries);
    }
}
