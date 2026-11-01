<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dataDir = __DIR__ . '/data';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Umfrage-ID fehlt.']);
    exit;
}

$surveyId = basename($_GET['id']);
$surveyFilePath = $dataDir . '/survey_' . $surveyId . '.json';
$answersFilePath = $dataDir . '/answers_' . $surveyId . '.json';

if (!file_exists($surveyFilePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Umfrage nicht gefunden.']);
    exit;
}

// Funktion für sicheres Lesen (jetzt ohne Lock, da wir atomar schreiben)
function readJsonFile($filePath) {
    if (!file_exists($filePath)) {
        return null;
    }
    // file_get_contents ist bei atomic writes sicher genug für diesen Anwendungsfall
    $content = file_get_contents($filePath);
    return $content ? json_decode($content, true) : null;
}

// Umfragedaten und Antworten sicher laden
$surveyData = readJsonFile($surveyFilePath);
if ($surveyData === null && file_exists($surveyFilePath)) { // Fehler beim Lesen der Survey-Datei
    http_response_code(503);
    echo json_encode(['error' => 'Konnte Umfragedaten nicht lesen, Server beschäftigt.']);
    exit;
}

$responses = readJsonFile($answersFilePath) ?? []; // Antworten können leer sein

$payload = [
    'survey' => $surveyData,
    'responses' => $responses
];

// Highscore nur berechnen, wenn für diese Umfrage aktiviert
if (isset($surveyData['highscoreEnabled']) && $surveyData['highscoreEnabled']) {
    $highscore = [];
    foreach ($responses as $response) {
        if (isset($response['nickname']) && isset($response['score']) && isset($response['timeTaken'])) {
            $highscore[] = [
                'nickname' => htmlspecialchars($response['nickname']),
                'score' => $response['score'],
                'timeTaken' => $response['timeTaken'],
                'currentQuestionIndex' => $response['currentQuestionIndex'] ?? null,
                'totalQuestions' => $response['totalQuestions'] ?? null
            ];
        }
    }

    // Highscore sortieren: primär nach Score (absteigend), sekundär nach Zeit (aufsteigend)
    usort($highscore, function ($a, $b) {
        if ($a['score'] == $b['score']) {
            return $a['timeTaken'] <=> $b['timeTaken'];
        }
        return $b['score'] <=> $a['score'];
    });

    $payload['highscore'] = $highscore;
}


// Daten kombinieren und zurücksenden
echo json_encode($payload);
