[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.00-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-5.5%20%3E-green.svg)](https://www.symcon.de/forum/threads/41251-IP-Symcon-5-5-%28Testing%29)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions) [![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  

# ONVIF Events
Bildet verschiedene Ereignisse (Events) als Statusvariablen in Symcon ab.

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Vorraussetzungen](#2-vorraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
  - [Statusvariablen](#statusvariablen)

## 1. Funktionsumfang

* Empfang von Statusmeldungen von einem ONVIF-Gerät.  

## 2. Vorraussetzungen

* IP-Symcon ab Version 5.5  
* Kameras oder Video-Encoder mit ONVIF Profil S Unterstützung.
* Geräte müssen ONVIF-Events unterstützen.  

## 3. Software-Installation

* Über den Module Store das 'ONVIF'-Modul installieren.  

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF Events'-Modul unter dem Hersteller 'ONVIF' aufgeführt.

__Konfigurationsseite__:

| Name       | Text          | Beschreibung                                                                      |
| ---------- | ------------- | --------------------------------------------------------------------------------- |
| EventTopic | Ereignis-Pfad | Auswahl des Ereignis-Pfad ab welchen Ereignisse empfangen und verarbeitet werden. |

## 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

### Statusvariablen

| Name                                           | Typ      | Beschreibung                                                                                        |
| ---------------------------------------------- | -------- | --------------------------------------------------------------------------------------------------- |
| je nach Name des Events aus dem Onvif-Ereignis | variabel | Für jedes Ereignis welches einen Wert liefern kann, wird eine passende Variable in Symcon erstellt. |
