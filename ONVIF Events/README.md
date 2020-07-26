# ONVIF Events
Bildet verschiedene Ereignisse (Events) als Statusvariablen in Symcon ab.

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Vorraussetzungen](#2-vorraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
  - [Statusvariablen](#statusvariablen)

## 1. Funktionsumfang

* Empfang von Statusmeldungen von einem ONVIF-Gerät.  

## 2. Vorraussetzungen

* IP-Symcon ab Version 5.3  
* Kameras oder Video-Encoder mit ONVIF Profil S Unterstützung.
* Geräte müssen ONVIF-Events unterstützen.  

## 3. Software-Installation

* Über den Module Store das 'ONVIF'-Modul installieren.  

## 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' ist das 'ONVIF Events'-Modul unter dem Hersteller 'ONVIF' aufgeführt.

__Konfigurationsseite__:

| Name       | Text          | Beschreibung                                                                      |
| ---------- | ------------- | --------------------------------------------------------------------------------- |
| EventTopic | Ereignis-Pfad | Auswahl des Ereignis-Pfad ab welchen Ereignisse empfangen und verarbeitet werden. |

## 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

### Statusvariablen

| Name                                           | Typ      | Beschreibung                                                                                         |
| ---------------------------------------------- | -------- | ---------------------------------------------------------------------------------------------------- |
| je nach Name des Events aus dem Onvif-Ereignis | variabel | Für jedes Ereignis welches einen Wert liefern kann, wird eine passende Variable in Symcon erstellt. |
