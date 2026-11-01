<?php
// rate_limiter.php

function checkRateLimit($action) {
    require_once __DIR__ . '/config.php';
    $ip = $_SERVER['REMOTE_ADDR'];
    $currentTime = time();
    $dataDir = __DIR__ . '/data';
    
    // 1. Automatische Aufräumaktion (Garbage Collection) mit ca. 1% Wahrscheinlichkeit
    if (rand(1, 100) === 1) {
        $files = glob($dataDir . '/rate_limit_*.json');
        if ($files) {
            foreach ($files as $file) {
                // Wenn die Datei älter als das Fenster ist, kann sie weg
                if (file_exists($file) && ($currentTime - filemtime($file) > RATE_LIMIT_WINDOW)) {
                    @unlink($file);
                }
            }
        }
    }

    // 2. IP-basiertes Rate-Limiting
    $ipHash = md5($ip);
    $rateLimitFile = $dataDir . '/rate_limit_' . $ipHash . '.json';
    
    // Öffne Datei für diese spezifische IP
    $fp = fopen($rateLimitFile, 'c+');
    if (!$fp) {
        return; // Fallback: Durchlassen, wenn Datei nicht geöffnet werden kann
    }

    // Nur diese IP wird blockiert, nicht alle User
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }

    $content = stream_get_contents($fp);
    $limits = $content ? json_decode($content, true) : [];
    if (!is_array($limits)) $limits = [];

    if (!isset($limits[$action])) {
        $limits[$action] = [];
    }

    // Alte Einträge entfernen
    $limits[$action] = array_filter($limits[$action], function($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < RATE_LIMIT_WINDOW;
    });

    // Limit für die jeweilige Aktion aus config definieren
    $limit = 0;
    if ($action === 'create_survey') {
        $limit = RATE_LIMIT_CREATE_SURVEY;
    } elseif ($action === 'submit_answer') {
        $limit = RATE_LIMIT_SUBMIT_ANSWER;
    }

    // Prüfen, ob das Limit überschritten ist
    if (count($limits[$action]) >= $limit) {
        flock($fp, LOCK_UN);
        fclose($fp);
        
        http_response_code(429); // Too Many Requests
        echo json_encode(['success' => false, 'message' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.']);
        exit;
    }

    // Aktuelle Anfrage hinzufügen
    $limits[$action][] = $currentTime;

    // Datei aktualisieren
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($limits));
    
    flock($fp, LOCK_UN);
    fclose($fp);
}
