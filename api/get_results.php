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

// Umfragedaten laden
$surveyJson = file_get_contents($surveyFilePath);
$surveyData = json_decode($surveyJson, true);

// Antwortdaten laden (können noch leer sein)
$responses = [];
if (file_exists($answersFilePath)) {
    $answersJson = file_get_contents($answersFilePath);
    $responses = json_decode($answersJson, true);
}

// Daten kombinieren und zurücksenden
echo json_encode([
    'survey' => $surveyData,
    'responses' => $responses
]);
