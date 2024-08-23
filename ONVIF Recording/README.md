[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.20-blue.svg)](https://community.symcon.de/t/modul-onvif-profil-s-fuer-ip-kameras-und-encoder/52036)
[![Version](https://img.shields.io/badge/Symcon%20Version-7.0%20%3E-green.svg)](https://www.symcon.de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)
[![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#3-spenden)[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#3-spenden)  

# ONVIF Recording <!-- omit in toc -->
Anzeige uns Steuerung von Aufnahmen.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen](#5-statusvariablen)
- [6. WebFront](#6-webfront)
- [7. PHP-Funktionsreferenz](#7-php-funktionsreferenz)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

* Instanz für die einfache Anzeige und Steuerung von Aufzeichnungen welche durch die Geräten selbstständig  verwaltet werden.  

## 2. Voraussetzungen

* IP-Symcon ab Version 6.1
* Kameras oder Video-Encoder mit ONVIF Profil G und eingerichteter Aufzeichnung.

## 3. Software-Installation

* Dieses Modul ist Bestandteil der [ONVIF-Library](../README.md#3-software-installation).    

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF Recording'-Modul unter dem Hersteller 'ONVIF' aufgeführt.
![Module](../imgs/Module.png)  

 Es wird empfohlen diese Instanz über die dazugehörige Instanz des [Configurator-Moduls](../ONVIF%20Configurator/README.md) von diesem Geräte anzulegen.  
 
__Konfigurationsseite__:

![Config](imgs/Config.png)  

| Name        | Text                       | Beschreibung                                                                                                  |
| ----------- | -------------------------- | ------------------------------------------------------------------------------------------------------------- |
| EventTopic  | Ereignisse für die Aufzeichnung | Auswahl des Ereignis-Pfad für Recording-Jobs (*).                         |
| EmulateStatus | Status simulieren       | Wenn aktiviert, wird die Statusvariable in Symcon auf den neuen Wert gesetzt, sobald ein Steuerbefehl erfolgreich übertragen wurde. Sonst wird nur über ankommende Ereignisse gesetzt. |

(*)  _Durch eine Änderung des Ereignis-Pfad werden die alten Statusvariablen hinfällig und müssen manuell gelöscht werden._   

Der Ereignis-Pfad wird für Recordings Jobs versucht automatisch zu erkennen, funktioniert dies nicht, ist das Eingabefeld aktiv und das Ereignis muss manuell ausgewählt werden. 

## 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt und erhalten das `~Switch` Profil. Das Löschen einzelner Statusvariablen kann zu Fehlfunktionen führen. 

| Name                                                | Typ  | Beschreibung                                                               |
| --------------------------------------------------- | ---- | -------------------------------------------------------------------------- |
| je nach Name des RecordingJob-Token aus dem Onvif-Ereignis | bool | Für jeden bekannten Job wird eine passende Variable in Symcon erstellt. |

## 6. WebFront

Die Statusvariablen haben eine hinterlegte Standardaktion und somit können die Aufnahmen direkt  bedient werden. 

Die direkte Darstellung der Statusvariablen ist möglich; es wird aber empfohlen mit Links zu arbeiten.  

## 7. PHP-Funktionsreferenz

``` php
boolean ONVIF_SetRecordingJobMode(integer $InstanzID, string $Ident, bool $State)
```
Startet oder Beendet die in `$Ident` übergeben Aufnahme, je nach übergebenen Wert in `$Value`.  
Konnte der Befehl erfolgreich ausgeführt werden, wird `TRUE` zurückgegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `FALSE` zurückgegeben.  

Beispiel:
`ONVIF_SetRecordingJobMode(12345, 'JobStateRecJob_Cam1Rec0', false);`  
Beendet die Aufzeichnung `RecJob_Cam1Rec0`.

## 8. Aktionen

__Grundsätzlich können alle bedienbaren Statusvariablen als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) mit 'Auf Wert schalten' angesteuert werden, so das hier keine speziellen Aktionen benutzt werden müssen.__

Dennoch gibt es eine Aktion für die 'ONVIF Recording' Instanz.  
Wenn so eine Instanz als Ziel einer Aktion ausgewählt wurde, steht folgende Aktion zur Verfügung:  
![Aktionen](imgs/Actions.png)  
* Starten oder Stoppen einer Aufzeichnung    

## 9. Anhang

### 1. Changelog

[Changelog der Library](../README.md#2-changelog)

### 2. Spenden

  Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/donate?hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share) 

## 10. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
