<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-control-allow-methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php'; // Für CREATE_TOKEN
require_once __DIR__ . '/rate_limiter.php';
checkRateLimit('create_survey');

// Stellt sicher, dass das data-Verzeichnis existiert
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    // 0775 ist eine sicherere Berechtigung als 0777.
    // Sie erlaubt dem Eigentümer (user) und der Gruppe (group) Lese-, Schreib- und Ausführrechte,
    // während andere (world) nur Lese- und Ausführrechte haben.
    // Dies ist nützlich in Umgebungen, in denen der Webserver-Benutzer (z.B. www-data)
    // in derselben Gruppe ist wie der Datei-Eigentümer.
    mkdir($dataDir, 0775, true);
}

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eingehende JSON-Daten auslesen
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Token-Validierung
    if (!isset($data['token']) || $data['token'] !== CREATE_TOKEN) {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Ungültiger oder fehlender Erstellungs-Token.']);
        exit;
    }

    // Strenge serverseitige Validierung
    if (!isValidSurveyData($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige oder unvollständige Umfragedaten.']);
        exit;
    }

    // Eine zufällige, schwer zu erratende ID generieren
    $surveyId = bin2hex(random_bytes(8));

    $surveyData = [
        'id' => $surveyId,
        'title' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
        'questions' => $data['questions'],
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

function isValidSurveyData($data) {
    if (json_last_error() !== JSON_ERROR_NONE) return false;
    if (!isset($data['title']) || !is_string($data['title']) || trim($data['title']) === '') return false;
    if (!isset($data['questions']) || !is_array($data['questions']) || empty($data['questions'])) return false;

    foreach ($data['questions'] as $question) {
        if (!isset($question['text']) || !is_string($question['text']) || trim($question['text']) === '') return false;
        // Anpassung: Akzeptiert 'mc' für Multiple-Choice, wie vom Frontend gesendet
        if (!isset($question['type']) || !in_array($question['type'], ['mc', 'text'])) return false;
        
        // Anpassung: Prüft auf 'mc' für die Optionsvalidierung
        if ($question['type'] === 'mc') {
            if (!isset($question['options']) || !is_array($question['options']) || count($question['options']) < 2) return false;
            foreach ($question['options'] as $option) {
                if (!is_string($option) || trim($option) === '') return false;
            }
        }
    }
    return true;
}
