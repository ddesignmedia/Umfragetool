<?php
// config.php

// Geheimer Schlüssel zur Absicherung kritischer Aktionen (z.B. Löschen)
// WICHTIG: Ändern Sie diesen Wert in eine lange, zufällige Zeichenkette!
define('DELETE_TOKEN', '86199');

// Geheimer Schlüssel zur Absicherung der Umfragenerstellung
define('CREATE_TOKEN', '86199');

// Geheimer Schlüssel zum Pausieren von Umfragen
define('PAUSE_TOKEN', '86199');

// Rate-Limiting-Einstellungen
define('RATE_LIMIT_WINDOW', 15 * 60); // 15 Minuten in Sekunden
define('RATE_LIMIT_CREATE_SURVEY', 50); // Max. 50 Umfragen pro Fenster
define('RATE_LIMIT_SUBMIT_ANSWER', 10000); // 10.000 für intensive Tests/Große Klassen
