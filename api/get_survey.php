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
    // Kein Lock mehr nötig dank Atomic Writes
    $surveyData = file_get_contents($filePath);
    echo $surveyData;
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Umfrage nicht gefunden.']);
}
