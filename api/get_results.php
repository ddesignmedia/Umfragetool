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

// Funktion für sicheres Lesen mit Shared Lock
function readJsonFile($filePath) {
    if (!file_exists($filePath)) {
        return null;
    }
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return null;
    }
    // Shared Lock anfordern, 5 Sekunden warten bei Bedarf
    if (flock($handle, LOCK_SH, $wouldBlock)) {
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return json_decode($content, true);
    }
    fclose($handle);
    // Wenn die Datei gesperrt ist, signalisieren wir, dass der Dienst beschäftigt ist.
    http_response_code(503);
    echo json_encode(['error' => 'Server ist beschäftigt, bitte später erneut versuchen.']);
    exit;
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
                'timeTaken' => $response['timeTaken']
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
