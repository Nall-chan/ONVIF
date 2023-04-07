[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.00-blue.svg)](https://community.symcon.de/t/modul-onvif-profil-s-fuer-ip-kameras-und-encoder/52036)
[![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-green.svg)](https://www.symcon.de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)
[![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#6-spenden)  

# ONVIF Profil S & T Library <!-- omit in toc -->

Einbinden von ONVIF kompatiblen Geräten in IPS.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Vorbemerkungen](#1-vorbemerkungen)
	- [Zur Library](#zur-library)
	- [Zur Integration von Geräten](#zur-integration-von-geräten)
	- [Hinweise zum Symcon-System / Host](#hinweise-zum-symcon-system--host)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Enthaltende Module](#4-enthaltende-module)
- [5. Anhang](#5-anhang)
	- [1. GUID der Module](#1-guid-der-module)
	- [2. Changelog](#2-changelog)
	- [3. Spenden](#3-spenden)
- [6. Lizenz](#6-lizenz)

----------
## 1. Vorbemerkungen

### Zur Library
Diese Library wurde nicht dazu entwickelt komplett den Profil S & T Spezifikationen zu entsprechen oder deren gesamten Funktionsumfang abzubilden.  
Vielmehr liegt der Schwerpunkt auf eine einfache und unkomplizierte Integration bestimmter Bestandteile (LiveStream, Steuerung) und Funktionen (Events, Digital Ein-/Ausgänge) in Symcon.  
Dadurch ist es auch möglich Geräte in Symcon einzubinden welche ihrerseits die Spezifikationen nicht vollständig oder nicht korrekt umsetzen.  
Dennoch wird geprüft ob Geräte sich nicht an verpflichtende Funktionen halten und diese als Popup in der Konfiguration der IO-Instanzen gemeldet.  
Dies ist kein Fehler, sondern ein beabsichtigtes Verhalten.  

----------
### Zur Integration von Geräten  

Es werden Instanzen zum auffinden (Discovery) und einrichten (Konfigurator) von Geräten in Symcon bereitgestellt.  
Diese Instanzen werden nur korrekt funktionieren, wenn die betreffenden Geräte entsprechend Konfiguriert wurden.  
So gibt es Geräte bei welchen am Werk z.B. das ONVIF Protokoll deaktiviert ist.  
Oder wo eine entsprechende Zugangsberechtigung erstellt bzw. erweitert werden muss.  
Eine Konfiguration der Geräte über Symcon ist in dieser Library aktuell nicht vorgesehen.  
Unerlässlich ist eine korrekte Uhrzeit auf den Geräten, da eine Authentifizierung sonst fehlschlägt.  
Es wird dringend empfohlen vor der Integration in IPS folgende Parameter in den Geräten fertig zu konfigurieren und ggfls. zu testen:

- Netzwerk-Schnittstelle (IP-Adresse)  
- Auffindbarkeit / Discovery über ONVIF aktivieren  
- Zugangsdaten (u.U. eigene für ONVIF)  
	- Die Zugangsdaten sollten bei allen Geräten identisch sein.  
- Zeitsynchronisation  
	- Nach Möglichkeit sollten die Geräte und der Symcon Host die Uhrzeit aus der gleichen Quelle beziehen (NTP-Server).  
- PTZ-Vorpositionen / Szenen  (sofern vorhanden)  
- h264-Profile bzw. Media-Profile für ONVIF  
- Sinnvolle Namen der Videoquellen und der Media-Profile, sofern die Geräte das umbenennen unterstützen.  

----------
### Hinweise zum Symcon-System / Host  

Die Maximale Anzahl der gleichzeitig verwendbaren RTSP-Streams hängt von der Symcon Lizenz ab. Bitte hierzu die [Funktionsübersicht der Editionen](https://www.symcon.de/produkt/editionen/) beachten.  

----------
<span style="color:red">**Folgendes gilt nicht für reine Profil T Geräte:**</span>  

Um Ereignisse der Geräte in Symcon zu verarbeiten wird ein Webhook pro [IO-Modul](ONVIF%20IO/README.md) erzeugt.  
Hier wird beim anlegen der Instanz automatisch nur der interne WebServer von Symcon auf Port 3777 eingetragen.
Die IP-Adresse auf welchem Symcon die Daten empfängt wird automatisch ermittelt.

Bei System mit aktiven NAT-Support funktioniert die automatische Erkennung der eigenen IP-Adresse nicht. __Hier wird automatisch die NATPublicIP aus den [Symcon-Spezialschaltern](https://www.symcon.de/service/dokumentation/entwicklerbereich/spezialschalter/) benutzt.__  
<span style="color:red">**Auch bei Systemen mit aktiven NAT-Support wird extern automatisch nur der Port 3777 beim anlegen von IO-Instanzen unterstützt.**</span>  
  
Sollte es nötig sein, so können bei Bedarf die eigene IP und der Port, sowie die Verwendung von https,  in den IO-Instanzen unter `Experteneinstellungen` geändert und fixiert werden.

----------
Damit Geräte über das [Discovery-Modul](ONVIF%20Discovery/README.md) gefunden werden können, müssen bei in gerouteten Netzen und bei NAT Systemen Multicast-Pakete korrekt weitergeleitet werden.  
<span style="color:red">**Discovery funktioniert nicht in einem Docker Container welcher per NAT angebunden ist. Diese Konstellation wird aufgrund der fehlenden Multicast Fähigkeiten von Docker nicht unterstützt.**</span>  
Für das Discovery werden Pakete über die Multicast-Adresse `239.255.255.250` auf Port `3702` gesendet und auf UDP Port `3703` empfangen.  

----------
## 2. Voraussetzungen

* IP-Symcon ab Version 6.1
* Kameras oder Video-Encoder mit ONVIF Profil S und/oder Profil T Unterstützung.
 
 ## 3. Software-Installation
  
  Über den 'Module-Store' in IPS das Modul 'ONVIF' hinzufügen.  
   **Bei kommerzieller Nutzung (z.B. als Errichter oder Integrator) wenden Sie sich bitte an den Autor.**  
![Module-Store](imgs/install.png) 

  ## 4. Enthaltende Module

- __ONVIF Discovery__ ([Dokumentation](ONVIF%20Discovery/README.md))  
	Erkennt ONVIF kompatible Geräte innerhalb des lokalen LAN.  
	<span style="color:red">**Funktioniert nicht in einem Docker Container welcher per NAT angebunden ist**</span>  
 
- __ONVIF Configurator__ ([Dokumentation](ONVIF%20Configurator/README.md))  
	Unterstützt beim Einrichten der verschiedenen Instanzen für ein ONVIF-Gerät.

- __ONVIF IO__ ([Dokumentation](ONVIF%20IO/README.md))  
	Stellt die Verbindung zu einem ONVIF-Gerät her.  

- __ONVIF Media Stream__ ([Dokumentation](ONVIF%20Media%20Stream/README.md))  
	Konfiguriert ein IPS Medien-Objekt (RTSP-Stream) anhand der Geräte-Fähigkeiten.  

- __ONVIF Image Grabber__ ([Dokumentation](ONVIF%20Image%20Grabber/README.md))  
	Lädt Snapshots (Standbilder) von dem Gerät und legt es in einem Media-Objekt ab.  

- __ONVIF Digital Input__ ([Dokumentation](ONVIF%20Digital%20Input/README.md))  
	Bildet die Digitalen Eingänge in Symcon ab.  

- __ONVIF Digital Output__ ([Dokumentation](ONVIF%20Digital%20Output/README.md))  
	Bildet Digitale Ausgänge (Relays) in Symcon ab.  

- __ONVIF Events__ ([Dokumentation](ONVIF%20Events/README.md))  
	Bildet empfangbare ONVIF-Ereignisse in Symcon ab.  


## 5. Anhang

###  1. GUID der Module

 
|        Modul        |     Typ      | Prefix |                  GUID                  |
| :-----------------: | :----------: | :----: | :------------------------------------: |
|   ONVIF Discovery   |  Discovery   | ONVIF  | {3E7839DC-5CC9-30A0-F48A-58DF2339EADD} |
| ONVIF Configurator  | Konfigurator | ONVIF  | {C6A79C49-19D5-8D45-FFE5-5D77165FAEE6} |
|      ONVIF IO       |      IO      | ONVIF  | {F40CA9A7-3B4D-4B26-7214-3A94B6074DFB} |
| ONVIF Media Stream  |    Gerät     | ONVIF  | {FA889450-38B6-7E20-D4DC-F2C6D0B074FB} |
| ONVIF Image Grabber |    Gerät     | ONVIF  | {18EA97C1-3CEC-80B7-4CAA-D91F8A2A0599} |
|    ONVIF Events     |    Gerät     | ONVIF  | {62584C2E-4542-4EBF-1E92-299F4CF364E4} |
|     ONVIF Input     |    Gerät     | ONVIF  | {73097230-1ECC-FEEB-5969-C85148DFA76E} |
|    ONVIF Output     |    Gerät     | ONVIF  | {A44B3114-1F72-1FD1-96FB-D7E970BD8614} |


----------
### 2. Changelog

Version 2.00:  
- Verbindungsaufbau des IO überarbeitet.  
- Wechsel von PHP-Streams auf CURL zur Unterstützung von HTTP digest Authentifizierung.  
- Unterstützung von ONVIF T Profil.  
- Damit einher gehende Unterstützung von PullMessages für reine Profil T Geräte.  
- Unterstützung der Rule & Analytics Funktionen für die Events.  
- IO Instanz zeigt Geräte-Informationen und dessen erkannte Fähigkeiten an.  
- Die Events in der IO-Instanz zeigen alle gemeldeten Quellen und Daten der Events an.  
- Auswertung von allen Quellen und allen Daten der Events. (Achtung, hierdurch können neue Statusvariablen angelegt werden und alte ungültig werden!)  
- Konfigurator bietet jetzt von allen Events das Parent-Topics zum Erstellen einer Events-Instanz an.  
- Verbesserte Erkennung von Relais und Digitalen Eingängen.  
- Übersetzungen ergänzt / verbessert.  

Version 1.23:  
- Image im Testbereich des ImageGrabber wurde nicht aktualisiert.  

Version 1.20:  
- Fehlermeldung in der Discovery Instanz bei ungültiger Anmeldung wird durch bestätigen mit 'Ignorieren' nicht mehr angezeigt, bis die Anmeldedaten geändert wurden.  
- Es wird eine Meldung angezeigt, wenn die Discovery Instanz nicht funktioniert (Docker + NAT).  
- Experteneinstellungen in den IO-Instanzen ermöglichen das umstellen auf http/https und ändern der IP und Port vom Ereignis-Hook.  
- Aktion für Digital Output war defekt.
  
Version 1.10:  
- Beta Release für Symcon 6.0  
- Aktionen für Kamerasteuerung, Snapshot des Image Grabber und für Ansteuerung der Ausgänge.  

Version 1.08:  
- Fehlermeldungen vom Image Grabber, wenn IO nicht verbunden war.  

Version 1.07:  
- Die Ansteuerung der PTZ-Kommandos kann invertiert werden.  
- Eventuelle Fehlermeldung wenn die Option `Variablenprofil benutzt Namen der Szenen` aktiviert war.  

Version 1.06:
- Eventuell wurden die Topics eines Events falsch ermittelt.  
- Statusvariablen für Topics mit Sonderzeichen wurden nicht korrekt angelegt.  

Version 1.05:  
- Fehlermeldung wenn Geräte keinen Namen für PTZ-Szenen geliefert haben.  
- Fehlermeldung im IO wenn Geräte keine Auflösung, kein Encoding oder keine Bitrate gemeldet haben.  

Version 1.02:  
- ONVIF_StopPTZ und das Anhalten beim loslassen der PTZ-Overlay Steuerung hat bei einigen Geräten nicht funktioniert  
- Profile ONVIF.Time und ONVIF.Speed waren bei der Beschreibung vertauscht  
- Fehlende Übersetzungen ergänzt  

Version 1.01:  
- Release für Symcon 5.5  
- Fehlende Übersetzungen ergänzt  

Version 1.00:  
- Beta Release für Symcon 5.5  

----------
### 3. Spenden  
  
  Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

  PayPal:  
<a href="https://www.paypal.com/donate?hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>  

  Wunschliste:  
<a href="https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share" target="_blank"><img src="https://upload.wikimedia.org/wikipedia/commons/4/4a/Amazon_icon.svg" border="0" width="100"/></a>  

## 6. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
 
