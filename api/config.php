<?php
// config.php

// Geheimer Schlüssel zur Absicherung kritischer Aktionen (z.B. Löschen)
// WICHTIG: Ändern Sie diesen Wert in eine lange, zufällige Zeichenkette!
define('DELETE_TOKEN', '86199');

// Geheimer Schlüssel zur Absicherung der Umfragenerstellung
define('CREATE_TOKEN', '86199');

// Rate-Limiting-Einstellungen
define('RATE_LIMIT_WINDOW', 15 * 60); // 15 Minuten in Sekunden
define('RATE_LIMIT_CREATE_SURVEY', 5); // Max. 5 Umfragen pro Fenster
define('RATE_LIMIT_SUBMIT_ANSWER', 50); // Max. 50 Antworten pro Fenster
