<?php
/**
 * Configuration - Gestionnaire de Recherche
 * PHP 5.6 Compatible
 */

// Configuration MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'lepetitweb_u-recherchebd');
define('DB_PASS', 'zJIWuD}Og2D]');
define('DB_NAME', 'gestionnaire_recherche');

// Configuration Upload
define('UPLOAD_PASSWORD', 'C3c13stm0np4ss!!!');

// Chemin du dossier des projets
define('DATA_PATH', __DIR__ . '/data/');

// Démarrage de la session
session_start();

/**
 * Connexion à la base de données
 * @return resource|mysqli
 */
function db_connect() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (!$conn) {
        die('Erreur de connexion à la base de données: ' . mysqli_connect_error());
    }
    
    // Configuration UTF-8
    mysqli_set_charset($conn, 'utf8');
    
    return $conn;
}

/**
 * Exécute une requête SQL
 * @param string $sql
 * @param resource|mysqli $conn
 * @return mysqli_result|bool
 */
function db_query($sql, $conn = null) {
    if ($conn === null) {
        $conn = db_connect();
    }
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        error_log('Erreur SQL: ' . mysqli_error($conn) . ' - Requête: ' . $sql);
    }
    
    return $result;
}

/**
 * Récupère une ligne de résultat
 * @param mysqli_result $result
 * @return array|null
 */
function db_fetch_array($result) {
    if ($result === false) {
        return null;
    }
    return mysqli_fetch_array($result, MYSQLI_ASSOC);
}

/**
 * Récupère tous les résultats
 * @param mysqli_result $result
 * @return array
 */
function db_fetch_all($result) {
    $rows = array();
    if ($result === false) {
        return $rows;
    }
    
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $rows[] = $row;
    }
    
    return $rows;
}

/**
 * Échappe une chaîne pour éviter les injections SQL
 * @param string $str
 * @param resource|mysqli $conn
 * @return string
 */
function db_escape($str, $conn = null) {
    if ($conn === null) {
        $conn = db_connect();
    }
    return mysqli_real_escape_string($conn, $str);
}

/**
 * Retourne le dernier ID inséré
 * @param resource|mysqli $conn
 * @return int
 */
function db_insert_id($conn = null) {
    if ($conn === null) {
        $conn = db_connect();
    }
    return mysqli_insert_id($conn);
}

/**
 * Ferme la connexion à la base de données
 * @param resource|mysqli $conn
 */
function db_close($conn) {
    if ($conn) {
        mysqli_close($conn);
    }
}

/**
 * Vérifie l'authentification de l'utilisateur
 * @return bool
 */
function is_authenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Authentifie l'utilisateur avec le mot de passe
 * @param string $password
 * @return bool
 */
function authenticate($password) {
    if ($password === UPLOAD_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        return true;
    }
    return false;
}

/**
 * Déconnecte l'utilisateur
 */
function logout() {
    session_destroy();
    $_SESSION = array();
}

/**
 * Vérifie si le dossier data existe, sinon le crée
 */
function ensure_data_directory() {
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }
}
