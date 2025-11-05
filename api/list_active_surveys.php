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
        // Extrahiere die Survey-ID aus dem Dateinamen
        $surveyId = str_replace(['survey_', '.json'], '', $file);

        // Lese den Inhalt der Datei, um den Titel zu bekommen
        $content = file_get_contents($dataDir . $file);
        $surveyData = json_decode($content, true);

        // Füge die Umfrage zur Liste hinzu, wenn der Titel vorhanden ist
        if (isset($surveyData['title'])) {
            $activeSurveys[] = [
                'id' => $surveyId,
                'title' => $surveyData['title'],
                'paused' => isset($surveyData['paused']) ? $surveyData['paused'] : false
            ];
        }
    }
}

echo json_encode($activeSurveys);
