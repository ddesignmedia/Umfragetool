<?php
// config.php

// Geheimer Schlüssel zur Absicherung kritischer Aktionen (z.B. Löschen)
// WICHTIG: Ändern Sie diesen Wert in eine lange, zufällige Zeichenkette!
define('DELETE_TOKEN', 'your_secret_delete_token');

// Geheimer Schlüssel zur Absicherung der Umfragenerstellung
define('CREATE_TOKEN', 'your_secret_create_token');

// Geheimer Schlüssel zum Pausieren von Umfragen
define('PAUSE_TOKEN', 'your_secret_pause_token');

// Rate-Limiting-Einstellungen
define('RATE_LIMIT_WINDOW', 15 * 60); // 15 Minuten in Sekunden
define('RATE_LIMIT_CREATE_SURVEY', 5); // Max. 5 Umfragen pro Fenster
define('RATE_LIMIT_SUBMIT_ANSWER', 150); // Max. 150 Antworten pro Fenster
