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

    // Überprüfen, ob die Umfrage pausiert ist
    $surveyData = json_decode(file_get_contents($surveyFilePath), true);
    if (isset($surveyData['paused']) && $surveyData['paused']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Diese Umfrage ist derzeit pausiert und akzeptiert keine neuen Antworten.']);
        exit;
    }

    $answersFilePath = $dataDir . '/answers_' . $surveyId . '.json';
    $isQuiz = false;
    $score = 0;
    $totalCorrectPossible = 0;

    // --- Atomarer Lese-Änderungs-Schreibvorgang ---
    $fileHandle = fopen($answersFilePath, 'c+');
    if (!$fileHandle) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Antwortdatei konnte nicht geöffnet werden.']);
        exit;
    }

    // Exklusive Sperre für den gesamten Vorgang anfordern
    if (!flock($fileHandle, LOCK_EX)) {
        fclose($fileHandle);
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Server ist überlastet, bitte versuchen Sie es später erneut.']);
        exit;
    }

    // Vorhandene Antworten lesen
    $existingContent = stream_get_contents($fileHandle);
    $responses = json_decode($existingContent, true) ?: [];

    // Die empfangenen Antworten serverseitig bereinigen, um XSS zu verhindern
    $sanitizedAnswers = [];
    if (is_array($data['answers'])) {
        foreach ($data['answers'] as $questionId => $answer) {
            if (is_array($answer)) {
                // Bereinige jedes Element im Array für mca-Fragen
                $sanitizedAnswers[$questionId] = array_map(function($a) {
                    return is_string($a) ? htmlspecialchars($a, ENT_QUOTES, 'UTF-8') : $a;
                }, $answer);
            } else {
                // Bereinige einzelne Antworten für mc- und text-Fragen
                $sanitizedAnswers[$questionId] = is_string($answer)
                    ? htmlspecialchars($answer, ENT_QUOTES, 'UTF-8')
                    : $answer;
            }
        }
    }

    // Lade die Umfragedaten, um die korrekten Antworten zu überprüfen
    $surveyJson = file_get_contents($surveyFilePath);
    $surveyData = json_decode($surveyJson, true);

    foreach ($surveyData['questions'] as $question) {
        $isMcQuestion = in_array($question['type'], ['mc', 'mca']);
        $hasCorrectAnswer = false;
        if ($isMcQuestion) {
            foreach ($question['options'] as $option) {
                if (is_array($option) && isset($option['correct']) && $option['correct']) {
                    $hasCorrectAnswer = true;
                    $isQuiz = true;
                    break;
                }
            }
        }

        if (!$hasCorrectAnswer) continue;

        $totalCorrectPossible++;
        $questionId = $question['id'];
        $userAnswer = $sanitizedAnswers[$questionId] ?? null;

        $correctAnswers = [];
        foreach ($question['options'] as $option) {
            if (is_array($option) && isset($option['correct']) && $option['correct']) {
                $correctAnswers[] = $option['text'];
            }
        }

        if ($question['type'] === 'mc') {
            if ($userAnswer && in_array($userAnswer, $correctAnswers)) {
                $score++;
            }
        } elseif ($question['type'] === 'mca') {
            if (is_array($userAnswer)) {
                sort($userAnswer);
                sort($correctAnswers);
                if ($userAnswer === $correctAnswers) {
                    $score++;
                }
            }
        }
    }

    $newResponse = [
        'responseId' => bin2hex(random_bytes(8)),
        'submittedAt' => date(DATE_ISO8601),
        'answers' => $sanitizedAnswers
    ];

    if ($isQuiz) {
        $newResponse['score'] = $score;
        $newResponse['totalCorrect'] = $totalCorrectPossible;

        if (isset($data['nickname']) && isset($data['timeTaken'])) {
            $newResponse['nickname'] = htmlspecialchars(trim($data['nickname']), ENT_QUOTES, 'UTF-8');
            $newResponse['timeTaken'] = intval($data['timeTaken']);
        }
    }

    $responses[] = $newResponse;

    // Den neuen Inhalt vorbereiten
    $newJsonContent = json_encode($responses, JSON_PRETTY_PRINT);

    // Dateizeiger zurücksetzen, neuen Inhalt schreiben und Datei kürzen
    rewind($fileHandle);
    fwrite($fileHandle, $newJsonContent);
    ftruncate($fileHandle, ftell($fileHandle));

    // Sperre freigeben und Datei schließen
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);

    $responsePayload = ['success' => true, 'message' => 'Antwort erfolgreich gespeichert.'];
    if ($isQuiz) {
        $responsePayload['isQuiz'] = true;
        $responsePayload['score'] = $score;
        $responsePayload['totalCorrect'] = $totalCorrectPossible;
    }

    echo json_encode($responsePayload);

} else {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'API ist erreichbar.']);
}
