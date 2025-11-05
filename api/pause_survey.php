<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['token']) || $input['token'] !== PAUSE_TOKEN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing token.']);
    exit;
}

if (!isset($input['surveyId']) || !isset($input['paused'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing surveyId or paused status.']);
    exit;
}

$surveyId = basename($input['surveyId']);
$surveyFilePath = __DIR__ . '/data/survey_' . $surveyId . '.json';

if (!file_exists($surveyFilePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Survey not found.']);
    exit;
}

$surveyData = json_decode(file_get_contents($surveyFilePath), true);
$surveyData['paused'] = (bool)$input['paused'];

if (file_put_contents($surveyFilePath, json_encode($surveyData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => 'Survey status updated.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update survey status.']);
}
