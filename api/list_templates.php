<?php
header('Content-Type: application/json');

// Definiere das Verzeichnis für die Vorlagen
$templatesDir = __DIR__ . '/../vorlagen/';

// Überprüfe, ob das Verzeichnis existiert
if (!is_dir($templatesDir)) {
    echo json_encode(['error' => 'Template directory not found.']);
    exit;
}

// Lese die Dateien im Verzeichnis
$files = scandir($templatesDir);
$templates = [];

foreach ($files as $file) {
    // Überspringe '.', '..' und versteckte Dateien
    if ($file[0] === '.') {
        continue;
    }

    // Füge nur .json-Dateien zur Liste hinzu
    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
        $templates[] = pathinfo($file, PATHINFO_FILENAME);
    }
}

echo json_encode($templates);
