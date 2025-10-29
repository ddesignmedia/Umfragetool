<?php
// rate_limiter.php

function checkRateLimit($action) {
    require_once __DIR__ . '/config.php';
    $ip = $_SERVER['REMOTE_ADDR'];
    $rateLimitFile = __DIR__ . '/data/rate_limits.json';

    $limits = file_exists($rateLimitFile) ? json_decode(file_get_contents($rateLimitFile), true) : [];
    $currentTime = time();

    // Alte Einträge entfernen
    if (isset($limits[$ip])) {
        $limits[$ip] = array_filter($limits[$ip], function($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < RATE_LIMIT_WINDOW;
        });
    }

    // Limite für die jeweilige Aktion definieren
    $limit = 0;
    if ($action === 'create_survey') {
        $limit = RATE_LIMIT_CREATE_SURVEY;
    } elseif ($action === 'submit_answer') {
        $limit = RATE_LIMIT_SUBMIT_ANSWER;
    }

    // Prüfen, ob das Limit überschritten ist
    if (isset($limits[$ip]) && count($limits[$ip]) >= $limit) {
        http_response_code(429); // Too Many Requests
        echo json_encode(['success' => false, 'message' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.']);
        exit;
    }

    // Aktuelle Anfrage hinzufügen
    $limits[$ip][] = $currentTime;

    // Datei aktualisieren
    file_put_contents($rateLimitFile, json_encode($limits));
}
