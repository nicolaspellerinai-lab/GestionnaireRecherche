<?php
/**
 * Exporter - Gestionnaire de Recherche
 * Exporte un projet en fichier ZIP
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Vérification de l'authentification
if (!is_authenticated()) {
    http_response_code(401);
    echo 'Accès non autorisé';
    exit;
}

// Vérification du paramètre projet
if (!isset($_GET['projet']) || empty($_GET['projet'])) {
    http_response_code(400);
    echo 'Paramètre "projet" requis';
    exit;
}

$projet = $_GET['projet'];
$projetSafe = basename($projet); // Protection contre les chemins path traversal
$projetPath = DATA_PATH . $projetSafe . '/';

// Vérification que le dossier existe
if (!is_dir($projetPath)) {
    http_response_code(404);
    echo 'Projet non trouvé';
    exit;
}

// Création du ZIP
$zipFileName = $projetSafe . '_export_' . date('Ymd_His') . '.zip';
$zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Erreur lors de la création du ZIP';
    exit;
}

// Fichier definition.md
$definitionFile = $projetPath . 'definition.md';
if (file_exists($definitionFile)) {
    $zip->addFile($definitionFile, 'definition.md');
} else {
    $zip->addFromString('definition.md', '# Definition

*Fichier de définition du projet*');
}

// Fichier sources.md
$sourcesFile = $projetPath . 'sources.md';
if (file_exists($sourcesFile)) {
    $zip->addFile($sourcesFile, 'sources.md');
} else {
    $zip->addFromString('sources.md', '# Sources

*Liste des sources*');
}

// Dossier sources/ (contenu brut)
$sourcesDir = $projetPath . 'sources/';
if (is_dir($sourcesDir)) {
    $zip->addEmptyDir('sources');
    $files = glob($sourcesDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $fileName = basename($file);
            $zip->addFile($file, 'sources/' . $fileName);
        }
    }
}

// Dossier resumes/
$resumesDir = $projetPath . 'resumes/';
if (is_dir($resumesDir)) {
    $zip->addEmptyDir('resumes');
    $files = glob($resumesDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $fileName = basename($file);
            $zip->addFile($file, 'resumes/' . $fileName);
        }
    }
}

$zip->close();

// Force download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
header('Content-Length: ' . filesize($zipFilePath));
header('Pragma: no-cache');
header('Expires: 0');

readfile($zipFilePath);

// Nettoyage
unlink($zipFilePath);
exit;
