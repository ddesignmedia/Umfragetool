<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/rate_limiter.php';
checkRateLimit('submit_answer');

$dataDir = __DIR__ . '/data';

function calculateScore($answers, $surveyData) {
    $score = 0;
    $totalCorrectPossible = 0;
    $isQuiz = false;

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
        $userAnswer = $answers[$questionId] ?? null;

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
                $tempUser = $userAnswer;
                $tempCorrect = $correctAnswers;
                sort($tempUser);
                sort($tempCorrect);
                if ($tempUser === $tempCorrect) {
                    $score++;
                }
            }
        }
    }

    return ['score' => $score, 'totalCorrect' => $totalCorrectPossible, 'isQuiz' => $isQuiz];
}

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

    // --- Logik VOR dem Locking ausführen ---
    // 1. Eingaben bereinigen
    $sanitizedAnswers = [];
    if (is_array($data['answers'])) {
        foreach ($data['answers'] as $questionId => $answer) {
            if (is_array($answer)) {
                $sanitizedAnswers[$questionId] = array_map(function($a) {
                    return is_string($a) ? htmlspecialchars($a, ENT_QUOTES, 'UTF-8') : $a;
                }, $answer);
            } else {
                $sanitizedAnswers[$questionId] = is_string($answer)
                    ? htmlspecialchars($answer, ENT_QUOTES, 'UTF-8')
                    : $answer;
            }
        }
    }

    // 2. Umfragedaten laden und Score berechnen
    // (Lesezugriff auf Survey-Datei braucht kein Lock, da wir nur lesen und sie sich selten ändert)
    // Hinweis: Wir nutzen file_get_contents ohne Lock, da Atomic Writes bei der Erstellung verwendet werden.
    $surveyJson = file_get_contents($surveyFilePath);
    $surveyData = json_decode($surveyJson, true);
    
    // Score für die AKTUELLEN Antworten berechnen (für das unmittelbare Response-Feedback)
    $calcResult = calculateScore($sanitizedAnswers, $surveyData);
    $score = $calcResult['score'];
    $totalCorrectPossible = $calcResult['totalCorrect'];
    $isQuiz = $calcResult['isQuiz'];

    $newResponse = [
        'responseId' => $data['responseId'] ?? bin2hex(random_bytes(8)),
        'submittedAt' => date(DATE_ISO8601),
        'answers' => $sanitizedAnswers,
        'currentQuestionIndex' => intval($data['currentQuestionIndex'] ?? count($surveyData['questions'])),
        'totalQuestions' => count($surveyData['questions'])
    ];

    if ($isQuiz) {
        $newResponse['score'] = $score; // Dies ist der Score der AKTUELLEN Sendung, wird unten ggf. korrigiert
        $newResponse['totalCorrect'] = $totalCorrectPossible;

        if (isset($data['nickname'])) {
            $newResponse['nickname'] = htmlspecialchars(trim($data['nickname']), ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['timeTaken'])) {
            $newResponse['timeTaken'] = intval($data['timeTaken']);
        }
    }


    // --- Kritischer Bereich: Antwort Speichern (Optimiert) ---
    // Wir nutzen eine separate Lock-Datei, um nicht auf dem Content-File locken zu müssen (Race Conditions bei Windows Rename)
    // und um die Logik vorher parallel laufen zu lassen.
    
    $lockFilePath = $dataDir . '/answers_' . $surveyId . '.lock';
    $lockHandle = fopen($lockFilePath, 'w+');
    if (!$lockHandle || !flock($lockHandle, LOCK_EX)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Server ist ausgelastet, bitte versuchen Sie es kurz erneut.']);
        exit;
    }

    // Aktuelle Antworten einlesen
    $currentContent = file_exists($answersFilePath) ? file_get_contents($answersFilePath) : '[]';
    $responses = json_decode($currentContent, true) ?: [];
    
    // Prüfen ob wir eine bestehende Antwort aktualisieren
    $updated = false;
    if (isset($data['responseId'])) {
        foreach ($responses as &$resp) {
            if ($resp['responseId'] === $data['responseId']) {
                // Bestehende Antwort gefunden -> Felder aktualisieren
                $resp['answers'] = array_merge($resp['answers'], $newResponse['answers'] ?? []);
                $resp['submittedAt'] = $newResponse['submittedAt'];
                $resp['currentQuestionIndex'] = $newResponse['currentQuestionIndex'];
                
                // WICHTIG: Score auf Basis der MERGED Antworten neu berechnen
                if ($isQuiz) {
                    $mergedResult = calculateScore($resp['answers'], $surveyData);
                    $resp['score'] = $mergedResult['score'];
                    $resp['totalCorrect'] = $mergedResult['totalCorrect'];
                    // Wir geben den aktuellen Gesamtscore auch an den Client zurück
                    $score = $mergedResult['score']; 
                }
                
                if (isset($newResponse['timeTaken'])) $resp['timeTaken'] = $newResponse['timeTaken'];
                if (isset($newResponse['nickname'])) $resp['nickname'] = $newResponse['nickname'];
                $updated = true;
                break;
            }
        }
    }

    if (!$updated) {
        // Neue Antwort anhängen
        $responses[] = $newResponse;
    }

    // In temporäre Datei schreiben
    $tempFilePath = $answersFilePath . '.tmp';
    $jsonOutput = json_encode($responses, JSON_PRETTY_PRINT);
    
    if (file_put_contents($tempFilePath, $jsonOutput) === false) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Daten.']);
        exit;
    }

    // Atomic Rename (Windows-Kompatibilität beachten)
    if (DIRECTORY_SEPARATOR === '\\' && file_exists($answersFilePath)) {
        @unlink($answersFilePath); // Windows kann manchmal nicht überschreiben beim Rename
    }
    
    if (!rename($tempFilePath, $answersFilePath)) {
         unlink($tempFilePath); // Cleanup bei Fehler
         flock($lockHandle, LOCK_UN);
         fclose($lockHandle);
         http_response_code(500);
         echo json_encode(['success' => false, 'message' => 'Fehler beim Finalisieren der Daten.']);
         exit;
    }

    // Lock freigeben
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    $responsePayload = [
        'success' => true, 
        'message' => 'Antwort erfolgreich gespeichert.',
        'responseId' => $newResponse['responseId']
    ];
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
