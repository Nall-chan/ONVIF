# ONVIF Digital Input
Bildet die Digitalen Eingänge in Symcon ab.  

## Inhaltsverzeichnis  <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Vorraussetzungen](#2-vorraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
  - [Statusvariablen](#statusvariablen)
- [6. WebFront](#6-webfront)
- [7. PHP-Funktionsreferenz](#7-php-funktionsreferenz)

## 1. Funktionsumfang

* Empfang von Statusmeldungen der Digitalen Eingängen von ONVIF-Geräten.  

## 2. Vorraussetzungen

* IP-Symcon ab Version 5.5
* Kameras oder Video-Encoder mit ONVIF Profil S Unterstützung.
* Geräte müssen über mindestens einen Digitalen Eingang verfügen.  

## 3. Software-Installation

* Über den Module Store das ['ONVIF'-Modul](../README.md) installieren.

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF Digital Input'-Modul unter dem Hersteller 'ONVIF' aufgeführt.
![Module](../imgs/Module.png)  

 Es wird empfohlen diese Instanz über die dazugehörige Instanz des [Configurator-Moduls](../ONVIF%20Configurator/README.md) von diesem Geräte anzulegen.  
 
__Konfigurationsseite__:

![Config](imgs/Config.png)  
| Name       | Text                    | Beschreibung                                                                      |
| ---------- | ----------------------- | --------------------------------------------------------------------------------- |
| EventTopic | Ereignisse der Eingänge | Auswahl des Ereignis-Pfad ab welchen Ereignisse empfangen und verarbeitet werden. |

Der Ereignis-Pfad wird bei Digital-Input versucht automatisch zu erkennen, alternativ steht das universelle [ONVIF Events](../ONVIF%20Events/README.md) Modul zur Verfügung
## 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

### Statusvariablen

| Name                           | Typ  | Beschreibung                                                                                |
| ------------------------------ | ---- | ------------------------------------------------------------------------------------------- |
| je nach Name des Onvif-Ereignis | bool | Für jeden Digital-Eingang wird eine passende Variable in Symcon erstellt. |


## 6. WebFront

Die direkte Darstellung der Statusvariablen ist möglich; es wird aber empfohlen mit Links zu arbeiten.  

## 7. PHP-Funktionsreferenz

Keine Funktionen verfügbar.  