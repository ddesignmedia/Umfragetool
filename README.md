# 📊 Live-Umfragetool & Quiz

> Ein schlankes, datenschutzfreundliches und interaktives Echtzeit-Umfrage- und Quiz-System für Schule, Unterricht, Lehrerfortbildungen und Workshops.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/Vanilla_JS-F7DF1E?style=flat-square&logo=javascript&logoColor=black)
![Database](https://img.shields.io/badge/Database-Zero_Config_(JSON)-orange?style=flat-square)
![DSGVO](https://img.shields.io/badge/DSGVO-100%25_konform-success?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)

---

## 📌 Übersicht

Das **Umfragetool** ermöglicht es Lehrkräften, Referenten und Moderatoren, innerhalb von Sekunden interaktive Live-Umfragen oder Wissensquizze zu starten. 

Die Teilnehmenden benötigen **weder App noch Benutzerkonto**: Sie scannen einfach den vom Beamer projizierten **QR-Code** mit dem Smartphone oder rufen den Kurzlink auf und können direkt abstimmen. Die Ergebnisse erscheinen in **Echtzeit** an der Wand.

---

## ✨ Kern-Features

### 1. 📝 Flexible Fragetypen & Quiz-Modus
- **Single-Choice:** Klassische Einzelauswahl mit Radio-Buttons.
- **Multiple-Choice:** Mehrfachauswahl mit Checkboxen.
- **Sterne-Skala (Rating):** Schnelles Stimmungs- oder Zufriedenheits-Feedback (1–5 Sterne).
- **Offener Freitext:** Qualitative Antworten, Ideen und Anregungen in übersichtlicher Kachel-/Kartenansicht.
- **🏆 Quiz-Funktion:** Festlegung der korrekten Antwort(en) mit automatischer Punkteberechnung und Live-Auflösung.

### 2. 🎮 Highscore & Gamification
- Optionaler **Highscore-Modus** für kompetitive Unterrichtsquizze.
- Automatische Rangliste basierend auf **Punktestand und benötigter Antwortzeit**.
- Podest-Badges (🥇 Gold, 🥈 Silber, 🥉 Bronze) und dynamische Gamification-Titel (*„Quiz-Master“*, etc.).

### 3. 📱 Beamer-Präsentation & QR-Code
- Automatischer **Live-QR-Code**, der direkt für den Beamer groß angezeigt wird.
- **One-Click Link-Kopie** zur Weitergabe im Chat oder auf Lernplattformen (mebis, ByCS, Teams, Moodle).
- Live-Aktualisierung der Balken und Antworten ohne manuelles Neuladen der Seite (Polling).

### 4. 🤖 Vorlagen-System & KI-Prompt-Integration
- **Integrierte Vorlagen:** Fertige Sets für Stunden-Feedback, Fortbildungen, Tagesrückblicke und Einstiegsabfragen.
- **JSON-Import / Export:** Eigene Umfragen speichern, sichern und jederzeit wiederverwenden.
- **✨ „KI-Prompt kopieren“-Button:** Kopiert eine fertige Promotanweisung für KI-Modelle (ChatGPT, Claude, Gemini), um sich in Sekunden maßgeschneiderte Umfrage-JSONs für jedes beliebige Fachthema generieren zu lassen.

### 5. 📄 PDF-Export & Verwaltung
- **PDF-Protokoll:** Exportiert die gesamten Umfrageergebnisse samt Diagrammen und Textantworten mit einem Klick als sauberes Dokument (`jspdf` + `html2canvas`).
- **Live-Steuerung:** Umfragen können live pausiert (`⏸️`), wieder aktiviert (`▶️`), dupliziert (`📋`) oder beendet & gelöscht (`🗑️`) werden.

### 6. 🔒 100% DSGVO-konform & Zero-Config Backend
- **Keine SQL-Datenbank erforderlich:** Verwendet ein sicheres, dateibasiertes JSON-Speichersystem (`api/data/`). Läuft auf nahezu jedem Webspace mit PHP.
- **Keine Tracker, keine Cookies von Drittanbietern, keine externen CDNs:** Alle JavaScript-Bibliotheken (QRCode, jsPDF, html2canvas) sind lokal gebündelt.
- **Concurrency & Spam-Schutz:** Sicheres Dateisperren (`.lock`) gegen gleichzeitige Schreibkonflikte und integriertes **Rate-Limiting** (`rate_limiter.php`).

---

## 🚀 Installation & Bereitstellung

Das Umfragetool benötigt keine Datenbank-Installation (kein MySQL/MariaDB nötig):

1. **Dateien auf den Webserver laden:**
   Laden Sie den Ordner (z. B. nach `/umfrage/`) auf Ihren PHP-fähigen Webspace (Apache, Nginx, LiteSpeed ab PHP 7.4).
2. **Schreibrechte für das Datenverzeichnis setzen:**
   Stellen Sie sicher, dass der Webserver im Ordner `api/data/` Schreibrechte besitzt:
   ```bash
   chmod 775 api/data
   ```
3. **Sicherheitstoken anpassen (empfohlen):**
   Öffnen Sie `api/config.php` und ändern Sie die Standard-Tokens für administrative Aktionen:
   ```php
   define('CREATE_TOKEN', 'IHR_GEHEIMER_SCHLUESSEL');
   define('DELETE_TOKEN', 'IHR_GEHEIMER_SCHLUESSEL');
   define('PAUSE_TOKEN',  'IHR_GEHEIMER_SCHLUESSEL');
   ```
4. **Fertig!** Rufen Sie `https://IhreDomain.de/umfrage/` im Browser auf.

---

## 📁 Projektstruktur

```text
├── index.html                   # Single-Page-App (Erstellung, Durchführung, Live-Ergebnisse)
├── css/
│   └── style.css                # Responsives Design für Smartphone & Beamer
├── js/
│   └── libs/
│       ├── qrcode.min.js        # Lokale QR-Code-Generierung
│       ├── jspdf.umd.min.js     # PDF-Dokumenterstellung
│       └── html2canvas.min.js   # Canvas-Rendering für PDF-Export
├── vorlagen/                    # Vordefinierte Umfrage-Templates (JSON)
│   ├── Stunden-Feedback.json
│   ├── Umfrage Fortbildung.json
│   ├── DSS Tagesumfrage.json
│   └── ...
├── api/                         # Schlankes PHP-Backend (REST-Endpoints)
│   ├── config.php               # Sicherheitstoken & Rate-Limiting-Konfiguration
│   ├── rate_limiter.php         # Schutz vor Spam & Überlastung
│   ├── create_survey.php        # Erstellt neue Umfragen
│   ├── get_survey.php           # Liefert Umfragedaten für Teilnehmer
│   ├── submit_answer.php        # Speichert Antworten threadsicher
│   ├── get_results.php          # Liefert aggregierte Live-Ergebnisse
│   ├── list_active_surveys.php  # Übersicht aller aktiven Sessions
│   ├── pause_survey.php         # Pausiert/Reaktiviert Umfragen
│   ├── delete_survey.php        # Löscht Umfragedaten
│   └── data/                    # Speicherort für aktive JSON-Umfragen (.gitignored)
│       └── .gitkeep
├── LICENSE                      # MIT-Lizenz
└── README.md                    # Dokumentation
```

---

## 📄 Lizenz

Dieses Projekt ist unter der [MIT-Lizenz](LICENSE) lizenziert – freie Nutzung für Schulen, Lehrkräfte, Hochschulen und Bildungsreinrichtungen.
