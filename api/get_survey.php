<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dataDir = __DIR__ . '/data';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Umfrage-ID fehlt.']);
    exit;
}

$surveyId = basename($_GET['id']); // Bereinigen
$filePath = $dataDir . '/survey_' . $surveyId . '.json';

if (file_exists($filePath)) {
    $handle = fopen($filePath, 'r');
    if ($handle && flock($handle, LOCK_SH)) {
        $surveyData = stream_get_contents($handle);
        flock($handle, LOCK_UN); // Sperre freigeben
        fclose($handle);
        echo $surveyData; // Direkte Ausgabe des JSON-Inhalts
    } else {
        if ($handle) fclose($handle);
        http_response_code(503); // Service Unavailable
        echo json_encode(['error' => 'Server ist beschäftigt, bitte später erneut versuchen.']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Umfrage nicht gefunden.']);
}
