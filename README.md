[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.00-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-5.5%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-3-%28Stable%29-Changelog)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/ONVIF/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/ONVIF/actions) [![Run Tests](https://github.com/Nall-chan/ONVIF/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/ONVIF/actions)  

# ONVIF Profil S Library


## Vorbemerkungen zur Library

Diese Library wurde nicht dazu entwickelt komplett den Profil S Spezifikationen zu entsprechen oder deren gesamten Funktionsumfang abzubilden.  
Vielmehr liegt der Schwerpunkt auf eine einfache und unkomplizierte Integration bestimmter Bestandteile und Funktionen in Symcon.  
Dadurch ist es auch möglich Geräte in Symcon einzubinden welche ihrerseits die Spezifikationen nicht vollständig oder nicht korrekt umsetzen.  

## Vorbemerkungen zur Integration von Geräten  

Es werden Instanzen zum auffinden (Discovery) und einrichten (Konfigurator) von Geräten in Symcon bereitgestellt.  
Diese Instanzen werden nur korrekt funktionieren, wenn die betreffenden Geräte entsprechend Konfiguriert wurden.  
So gibt es Geräte bei welchen am Werk z.B. das ONVIF Protokoll deaktiviert ist.  
Oder eine entsprechende Zugangsberechtigung erstellt oder erweitert werden muss.  
Eine Konfiguration der Geräte über Symcon ist in dieser Library nicht vorgesehen.  
Unerlässlich ist eine korrekte Uhrzeit auf den Geräten, es wird dringend empfohlen vor der Integration in IPS folgende Parameter in den Geräten fertig zu konfigurieren und ggfls. zu testen:

- Netzwerk-Schnittstelle (IP-Adresse)  
- Auffindbarkeit / Discovery über ONVIF aktivieren  
- Zugangsdaten (u.U. eigene für ONVIF)  
- Zeitsynchronisation  
- PTZ-Vorpositionen / Szenen  (sofern vorhanden)  
- h26x-Profile bzw. Media-Profile für ONVIF

## Folgende Module beinhaltet die ONVIF Library:

- __ONVIF Discovery__ ([Dokumentation](ONVIF%20Discovery))
	Erkennt ONVIF kompatible Geräte innerhalb des lokalen LAN.

- __ONVIF Configurator__ ([Dokumentation](ONVIF%20Configurator))
	Unterstützt beim Einrichten der verschiedenen Instanzen für ein ONVIF-Gerät.

- __ONVIF IO__ ([Dokumentation](ONVIF%20IO))
	Stellt die Verbindung zu einem ONVIF-Gerät her.  

- __ONVIF Media Stream__ ([Dokumentation](ONVIF%20Media%20Stream))
	Konfiguriert ein IPS Medien-Objekt anhand der Geräte-Fähigkeiten.  

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
