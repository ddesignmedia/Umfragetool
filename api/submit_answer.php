<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/rate_limiter.php';
checkRateLimit('submit_answer');

$dataDir = __DIR__ . '/data';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['surveyId']) || !isset($data['answers'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige Eingabedaten.']);
        exit;
    }

    $surveyId = basename($data['surveyId']); // Bereinigen, um Path-Traversal-Angriffe zu verhindern
    $surveyFilePath = $dataDir . '/survey_' . $surveyId . '.json';

    // Sicherstellen, dass die Umfrage existiert, bevor Antworten angenommen werden
    if (!file_exists($surveyFilePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Umfrage nicht gefunden.']);
        exit;
    }

    $answersFilePath = $dataDir . '/answers_' . $surveyId . '.json';
    $responses = [];

    // Vorhandene Antworten lesen (falls die Datei existiert)
    if (file_exists($answersFilePath)) {
        $fileHandleRead = fopen($answersFilePath, 'r');
        if (flock($fileHandleRead, LOCK_SH)) { // Shared lock zum Lesen
            $existingContent = fread($fileHandleRead, filesize($answersFilePath) ?: 1);
            $responses = json_decode($existingContent, true) ?: [];
            flock($fileHandleRead, LOCK_UN);
        }
        fclose($fileHandleRead);
    }

    // Die empfangenen Antworten serverseitig bereinigen, um XSS zu verhindern
    $sanitizedAnswers = [];
    if (is_array($data['answers'])) {
        foreach ($data['answers'] as $questionId => $answer) {
            // Bereinige jede Antwort, egal ob Text oder Multiple-Choice-Wert
            $sanitizedAnswers[$questionId] = is_string($answer)
                ? htmlspecialchars($answer, ENT_QUOTES, 'UTF-8')
                : $answer; // Behalte Nicht-Strings bei (sollte nicht vorkommen)
        }
    }

    // Neue Antwort hinzufügen
    $newResponse = [
        'responseId' => bin2hex(random_bytes(8)),
        'submittedAt' => date(DATE_ISO8601),
        'answers' => $sanitizedAnswers // Verwende die bereinigten Antworten
    ];
    $responses[] = $newResponse;

    // Alle Antworten zurück in die Datei schreiben
    $fileHandleWrite = fopen($answersFilePath, 'w');
    if (flock($fileHandleWrite, LOCK_EX)) { // Exclusive lock zum Schreiben
        fwrite($fileHandleWrite, json_encode($responses, JSON_PRETTY_PRINT));
        flock($fileHandleWrite, LOCK_UN);
    }
    fclose($fileHandleWrite);

    echo json_encode(['success' => true, 'message' => 'Antwort erfolgreich gespeichert.']);

} else {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'API ist erreichbar.']);
}
