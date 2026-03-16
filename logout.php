<?php
/**
 * Gestionnaire de Recherche - Déconnexion
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Détruit la session
logout();

// Redirection vers index.php
header('Location: index.php');
exit;
?>
