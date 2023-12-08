[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.13-blue.svg)](https://community.symcon.de/t/modul-onvif-profil-s-fuer-ip-kameras-und-encoder/52036)
[![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-green.svg)](https://www.symcon.de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)
[![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#2-spenden)[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# ONVIF Configurator  <!-- omit in toc -->
Beschreibung des Moduls.

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
  - [Beispiel 1: Keine Digital IOs](#beispiel-1-keine-digital-ios)
  - [Beispiel 2: Mit Digital IOs](#beispiel-2-mit-digital-ios)
  - [Beispiel 3: Multikanal-Geräte](#beispiel-3-multikanal-geräte)
  - [Beispiel 4: Mit Events und mehreren Profilen](#beispiel-4-mit-events-und-mehreren-profilen)
- [5. Statusvariablen](#5-statusvariablen)
- [6. WebFront](#6-webfront)
- [7. PHP-Funktionsreferenz](#7-php-funktionsreferenz)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

* Unterstützt beim Einrichten der verschiedenen Instanzen für ein ONVIF-Gerät.  

## 2. Voraussetzungen

* IP-Symcon ab Version 6.1
* Kameras oder Video-Encoder mit ONVIF Profil S und/oder Profil T Unterstützung.  

## 3. Software-Installation

* Dieses Modul ist Bestandteil der [ONVIF-Library](../README.md#3-software-installation).    

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF Configurator'-Modul unter dem Hersteller 'ONVIF' aufgeführt.  
 ![Module](../imgs/Module.png)  
 Es wird empfohlen, die Instanzen über das [ONVIF Discovery'-Modul](../ONVIF%20Discovery/README.md) einzurichten.  

 Der Konfigurator ermöglicht es folgende Instanzen einfach zu erstellen und fertig zu konfigurieren:

- ONVIF Media Stream ([Dokumentation](../ONVIF%20Media%20Stream/README.md))
  - Pro Videoquelle wird erneut ein Stream zum erstellen angeboten, wie es noch ungenutzte Profile des Kanals gibt. (nach aktualisieren der Ansicht)  
- ONVIF Image Grabber ([Dokumentation](../ONVIF%20Image%20Grabber/README.md))
  - Pro Videoquelle wird erneut ein Stream zum erstellen angeboten, wie es noch ungenutzte Profile des Kanals gibt. (nach aktualisieren der Ansicht)  
- ONVIF Digital Input ([Dokumentation](../ONVIF%20Digital%20Input/README.md))
- ONVIF Digital Output ([Dokumentation](../ONVIF%20Digital%20Output/README.md))
- ONVIF Events ([Dokumentation](../ONVIF%20Events/README.md))

### Beispiel 1: Keine Digital IOs   
![Config](imgs/Config1.png)  
Hier wird als Beispiel ein Konfigurator eines Gerätes dargestellt, welche nur einen Videoeingang (Videosignal / Videoquelle) hat und über keine Digital I/O's verfügt.  

### Beispiel 2: Mit Digital IOs
![Config](imgs/Config2.png)  
Dieses Gerät hat ebenfalls nur einen Videoeingang (Videosignal / Videoquelle), verfügt aber über Digitale Ein- und Ausgänge.  

### Beispiel 3: Multikanal-Geräte
![Config](imgs/Config3.png)
Dieses Gerät stellt 5 Videosignale (Videoquellen) bereit.  
In diesem Fall sind es 4 Videoeingänge und ein Quad-Bild aller 4 Videoeingänge.

### Beispiel 4: Mit Events und mehreren Profilen
![Config](imgs/Config4.png)
Dieses Gerät stellt 3 Parent Topics für Events bereit.  
In diesem Fall sind es `RecordingConfig`, `Media` und `IVA` (=> Intelligent Video Analytics).  
Außerdem werden pro Videosignal mehr als ein Profil angeboten.  
Hier ist die Stream-Instanz für das `HD Bit Rate`-Profil schon vorhanden und der Konfigurator bietet jetzt zusätzlich noch `Image optimized` als weiteres Profil für eine Instanz an.  

## 5. Statusvariablen

Dieses Modul erzeugt keine Statusvariablen.  

## 6. WebFront

Dieses Modul ist nicht für die Darstellung im Webfront geeignet.  

## 7. PHP-Funktionsreferenz

Keine Funktionen verfügbar. 

## 8. Aktionen

Keine Aktionen verfügbar.

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
