<?php

declare(strict_types=1);

namespace ONVIF
{
    /**
     * Hilfsfunktionen für Eingabeprüfung und Maskierung sensibler Daten (ohne Symcon-Abhängigkeit).
     *
     * @project       ONVIF
     * @file          Security.php
     * @package       ONVIF
     * @author        Michael Tröger <nall4chan@gmail.com>
     * @copyright     2026 Michael Tröger
     * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
     * @version       2.61
     */
    class Security
    {
        private const HostnamePattern = '/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';
        private const PathPattern = '/^(\/[A-Za-z0-9._~-]+)*$/';

        /**
         * Prüft eine per WS-Discovery empfangene XAddr und liefert die bereinigte Basisadresse.
         *
         * Erlaubt sind http und https, ohne Zugangsdaten, Query und Fragment. Der Host darf eine IP-Adresse
         * oder ein DNS-Name sein. Eine IP-Adresse muss der Absenderadresse der Antwort entsprechen;
         * ein DNS-Name kann nicht gegen den Absender geprüft werden und wird nur auf gültige Syntax kontrolliert.
         *
         * @param string $XAddr    Empfangene Adresse.
         * @param string $SenderIP IP-Adresse des Absenders der Discovery-Antwort.
         *
         * @return ?string Adresse ohne '/onvif/device_service' und ohne Standardport 80, oder null wenn ungültig.
         */
        public static function SanitizeXAddr(string $XAddr, string $SenderIP): ?string
        {
            $XAddr = trim($XAddr);
            if (($XAddr === '') || (strlen($XAddr) > 512) || preg_match('/[^\x21-\x7E]/', $XAddr)) {
                return null;
            }
            $Url = parse_url($XAddr);
            if (($Url === false) || !isset($Url['scheme'], $Url['host'])) {
                return null;
            }
            if (!in_array(strtolower($Url['scheme']), ['http', 'https'], true)) {
                return null;
            }
            if (isset($Url['user']) || isset($Url['pass']) || isset($Url['query']) || isset($Url['fragment'])) {
                return null;
            }
            $Path = str_replace('/onvif/device_service', '', $Url['path'] ?? '');
            if (!preg_match(self::PathPattern, $Path)) {
                return null;
            }
            $Host = $Url['host'];
            $IsIPv6 = false;
            if (str_starts_with($Host, '[') && str_ends_with($Host, ']')) {
                $IsIPv6 = true;
                $Host = substr($Host, 1, -1);
            }
            if ($IsIPv6 && !preg_match('/^[0-9A-Fa-f:.]+(%25[A-Za-z0-9._~-]+)?$/', $Host)) {
                return null;
            }
            $HostIP = self::NormalizeIP($IsIPv6 ? preg_replace('/%25.*$/', '', $Host) : $Host);
            if ($IsIPv6 && ($HostIP === null)) {
                return null;
            }
            if ($HostIP !== null) {
                if ($HostIP !== self::NormalizeIP(preg_replace('/%.*$/', '', $SenderIP) ?? $SenderIP)) {
                    return null;
                }
            } elseif (!preg_match(self::HostnamePattern, $Host)) {
                return null;
            }
            if (isset($Url['port']) && (($Url['port'] < 1) || ($Url['port'] > 65535))) {
                return null;
            }
            $Result = strtolower($Url['scheme']) . '://' . ($IsIPv6 ? '[' . $Host . ']' : $Host);
            if (isset($Url['port']) && !((strtolower($Url['scheme']) === 'http') && ($Url['port'] === 80))) {
                $Result .= ':' . $Url['port'];
            }
            return $Result . $Path;
        }

        /**
         * Entfernt Zugangsdaten aus einer URL für Debug-Ausgaben.
         *
         * @param string $Url URL, ggf. mit user:pass@.
         *
         * @return string URL ohne Passwort; ein Benutzername wird durch *** ersetzt.
         */
        public static function MaskUrl(string $Url): string
        {
            return preg_replace('#^([a-z][a-z0-9+.-]*://)[^/@?\#]*@#i', '$1***@', $Url) ?? $Url;
        }

        /**
         * Maskiert Authorization-Header in HTTP-Headern für Debug-Ausgaben.
         *
         * @param string $Headers HTTP-Header als Text.
         *
         * @return string Header mit maskierten Zugangsdaten.
         */
        public static function MaskHeaders(string $Headers): string
        {
            return preg_replace('/^((?:Proxy-)?Authorization:\s*\S+).*$/mi', '$1 ***', $Headers) ?? $Headers;
        }

        /**
         * Vereinheitlicht eine IP-Adresse (ohne Zone-ID, diese muss vorher entfernt werden) für Vergleiche.
         *
         * @param string $IP IPv4- oder IPv6-Adresse.
         *
         * @return ?string Binäre Darstellung oder null, wenn keine IP-Adresse.
         */
        private static function NormalizeIP(string $IP): ?string
        {
            if (filter_var($IP, FILTER_VALIDATE_IP) === false) {
                return null;
            }
            $Binary = inet_pton($IP);
            return ($Binary === false) ? null : $Binary;
        }
    }
}
