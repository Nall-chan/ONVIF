[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.21-blue.svg)](https://community.symcon.de/t/modul-onvif-profil-s-fuer-ip-kameras-und-encoder/52036)
[![Version](https://img.shields.io/badge/Symcon%20Version-7.0%20%3E-green.svg)](https://www.symcon.de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)
[![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#2-spenden)[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# ONVIF IO  <!-- omit in toc -->
Stellt die Verbindung zu einem ONVIF-Gerät her.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
  - [Konfigurationsseite: Übersicht](#konfigurationsseite-übersicht)
  - [Konfigurationsseite: Ereignisse möglich](#konfigurationsseite-ereignisse-möglich)
  - [Konfigurationsseite: Ereignisse nicht möglich](#konfigurationsseite-ereignisse-nicht-möglich)
- [5. Statusvariablen](#5-statusvariablen)
- [6. WebFront](#6-webfront)
- [7. PHP-Funktionsreferenz](#7-php-funktionsreferenz)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

* Interface für die Kommunikation mit einem ONVIF Profil S und/oder Profil T kompatiblen Gerät.  
* Ereignisverwaltung für Geräte welche Events unterstützen.  

## 2. Voraussetzungen

* IP-Symcon ab Version 7.0
* Kameras oder Video-Encoder mit ONVIF Profil S und/oder Profil T Unterstützung.  

## 3. Software-Installation

* Dieses Modul ist Bestandteil der [ONVIF-Library](../README.md#3-software-installation).  

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF IO'-Modul unter dem Hersteller 'ONVIF' aufgeführt.  
![Module](../imgs/Module.png)  

 Diese Instanz wird automatisch angelegt, wenn im ['Discovery-Modul'](../ONVIF%20Discovery/README.md) ein Gerät in Symcon angelegt wird.  
 
 ### Konfigurationsseite: Übersicht

| Name                            | Text                             | Typ     | Beschreibung                                                           |
| ------------------------------- | -------------------------------- | ------- | ---------------------------------------------------------------------- |
| Open                            | Aktiv                            | bool    | Öffnet/Aktiviert die Verbindung zum Gerät                              |
| Address                         | Adresse                          | string  | URL von dem Gerät (z.B. http://192.168.1.111:8080)                     |
| Username                        | Benutzername                     | string  | Benutzername für die Anmeldung                                         |
| Password                        | Passwort                         | string  | Passwort zum Benutzernamen                                             |
| EventHandler                    | Ereignisse verarbeiten           | Bitmask | Bit0: Subscribe, Bit1: PullPoint                                       |
| WebHookIP                       | Experteneinstellung (Abonnieren) | string  | IP Adresse unter welcher IPS von dem Gerät aus erreichbar ist          |
| WebHookPort                     | Experteneinstellung (Abonnieren) | int     | Port unter welchem IPS von dem Gerät aus erreichbar ist (3777)         |
| WebHookHTTPS                    | Experteneinstellung (Abonnieren) | bool    | true wenn https benutzt werden soll                                    |
| SubscribeInitialTerminationTime | Experteneinstellung (Abonnieren) | int     | Erstes Timeout welches beim abonnieren angefragt wird                  |
| SubscribeEventTimeout           | Experteneinstellung (Abonnieren) | int     | Timeout bis wann das erste Ereignis eintreffen muss                    |
| PullPointInitialTerminationTime | Experteneinstellung (Abfragen)   | int     | Erstes Timeout welches beim abonnieren angefragt wird                  |
| PullPointTimeout                | Experteneinstellung (Abfragen)   | int     | Timeout bis wann das Gerät warten soll, bevor es die Verbindung trennt |
| MessageLimit                    | Experteneinstellung (Abfragen)   | int     | Maximal Anzahl von Ereignissen pro Abfrage                             |

![Config](imgs/Config2.png)  

Der Aktions-Bereich zeigt aktuelle Informationen zur Verbindung an.

Unter `Geräteinformationen` werden die gemeldeten Informationen und erkannten Fähigkeiten aufgeführt.  

### Konfigurationsseite: Ereignisse möglich  

Es gibt zwei verschiedene Arten der Ereignisverarbeitung welche vom IO unterstützt werden.  
Die bevorzugte Variante wird vom IO automatisch anhand der ermittelten Fähigkeiten der Geräte festgelegt.  

__Abonnieren__

Für Geräte welche das ONVIF Profile S unterstützen, wird der `Ereignis-Hook`, auf welchen Symcon die Nachrichten des Endgerätes empfängt angezeigt.  
Die IP-Adresse des `Ereignis-Hook` wird automatisch ermittelt, je nachdem über welchen Adresse das Gerät erreichbar ist.  
<span style="color:red">**Diese Erkennung funktioniert nicht bei NAT, da hier die externe Adresse Symcon nicht automatisch ermitteln kann.  
Es müssen die [Spezialschalter](https://www.symcon.de/service/dokumentation/entwicklerbereich/spezialschalter/) `NATSupport` und `NATPublicIP` benutzt werden**</span>  

Sollte es nötig sein, so können bei Bedarf die eigene IP und der Port, sowie die Verwendung von https anstatt http, in den  `Experteneinstellungen (Ereignisse abonnieren)` geändert und fixiert werden.

<span style="color:red">**Wird der übliche Port (3777) von Symcon nicht benutzt (z.B. Port forwarding) so kann hier auch der Port, unter welchen Symcon erreichbar ist, angepasst werden.**</span>  

---

__Abfragen__

Für Geräte welche das Profil S nicht unterstützen, gibt es außerdem noch die Möglichkeit die Ereignisse von dem Gerät abzufragen.  
Hierzu baut Symcon der IO eine Verbindung zum Gerät auf und wartet auf eine Antwort. Das Gerät sendet bis zum erreichen der Wartezeit ein auftretendes Ereignis als Antwort an Symcon.  
Anschließend baut Symcon die nächste Verbindung auf.  
<span style="color:red">**Bei dieser Art der Verarbeitung ist zu beachten, dass permanent ein PHP-Thread von der IO-Instanz belegt wird!**</span>  

---

__Allgemein__

Sofern das Gerät ONVIF-Ereignisse unterstützt und Symcon sich erfolgreich am Gerät angemeldet hat, wird eine Adresse unter  `Abonnementreferenz` angezeigt. 

In der Tabelle wird eine Liste aller vom Gerät gemeldeten Ereignissen angezeigt, welche sich in Symcon nutzen lassen. Über das Feld `Benutzt` wird angezeigt ob das Ereignis in einer Instanz konfiguriert wurde. Und über das Zahnrad einer Zeile werden diese Instanzen tabellarisch angezeigt.  

Die Fähigkeiten der Geräte werden beim Systemstart und anlegen von Instanzen ermittelt und innerhalb Symcon zwischengespeichert; da dieser Vorgang einige Zeit dauern kann.  
Wird das Gerät selber umkonfiguriert, z.B. Änderung der Stream/ONVIF-Profile, oder erhält z.B. ein Firmware-Update, so kann über den Button `Fähigkeiten neu laden` die Instanz veranlasst werden die Fähigkeiten neu zu laden.  

### Konfigurationsseite: Ereignisse nicht möglich  

![Config](imgs/Config3.png)  

Geräte welche beim ermitteln der Fähigkeiten von Ereignissen eine Fehlermeldung an Symcon melden, werden mit einem entsprechenden Hinweis dargestellt.  

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
