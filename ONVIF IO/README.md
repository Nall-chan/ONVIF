# ONVIF IO
Stellt die Verbindung zu einem ONVIF-Gerät her.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Vorraussetzungen](#2-vorraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)

## 1. Funktionsumfang

* Interface für die Kommunikation mit einem ONVIF Profil S kompatiblen Gerät.  
* Ereignisverwaltung für Geräte welche Events unterstützen.  

## 2. Vorraussetzungen

* IP-Symcon ab Version 5.5
* Kameras oder Video-Encoder mit ONVIF Profil S Unterstützung.  

## 3. Software-Installation

* Über den Module Store das 'ONVIF'-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF IO'-Modul unter dem Hersteller 'ONVIF' aufgeführt.

 Diese Instanz wird automatisch angelegt, wenn im Discovery-Modul ein Gerät in Symcon angelegt wird.  
 
 __Konfigurationsseite__:

| Name       | Text         | Beschreibung                                                                                                            |
| ---------- | ------------ | ----------------------------------------------------------------------------------------------------------------------- |
| Open       | Aktiv        | Öffnet/Aktiviert die Verbindung zum Gerät.                                                                              |
| Address    | Adresse      | URL zum ONVIF Device-Service (z.B. http://192.168.1.111/onvif/device_service)                                           |
| Username   | Benutzername | Benutzername für die Anmeldung                                                                                          |
| Password   | Passwort     | Passwort zum Benutzernamen                                                                                              |
| NATAddress | NAT Adresse  | Nur bei Betrieb von Symcon hinter einem NAT, ist hier die Public-IP ggfls mit :Port einzutragen (z.B. 192.168.0.5:3777) |