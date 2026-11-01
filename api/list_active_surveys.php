<?php
header('Content-Type: application/json');

$dataDir = __DIR__ . '/data/';
$activeSurveys = [];

// Überprüfe, ob das Datenverzeichnis existiert
if (!is_dir($dataDir)) {
    echo json_encode(['error' => 'Data directory not found.']);
    exit;
}

// Lese alle Dateien im Verzeichnis
$files = scandir($dataDir);

foreach ($files as $file) {
    // Verarbeite nur 'survey_...json' Dateien
    if (strpos($file, 'survey_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
        $filePath = $dataDir . $file;
        // Extrahiere die Survey-ID aus dem Dateinamen
        $surveyId = str_replace(['survey_', '.json'], '', $file);

        // Lese den Inhalt der Datei, um den Titel zu bekommen
        $content = file_get_contents($filePath);
        $surveyData = json_decode($content, true);

        // Füge die Umfrage zur Liste hinzu, wenn der Titel vorhanden ist
        if (isset($surveyData['title'])) {
            $activeSurveys[] = [
                'id' => $surveyId,
                'title' => $surveyData['title'],
                'paused' => isset($surveyData['paused']) ? $surveyData['paused'] : false,
                'mtime' => filemtime($filePath) // Zeitstempel für Sortierung
            ];
        }
    }
}

// Sortieren: Neueste zuerst (mtime absteigend)
usort($activeSurveys, function($a, $b) {
    return $b['mtime'] <=> $a['mtime'];
});

// Zeitstempel wieder entfernen, damit das JSON sauber bleibt
foreach ($activeSurveys as &$s) {
    unset($s['mtime']);
}
unset($s);

echo json_encode($activeSurveys);
