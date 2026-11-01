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
    mkdir($dataDir, 0775, true);
}

// Fehler abfangen und als JSON zurückgeben
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

ob_start(); // Puffer starten, um versehentliche Ausgaben zu unterdrücken

try {
    // --- AUTO-CLEANUP: Löscht Umfragen und Antworten, die älter als 7 Tage sind ---
    function cleanupOldSurveys($dataDir) {
        if (!is_dir($dataDir)) return;
        
        // Alle Dateien im Verzeichnis prüfen
        $files = scandir($dataDir);
        $now = time();
        $maxAge = 604800; // 7 Tage in Sekunden (7 * 24 * 60 * 60)

        foreach ($files as $file) {
            $filePath = $dataDir . '/' . $file;
            if (!is_file($filePath)) continue;

            // Nicht löschen: Konfigurationsdateien oder .htaccess
            if ($file === 'rate_limits.json' || $file === 'config.php' || $file === '.htaccess') continue;
            
            // Wenn die Datei älter als das maximale Alter ist
            if ($now - filemtime($filePath) >= $maxAge) {
                // Wenn es eine Umfrage ist, auch die zugehörigen Antworten, Locks und Temps löschen
                if (strpos($file, 'survey_') === 0) {
                    $surveyId = str_replace(['survey_', '.json'], '', $file);
                    $patterns = [
                        'answers_' . $surveyId . '.json',
                        'answers_' . $surveyId . '.lock',
                        'answers_' . $surveyId . '.json.tmp'
                    ];
                    foreach ($patterns as $p) {
                        $pPath = $dataDir . '/' . $p;
                        if (file_exists($pPath)) @unlink($pPath);
                    }
                }
                // Auch alleinstehende .lock oder .tmp Dateien löschen, wenn sie alt sind
                if (strpos($file, '.lock') !== false || strpos($file, '.tmp') !== false) {
                    @unlink($filePath);
                }
                
                @unlink($filePath);
            }
        }
    }

    // Cleanup bei Aufruf
    cleanupOldSurveys($dataDir);

    // Nur POST-Anfragen erlauben
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Eingehende JSON-Daten auslesen
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Token-Validierung
        if (!isset($data['token']) || $data['token'] !== CREATE_TOKEN) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ungültiger oder fehlender Erstellungs-Token.']);
            exit;
        }

        // Strenge serverseitige Validierung
        if (!isValidSurveyData($data)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ungültige oder unvollständige Umfragedaten.']);
            exit;
        }

        // Eine zufällige, schwer zu erratende ID generieren
        $surveyId = bin2hex(random_bytes(8));

        $surveyData = [
            'id' => $surveyId,
            'title' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
            'questions' => $data['questions'],
            'createdAt' => date(DATE_ISO8601),
            'paused' => false,
            'highscoreEnabled' => isset($data['highscoreEnabled']) && filter_var($data['highscoreEnabled'], FILTER_VALIDATE_BOOLEAN)
        ];

        // Sanitize question data
        foreach ($surveyData['questions'] as &$question) {
            $question['text'] = htmlspecialchars($question['text'], ENT_QUOTES, 'UTF-8');
            if ($question['type'] === 'slider') {
                $question['min'] = isset($question['min']) ? intval($question['min']) : 0;
                $question['max'] = isset($question['max']) ? intval($question['max']) : 10;
                $question['minLabel'] = isset($question['minLabel']) ? htmlspecialchars($question['minLabel'], ENT_QUOTES, 'UTF-8') : '';
                $question['maxLabel'] = isset($question['maxLabel']) ? htmlspecialchars($question['maxLabel'], ENT_QUOTES, 'UTF-8') : '';
            } elseif (isset($question['options']) && is_array($question['options'])) {
                foreach ($question['options'] as &$option) {
                    if (is_array($option) && isset($option['text'])) {
                        $option['text'] = htmlspecialchars($option['text'], ENT_QUOTES, 'UTF-8');
                        $option['correct'] = isset($option['correct']) && filter_var($option['correct'], FILTER_VALIDATE_BOOLEAN);
                    } else if (is_string($option)) {
                        $option = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }

        $filePath = $dataDir . '/survey_' . $surveyId . '.json';

        // Umfrage in eine Datei schreiben
        if (file_put_contents($filePath, json_encode($surveyData, JSON_PRETTY_PRINT))) {
            ob_end_clean(); // Puffer leeren (falls Warnungen drin waren)
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'surveyId' => $surveyId]);
        } else {
            throw new Exception("Datei konnte nicht geschrieben werden: $filePath");
        }
    } else {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'API ist erreichbar.']);
    }

} catch (Throwable $e) {
    ob_end_clean(); // Versehentlichen Output verwerfen
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Serverfehler beim Erstellen der Umfrage: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
exit;

function isValidSurveyData($data) {
    if (json_last_error() !== JSON_ERROR_NONE) return false;
    if (!isset($data['title']) || !is_string($data['title']) || trim($data['title']) === '') return false;
    if (!isset($data['questions']) || !is_array($data['questions']) || empty($data['questions'])) return false;

    foreach ($data['questions'] as $question) {
        if (!isset($question['id']) || !is_string($question['id']) || trim($question['id']) === '') return false;
        if (!isset($question['text']) || !is_string($question['text']) || trim($question['text']) === '') return false;
        if (!isset($question['type']) || !in_array($question['type'], ['mc', 'mca', 'star', 'text', 'slider'])) return false;
        
        if ($question['type'] === 'mc' || $question['type'] === 'mca') {
            if (!isset($question['options']) || !is_array($question['options']) || count($question['options']) < 2) return false;
            foreach ($question['options'] as $option) {
                // Supports both new object format and old string format
                if (is_array($option)) {
                    if (!isset($option['text']) || !is_string($option['text']) || trim($option['text']) === '') return false;
                    if (!isset($option['correct']) || !is_bool($option['correct'])) return false;
                } else if (is_string($option)) {
                    if (trim($option) === '') return false;
                } else {
                    return false; // Invalid option format
                }
            }
        } elseif ($question['type'] === 'slider') {
            if (isset($question['min']) && !is_numeric($question['min'])) return false;
            if (isset($question['max']) && !is_numeric($question['max'])) return false;
        }
    }
    return true;
}
