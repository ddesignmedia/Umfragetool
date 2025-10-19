<?php
header('Content-Type: application/json');

// Erwarte eine POST-Anfrage
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST method is accepted.']);
    exit;
}

// Lese den JSON-Body aus der Anfrage
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['surveyId'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Survey ID is missing.']);
    exit;
}

// Bereinige die Survey-ID, um Sicherheitslücken zu vermeiden
$surveyId = basename($input['surveyId']);
$dataDir = __DIR__ . '/data/';

// Definiere die Dateipfade
$surveyFile = $dataDir . 'survey_' . $surveyId . '.json';
$answersFile = $dataDir . 'answers_' . $surveyId . '.json';

$deleted = false;

// Lösche die Umfrage-Datei, falls sie existiert
if (file_exists($surveyFile)) {
    if (unlink($surveyFile)) {
        $deleted = true;
    }
}

// Lösche die Antwort-Datei, falls sie existiert
if (file_exists($answersFile)) {
    if (unlink($answersFile)) {
        $deleted = true;
    }
}

if ($deleted) {
    echo json_encode(['success' => true, 'message' => 'Survey deleted successfully.']);
} else {
    // Wenn keine Datei gelöscht wurde (z.B. weil sie nicht existierte)
    http_response_code(404); // Not Found
    echo json_encode(['success' => false, 'message' => 'Survey not found or already deleted.']);
}
