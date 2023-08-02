[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.10-blue.svg)](https://community.symcon.de/t/modul-onvif-profil-s-fuer-ip-kameras-und-encoder/52036)
[![Version](https://img.shields.io/badge/Symcon%20Version-7.0%20%3E-green.svg)](https://www.symcon.de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)
[![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#3-spenden)  

# ONVIF Image Grabber <!-- omit in toc -->
Speichert einzelne Snapshots (Standbilder) als ein IPS Medien-Objekt.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen](#5-statusvariablen)
  - [Keine Events:](#keine-events)
  - [Mit Events:](#mit-events)
- [6. WebFront](#6-webfront)
- [7. PHP-Funktionsreferenz](#7-php-funktionsreferenz)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Tips & Tricks](#1-tips--tricks)
    - [__Bild bei Bewegung aktualisieren__](#bild-bei-bewegung-aktualisieren)
  - [2. Changelog](#2-changelog)
  - [3. Spenden](#3-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

* Instanz für die einfache Integration eines Media-Bild-Objektes innerhalb von Symcon.  

## 2. Voraussetzungen

* IP-Symcon ab Version 7.0
* Kameras oder Video-Encoder mit ONVIF Profil S und/oder Profil T Unterstützung.

## 3. Software-Installation

* Dieses Modul ist Bestandteil der [ONVIF-Library](../README.md#3-software-installation).    

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF Image Grabber'-Modul unter dem Hersteller 'ONVIF' aufgeführt.
![Module](../imgs/Module.png)  

 Es wird empfohlen diese Instanz über die dazugehörige Instanz des [Configurator-Moduls](../ONVIF%20Configurator/README.md) von diesem Geräte anzulegen.  
 
__Konfigurationsseite__:

![Config](imgs/Config.png)  

| Name        | Text                       | Beschreibung                                                                                                  |
| ----------- | -------------------------- | ------------------------------------------------------------------------------------------------------------- |
| VideoSource | Videoquelle                | Auswahl der Videoquelle                                                                                       |
| Profile     | Stream-Profil              | Auswahl des Profils                                                                                           |
| Intervall   | Interval                   | Intervall in Sekunden wann das Bild neu geladen werden soll.                                                  |
| UseCaching  | Benutze In-Memory Cache    | Speichert die Bilder im RAM des System und schreibt sie nur beim beenden des Dienstes auf das Speichermedium. |
| EventTopic  | Ereignisse der Videoquelle | Auswahl des Ereignis-Pfad ab welchen Ereignisse empfangen und verarbeitet werden (*).                         |

(*)  _Durch eine Änderung des Ereignis-Pfad werden die alten Statusvariablen hinfällig und müssen manuell gelöscht werden._   

## 5. Statusvariablen

Es wird automatisch ein Media-Objekt vom Typ Bild angelegt.  
Weitere Statusvariablen, basierend auf den Ereignissen, werden automatisch angelegt.  

### Keine Events:  
![Tree](imgs/Tree1.png)  

### Mit Events:  
![Tree](imgs/Tree2.png)  

| Name    | Typ      | Beschreibung                                                                                |
| ------- | -------- | ------------------------------------------------------------------------------------------- |
| Image   | Media    | IPS-Medienobjekt Typ Bild mit dem Snapshot.                                                 |
| diverse | variable | Für jedes eintreffende Ereignis wird automatisch eine passende Variable in Symcon erstellt. |

Beispiele für Statusvariablen von Ereignisse (`EventTopics`) sind in der [Events-Instanz](../ONVIF%20Events/README.md#5-statusvariablen) zu finden.
Es ist zu beachten das die Image-Grabber Instanz Event-Quellen auf Basis der konfigurierten Videoquelle (`VideoSource`) filtert. Somit werden z.B. Signalverlust (`VideoLost`) Events mit Bezug auf eine Videoquelle auch in der richtigen Instanz verarbeitet.  

## 6. WebFront

Die direkte Darstellung des Medien-Objektes und der eventuellen Statusvariablen von Ereignissen ist möglich; es wird aber empfohlen mit Links zu arbeiten.  

## 7. PHP-Funktionsreferenz

``` php
boolean ONVIF_UpdateImage(integer $InstanzID)
```
Holt ein neues Bild vom dem Gerät und speichert es im Medien-Objekt.  
Im Fehlerfall wird eine Warnung erzeugt und `false` zurück gegeben, sonst `true`.

## 8. Aktionen

Wenn eine 'ONVIF Image Grabber' Instanz als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) ausgewählt wurde, steht folgende Aktion zur Verfügung:  

![Aktionen](imgs/Actions.png)  
* Bild von der Kamera aktualisieren  


## 9. Anhang

### 1. Tips & Tricks

#### __Bild bei Bewegung aktualisieren__  
 
Es soll ein Bild geladen werden, sobald der Videosensor auslöst.  
 
Hierzu ist unter `Ereignisse der Videoquelle` das Topic des Videosensor ausgewählt worden.
Die Variable trägt in diesem Beispiel den Namen `State`.  

Es wird ein neues `auslösendes Ereignis` in Symcon erstellt.
Als `Auslösende Variable` wird die `State` Variable des Videosensors ausgewählt.  
Bei `Auslöser` wird `bestimmter Wert` und bei `Wert` wird `True` eingetragen.
Damit eine wiederholte Auslösung des Videosensors auch ein neues Bild lädt, wird bei `Nachfolgende Ereignisse ausführen` auch `...wiederholt erfüllte Bedingung` ausgewählt.  
![Event-Quelle](imgs/Event1.png)
Das Ereignis muss jetzt noch eine Aktion erhalten, damit es das laden eines neuen Bildes vom Gerät anstoßen kann.  
![Event-Ziel](imgs/Event2.png)
Also `Ziel` wird die Instanz des `ONVIF Image Grabber` gewählt, welcher das Bild aktualisieren soll.  
Als `Aktion` wird unter `Zielspezifisch` auf `Bild von der Kamera aktualisieren` ausgewählt und über `OK` wird das Ereignis gespeichert.  

### 2. Changelog

[Changelog der Library](../README.md#2-changelog)

### 3. Spenden

Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

  PayPal:  
<a href="https://www.paypal.com/donate?hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>  

  Wunschliste:  
<a href="https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share" target="_blank"><img src="https://upload.wikimedia.org/wikipedia/commons/4/4a/Amazon_icon.svg" border="0" width="100"/></a>  

## 10. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
