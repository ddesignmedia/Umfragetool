<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Stellt sicher, dass das data-Verzeichnis existiert
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eingehende JSON-Daten auslesen
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['title']) || !isset($data['questions'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige Eingabedaten.']);
        exit;
    }

    // Eine zufällige, schwer zu erratende ID generieren
    $surveyId = bin2hex(random_bytes(8));

    $surveyData = [
        'id' => $surveyId,
        'title' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
        'questions' => $data['questions'], // Fragen werden bereits auf dem Client validiert
        'createdAt' => date(DATE_ISO8601)
    ];

    // Sanitize question data
    foreach ($surveyData['questions'] as &$question) {
        $question['text'] = htmlspecialchars($question['text'], ENT_QUOTES, 'UTF-8');
        if (isset($question['options'])) {
            foreach ($question['options'] as &$option) {
                $option = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
            }
        }
    }

    $filePath = $dataDir . '/survey_' . $surveyId . '.json';

    // Umfrage in eine Datei schreiben
    if (file_put_contents($filePath, json_encode($surveyData, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => true, 'surveyId' => $surveyId]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Umfrage konnte nicht gespeichert werden.']);
    }
} else {
    // Fallback für andere Methoden als POST (z.B. pre-flight OPTIONS)
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'API ist erreichbar.']);
}
