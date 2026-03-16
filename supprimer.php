<?php
/**
 * Supprimer un projet - Gestionnaire de Recherche
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Vérifier l'authentification
if (!is_authenticated()) {
    header('Location: login.php');
    exit;
}

// Vérifier le paramètre projet
if (!isset($_GET['projet']) || empty($_GET['projet'])) {
    header('Location: index.php?message=' . urlencode('Nom de projet manquant.') . '&type=error');
    exit;
}

$project_name = $_GET['projet'];

// Sécuriser le nom du projet (même sanitization que dans index.php)
$safe_project_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project_name);

$project_path = DATA_PATH . $safe_project_name;

// Vérifier que le dossier existe
if (!is_dir($project_path)) {
    header('Location: index.php?message=' . urlencode('Le projet "' . htmlspecialchars($project_name) . '" n\'existe pas.') . '&type=error');
    exit;
}

// Fonction récursive pour supprimer un dossier et son contenu
function remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            remove_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

// Supprimer le dossier du projet
if (remove_directory($project_path)) {
    $message = 'Projet "' . htmlspecialchars($safe_project_name) . '" supprimé avec succès.';
    $type = 'success';
} else {
    $message = 'Erreur lors de la suppression du projet "' . htmlspecialchars($safe_project_name) . '".';
    $type = 'error';
}

// Rediriger vers index.php avec le message
header('Location: index.php?message=' . urlencode($message) . '&type=' . $type);
exit;
