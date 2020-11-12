[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.06-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-5.5%20%3E-green.svg)](https://www.symcon.de/forum/threads/41251-IP-Symcon-5-5-%28master%29)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions) [![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](../README.md#spenden)  

# ONVIF Discovery <!-- omit in toc -->
Erkennt ONVIF kompatible Geräte innerhalb des lokalen LAN.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Vorraussetzungen](#2-vorraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanz in IP-Symcon](#4-einrichten-der-instanz-in-ip-symcon)
  - [Laden der Konfigurationsseite:](#laden-der-konfigurationsseite)
  - [Konfigurationsseite nach der Gerätesuche:](#konfigurationsseite-nach-der-gerätesuche)
  - [Anlegen von Geräten in Symcon:](#anlegen-von-geräten-in-symcon)
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

## 4. Einrichten der Instanz in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF Discovery'-Modul unter dem Hersteller 'ONVIF' aufgeführt.  
 ![Module](../imgs/Module.png)  
 Nach der Installation über den Store, wird eine Instanz von diesem Modul automatisch angelegt.  

 ### Laden der Konfigurationsseite:  

Beim öffnen der Instanz wird automatisch ein Suchlauf gestartet:  
![Wait](imgs/ConfigWait.png)  

Bei gefundenen Geräten wird versucht die Fähigkeiten des jeweiligen Gerätes zu ermitteln.  
Dabei auftretende Fehler oder Probleme werden im Anschluss der Suche über ein Popup angezeigt.  
![Error](imgs/Error.png)  

Die Fehlermeldungen enthalten weitere Hinweise, warum einige Geräte nicht in der anschließend angezeigten Liste zum erstellen auftauchen.  
So existieren Geräte welche zwingend eine Benutzeranmeldung vorraussetzen(*). Oder, wenn Geräte auch eine verschlüsselte HTTPS-Verbindung unterstützen, mehrmals in der Fehlerliste auftauchen (Siehe rote Markierung im Bild).  

_(*) Siehe weiter unten._  

### Konfigurationsseite nach der Gerätesuche:  

| Name                                                                                                    | Text | Beschreibung |
| ------------------------------------------------------------------------------------------------------- | ---- | ------------ |
| __Die Discovery-Instanz hat keine Einstellungen, welche über IPS_SetProperty verändert werden können.__ |      |              |


![Config](imgs/Config.png)  

Die Instanz listet alle im Netzwerk gefundenen Geräte auf und stellt sie, nach einem Abgleich der schon in Symcon eingerichteten [Configurator-Module](../ONVIF%20Configurator/README.md), tabellarisch in einer Liste dar.  

Sollten Zugangsdaten für die Geräte benötigt werden, was auf jeden Fall zu bevorzugen ist, dann werden Diese im Bereich `Anmeldedaten` als Benutzername und Passwort eingetragen.  
Über die `Speichern & Neuladen` Schaltfläche werden die Zugangsdaten übernommen und ein neuer Suchlauf gestartet.  

### Anlegen von Geräten in Symcon:

Wird eine Zeile selektiert und die Schaltfläche `Erstellen` betätigt, so erzeugt Symcon automatisch mehrere Instanzen (*2).  
Es wird ein Instanz des [Configurator-Module](../ONVIF%20Configurator/README.md) erzeugt, welche automatisch den Namen vom Gerät erhält.  
Dazugehörig wird eine Instanz vom [IO-Module](../ONVIF%20IO/README.md) erzeugt, welche ebenfalls den Namen vom Gerät erhält.  
Die Namen der erzeugten Instanzen können selbstverständlich geändert werden, Sie dienen nur als Hilfsmittel um schnell eine Verbindung der Instanzen zueinander zu erkennen.  
In der erzeugten Instanz vom [IO-Module](../ONVIF%20IO/README.md) werden auch die Zugangsdaten mit übernommen. Dies erfolgt einmalig wenn so eine Kette von Instanzen über diese Discovery-Instanz erstellt wurde.  
Nachträgliches ändern der Zugangsdaten muss direkt in den jeweiligen Instanzen vom [IO-Module](../ONVIF%20IO/README.md) erfolgen.  

Wurden beide Instanzen erzeugt, ändert sich die Schaltfläche von `Erstellen` auf `Konfigurieren`.  
Hierüber wird dann direkt die Konfigurationsseite der zum Gerät gehörigen Instanz vom [Configurator-Module](../ONVIF%20Configurator/README.md) geöffnet.  
Das Anlegen der einzelnen Geräte-Instanzen erfolgt dort.  

_(*2) Eventuell erscheint eine Auswahlliste wo ausgewählt werden kann über welches Protokoll ( HTTP / HTTPS ) das Gerät in Symcon eingebunden werden soll._  

## 5. Statusvariablen

Dieses Modul erzeugt keine Statusvariablen.  

## 6. WebFront

Dieses Modul ist nicht für die Darstellung im Webfront geeignet.  

## 7. PHP-Funktionsreferenz

Keine Funktionen verfügbar.  