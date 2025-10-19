<?php
header('Content-Type: application/json');

// Definiere das Verzeichnis für die Vorlagen
$templatesDir = __DIR__ . '/../vorlagen/';

// Überprüfe, ob eine Template-ID übergeben wurde
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Template ID is required.']);
    exit;
}

// Bereinige den Dateinamen, um Path-Traversal-Angriffe zu verhindern
$templateId = basename($_GET['id']);
$filePath = $templatesDir . $templateId . '.json';

// Überprüfe, ob die Datei existiert und lesbar ist
if (file_exists($filePath) && is_readable($filePath)) {
    // Lese den Inhalt der Datei und gib ihn aus
    $content = file_get_contents($filePath);
    echo $content;
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Template not found.']);
}
