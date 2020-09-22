[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.00-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-5.5%20%3E-green.svg)](https://www.symcon.de/forum/threads/41251-IP-Symcon-5-5-%28Testing%29)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions) [![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  

# ONVIF Profil S Library


## Vorbemerkungen zur Library

Diese Library wurde nicht dazu entwickelt komplett den Profil S Spezifikationen zu entsprechen oder deren gesamten Funktionsumfang abzubilden.  
Vielmehr liegt der Schwerpunkt auf eine einfache und unkomplizierte Integration bestimmter Bestandteile (LiveStream, Steuerung) und Funktionen (Events, Digital Ein-/Ausgänge) in Symcon.  
Dadurch ist es auch möglich Geräte in Symcon einzubinden welche ihrerseits die Spezifikationen nicht vollständig oder nicht korrekt umsetzen.  

## Vorbemerkungen zur Integration von Geräten  

Es werden Instanzen zum auffinden (Discovery) und einrichten (Konfigurator) von Geräten in Symcon bereitgestellt.  
Diese Instanzen werden nur korrekt funktionieren, wenn die betreffenden Geräte entsprechend Konfiguriert wurden.  
So gibt es Geräte bei welchen am Werk z.B. das ONVIF Protokoll deaktiviert ist.  
Oder eine entsprechende Zugangsberechtigung erstellt oder erweitert werden muss.  
Eine Konfiguration der Geräte über Symcon ist in dieser Library nicht vorgesehen.  
Unerlässlich ist eine korrekte Uhrzeit auf den Geräten, da eine Authentifizierung sonst fehlschlägt.  
Es wird dringend empfohlen vor der Integration in IPS folgende Parameter in den Geräten fertig zu konfigurieren und ggfls. zu testen:

- Netzwerk-Schnittstelle (IP-Adresse)  
- Auffindbarkeit / Discovery über ONVIF aktivieren  
- Zugangsdaten (u.U. eigene für ONVIF)  
	- Die Zugangsdaten sollten bei allen Geräten identisch sein.  
- Zeitsynchronisation  
	- Nach Möglichkeit sollten die Geräte und der Symcon Host die Uhrzeit aus der gleichen Quelle beziehen (NTP-Server).  
- PTZ-Vorpositionen / Szenen  (sofern vorhanden)  
- h26x-Profile bzw. Media-Profile für ONVIF  

## Hinweise zum Symcon-System / Host  

Die Maximale Anzahl der gleichzeitig verwendbaren RTSP-Streams hängt von der Symcon Lizenz ab. Bitte hierzu die [Funktionsübersicht der Editionen](https://www.symcon.de/produkt/editionen/) beachten.  

Um Ereignisse der Geräte in Symcon zu verarbeiten wird ein Webhook pro [IO-Modul](ONVIF%20IO) erzeugt.  
Hier wird aktuell nur der interne WebServer von Symcon auf Port 3777 unterstützt.  
Die IP-Adresse auf welchem Symcon die Daten empfängt wird automatisch ermittelt.  

Bei System mit aktiven NAT-Support funktioniert die automatische Erkennung der eigenen IP-Adresse nicht. Hier wird die PublicIP aus den Symcon-Spezialschaltern benutzt.  
Auch bei Systemen mit aktiven NAT-Support wird extern nur der Port 3777 unterstützt, und muss somit korrekt weitergeleitet werden.  
Damit Geräte über das [Discovery-Modul](ONVIF%20Discovery) gefunden werden können, müssen bei NAT Systemen Multicast-Pakete korrekt weitergeleitet werden.  
Für das Discovery werden Pakete über die Multicast-Adresse `239.255.255.250` auf Port `3702` gesendet und empfangen.

## Folgende Module beinhaltet die ONVIF Library:

- __ONVIF Discovery__ ([Dokumentation](ONVIF%20Discovery))
	Erkennt ONVIF kompatible Geräte innerhalb des lokalen LAN.

- __ONVIF Configurator__ ([Dokumentation](ONVIF%20Configurator))
	Unterstützt beim Einrichten der verschiedenen Instanzen für ein ONVIF-Gerät.

- __ONVIF IO__ ([Dokumentation](ONVIF%20IO))
	Stellt die Verbindung zu einem ONVIF-Gerät her.  

- __ONVIF Media Stream__ ([Dokumentation](ONVIF%20Media%20Stream))
	Konfiguriert ein IPS Medien-Objekt (RTSP-Stream) anhand der Geräte-Fähigkeiten.  

- __ONVIF Image Grabber__ ([Dokumentation](ONVIF%20Image%20Grabber))
	Lädt Snapshots (Standbilder) von dem Gerät und legt es in einem Media-Objekt ab.  

- __ONVIF Digital Input__ ([Dokumentation](ONVIF%20Digital%20Input))
	Bildet die Digitalen Eingänge in Symcon ab.  

- __ONVIF Digital Output__ ([Dokumentation](ONVIF%20Digital%20Output))
	Bildet Digitale Ausgänge (Relays) in Symcon ab.  

- __ONVIF Events__ ([Dokumentation](ONVIF%20Events))
	Bildet empfangbare ONVIF-Ereignisse in Symcon ab.  

---  


## Changelog

 Version 1.00:  
  - Release für Symcon 5.5  

## Spenden  
  
  Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

## Lizenz  

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
