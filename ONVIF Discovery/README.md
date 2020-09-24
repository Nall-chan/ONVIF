[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.00-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-5.5%20%3E-green.svg)](https://www.symcon.de/forum/threads/41251-IP-Symcon-5-5-%28Testing%29)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions) [![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  

# ONVIF Discovery
Erkennt ONVIF kompatible Geräte innerhalb des lokalen LAN.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [ONVIF Discovery](#onvif-discovery)
  - [1. Funktionsumfang](#1-funktionsumfang)
  - [2. Vorraussetzungen](#2-vorraussetzungen)
  - [3. Software-Installation](#3-software-installation)
  - [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
  - [5. Statusvariablen](#5-statusvariablen)
  - [6. WebFront](#6-webfront)
  - [7. PHP-Funktionsreferenz](#7-php-funktionsreferenz)

## 1. Funktionsumfang

  * Einfache Einrichtung von ONVIF-Konfiguratoren in Symcon.  
  * Erkennt ONVIF kompatible Geräte innerhalb des lokalen LAN.  

## 2. Vorraussetzungen

* IP-Symcon ab Version 5.5  
* Kameras oder Video-Encoder mit ONVIF Profil S Unterstützung.  

## 3. Software-Installation

* Über den Module Store das ['ONVIF'-Modul](../README.md) installieren.  

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF Discovery'-Modul unter dem Hersteller 'ONVIF' aufgeführt.
 Nach der Installation über den Store, wird eine Instanz von diesem Modul automatisch angelegt.  

 __Konfigurationsseite__:

| Name       | Text         | Beschreibung                                                                                                            |
| ---------- | ------------ | ----------------------------------------------------------------------------------------------------------------------- |
| Open       | Aktiv        | Öffnet/Aktiviert die Verbindung zum Gerät.                                                                              |
| Address    | Adresse      | URL zum ONVIF Device-Service (z.B. http://192.168.1.111/onvif/device_service)                                           |
| Username   | Benutzername | Benutzername für die Anmeldung                                                                                          |
| Password   | Passwort     | Passwort zum Benutzernamen                                                                                              |
| NATAddress | NAT Adresse  | Nur bei Betrieb von Symcon hinter einem NAT, ist hier die Public-IP ggfls. mit :Port einzutragen (z.B. 192.168.0.5:3777) |

## 5. Statusvariablen

Dieses Modul erzeugt keine Statusvariablen.  

## 6. WebFront

Dieses Modul ist nicht für die Darstellung im Webfront geeignet.  

## 7. PHP-Funktionsreferenz

Keine Funktionen verfügbar.  