<?php
/**
 * API AJAX - Gestionnaire de Recherche
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Headers pour JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Répondre aux requêtes OPTIONS pré-vol
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Retourne une réponse JSON
 * @param bool $success
 * @param string $message
 * @param mixed $data
 */
function json_response($success, $message = '', $data = null) {
    $response = array(
        'success' => $success,
        'message' => $message
    );
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Vérifie que la requête est AJAX
 */
function require_ajax() {
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        json_response(false, 'Requête invalide');
    }
}

/**
 * Vérifie l'authentification pour les actions protégées
 */
function require_auth() {
    if (!is_authenticated()) {
        json_response(false, 'Authentification requise');
    }
}

// Récupération de la méthode et de l'action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Routing des actions
switch ($action) {
    // ============================================
    // ACTIONS PUBLIQUES (sans authentification)
    // ============================================
    
    case 'login':
        require_ajax();
        
        if ($method !== 'POST') {
            json_response(false, 'Méthode non autorisée');
        }
        
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($password)) {
            json_response(false, 'Mot de passe requis');
        }
        
        if (authenticate($password)) {
            json_response(true, 'Connexion réussie');
        } else {
            json_response(false, 'Mot de passe incorrect');
        }
        break;
    
    case 'check_auth':
        require_ajax();
        
        if (is_authenticated()) {
            $session_time = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : time();
            json_response(true, 'Authentifié', array(
                'authenticated' => true,
                'login_time' => $session_time
            ));
        } else {
            json_response(true, 'Non authentifié', array(
                'authenticated' => false
            ));
        }
        break;
    
    // ============================================
    // ACTIONS PROTÉGÉES (authentification requise)
    // ============================================
    
    /**
     * Marque une source comme non pertinente (flag)
     * POST: action=flag_source&id=<source_id>
     */
    case 'flag_source':
        require_ajax();
        require_auth();
        
        if ($method !== 'POST') {
            json_response(false, 'Méthode non autorisée');
        }
        
        $source_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($source_id <= 0) {
            json_response(false, 'ID de source invalide');
        }
        
        $conn = db_connect();
        $id_escaped = db_escape($source_id, $conn);
        
        // Vérifier que la source existe
        $check = db_query("SELECT id FROM sources WHERE id = $id_escaped", $conn);
        if (db_fetch_array($check) === null) {
            db_close($conn);
            json_response(false, 'Source introuvable');
        }
        
        // Mettre à jour le flag pertinente = 0
        $sql = "UPDATE sources SET pertinente = 0 WHERE id = $id_escaped";
        $result = db_query($sql, $conn);
        
        db_close($conn);
        
        if ($result) {
            json_response(true, 'Source marquée comme non pertinente');
        } else {
            json_response(false, 'Erreur lors de la mise à jour');
        }
        break;
    
    /**
     * Restaure une source (marque comme pertinente)
     * POST: action=restore_source&id=<source_id>
     */
    case 'restore_source':
        require_ajax();
        require_auth();
        
        if ($method !== 'POST') {
            json_response(false, 'Méthode non autorisée');
        }
        
        $source_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($source_id <= 0) {
            json_response(false, 'ID de source invalide');
        }
        
        $conn = db_connect();
        $id_escaped = db_escape($source_id, $conn);
        
        // Vérifier que la source existe
        $check = db_query("SELECT id FROM sources WHERE id = $id_escaped", $conn);
        if (db_fetch_array($check) === null) {
            db_close($conn);
            json_response(false, 'Source introuvable');
        }
        
        // Mettre à jour le flag pertinente = 1
        $sql = "UPDATE sources SET pertinente = 1 WHERE id = $id_escaped";
        $result = db_query($sql, $conn);
        
        db_close($conn);
        
        if ($result) {
            json_response(true, 'Source restaurée');
        } else {
            json_response(false, 'Erreur lors de la mise à jour');
        }
        break;
    
    /**
     * Supprime un projet et toutes ses sources
     * POST: action=delete_projet&id=<projet_id>
     */
    case 'delete_projet':
        require_ajax();
        require_auth();
        
        if ($method !== 'POST') {
            json_response(false, 'Méthode non autorisée');
        }
        
        $projet_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($projet_id <= 0) {
            json_response(false, 'ID de projet invalide');
        }
        
        $conn = db_connect();
        $id_escaped = db_escape($projet_id, $conn);
        
        // Vérifier que le projet existe
        $check = db_query("SELECT id, nom FROM projets WHERE id = $id_escaped", $conn);
        $projet = db_fetch_array($check);
        
        if ($projet === null) {
            db_close($conn);
            json_response(false, 'Projet introuvable');
        }
        
        // Supprimer le projet (les sources sont supprimées en cascade)
        $sql = "DELETE FROM projets WHERE id = $id_escaped";
        $result = db_query($sql, $conn);
        
        db_close($conn);
        
        if ($result) {
            json_response(true, 'Projet "' . htmlspecialchars($projet['nom']) . '" supprimé');
        } else {
            json_response(false, 'Erreur lors de la suppression');
        }
        break;
    
    // ============================================
    // ACTION DE LOGOUT
    // ============================================
    
    case 'logout':
        require_ajax();
        
        logout();
        json_response(true, 'Déconnexion réussie');
        break;
    
    // ============================================
    // ACTION INCONNUE
    // ============================================
    
    default:
        json_response(false, 'Action inconnue: ' . htmlspecialchars($action));
        break;
}
