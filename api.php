<?php
/**
 * API REST - Gestionnaire de Recherche
 * Endpoints pour gérer les sources via API
 */

require_once 'config.php';

// ============================================
// CONFIGURATION API
// ============================================

// Clé API - à changer en production
if (!defined('API_KEY')) {
    define('API_KEY', 'gr-api-2024-secure-key');
}

// Dossier des données
define('API_DATA_PATH', __DIR__ . '/data/');

// ============================================
// HEADERS
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Répondre aux requêtes OPTIONS pré-vol
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Retourne une réponse JSON
 */
function api_response($success, $message = '', $data = null, $code = 200) {
    http_response_code($code);
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
 * Vérifie la clé API
 */
function require_api_key() {
    $api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
    
    if (empty($api_key) || $api_key !== API_KEY) {
        api_response(false, 'Clé API invalide ou manquante', null, 401);
    }
}

/**
 * Récupère le contenu d'une URL
 */
function fetch_url_content($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GestionnaireRecherche-API/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || $http_code >= 400) {
        return array('error' => $error ?: 'HTTP ' . $http_code, 'content' => null);
    }
    
    return array('error' => null, 'content' => $content);
}

/**
 * Extrait le titre depuis le HTML
 */
function extract_title($html) {
    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
        return trim($matches[1]);
    }
    if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

/**
 * Trouve le prochain ID libre pour un projet
 */
function get_next_source_id($projet_dir) {
    $sources_file = $projet_dir . 'sources.md';
    
    if (!file_exists($sources_file)) {
        return 1;
    }
    
    $content = file_get_contents($sources_file);
    preg_match_all('/^(\d+)\.\s+\*\*/m', $content, $matches);
    
    if (empty($matches[1])) {
        return 1;
    }
    
    $max_id = max(array_map('intval', $matches[1]));
    return $max_id + 1;
}

/**
 * Liste les sources d'un projet
 */
function list_sources($projet) {
    $projet_dir = API_DATA_PATH . $projet . '/';
    $sources_file = $projet_dir . 'sources.md';
    
    if (!is_dir($projet_dir)) {
        return array();
    }
    
    if (!file_exists($sources_file)) {
        return array();
    }
    
    $content = file_get_contents($sources_file);
    $sources = array();
    
    // Format: 1. **Titre** - URL: https://... (Date: YYYY-MM-DD)
    preg_match_all('/^(\d+)\.\s+\*\*([^*]+)\*\*[^-]*- URL:\s*(https?:\/\/[^\s]+)(?:\s+\(Date:\s*([^\)]+)\))?/m', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $sources[] = array(
            'id' => intval($match[1]),
            'titre' => trim($match[2]),
            'url' => $match[3],
            'date' => isset($match[5]) ? trim($match[5]) : null
        );
    }
    
    return $sources;
}

/**
 * Récupère une source spécifique
 */
function get_source($projet, $id) {
    $projet_dir = API_DATA_PATH . $projet . '/';
    $source_file = $projet_dir . 'sources/' . sprintf('%02d', $id) . '.md';
    $sources_file = $projet_dir . 'sources.md';
    
    if (!file_exists($source_file)) {
        return null;
    }
    
    $contenu = file_get_contents($source_file);
    
    // Récupérer les métadonnées depuis sources.md
    $titre = 'Source #' . $id;
    $url = '';
    
    if (file_exists($sources_file)) {
        $sources_content = file_get_contents($sources_file);
        preg_match('/' . $id + 0 . '\.\s+\*\*([^*]+)\*\*[^-]*- URL:\s*(https?:\/\/[^\s]+)/', $sources_content, $matches);
        if (!empty($matches[1])) {
            $titre = trim($matches[1]);
        }
        if (!empty($matches[2])) {
            $url = $matches[2];
        }
    }
    
    return array(
        'id' => $id,
        'titre' => $titre,
        'url' => $url,
        'contenu' => $contenu
    );
}

/**
 * Ajoute une nouvelle source
 */
function add_source($projet, $url, $titre = null) {
    $projet_dir = API_DATA_PATH . $projet . '/';
    $sources_dir = $projet_dir . 'sources/';
    $sources_file = $projet_dir . 'sources.md';
    
    // Créer les dossiers si nécessaires
    if (!is_dir($projet_dir)) {
        mkdir($projet_dir, 0755, true);
    }
    if (!is_dir($sources_dir)) {
        mkdir($sources_dir, 0755, true);
    }
    
    // Récupérer le contenu de l'URL
    $fetch_result = fetch_url_content($url);
    if ($fetch_result['error']) {
        return array('error' => 'Erreur lors de la récupération de l\'URL: ' . $fetch_result['error']);
    }
    
    // Si pas de titre fourni, l'extraire du HTML
    if (empty($titre)) {
        $titre = extract_title($fetch_result['content']);
        if (empty($titre)) {
            $titre = 'Source - ' . date('Y-m-d');
        }
    }
    
    // Trouver le prochain ID
    $next_id = get_next_source_id($projet_dir);
    
    // Créer le fichier source
    $source_file = $sources_dir . sprintf('%02d', $next_id) . '.md';
    $source_content = "# " . $titre . "\n\n";
    $source_content .= "URL: " . $url . "\n";
    $source_content .= "Date: " . date('Y-m-d') . "\n\n";
    $source_content .= "---\n\n";
    $source_content .= "## Contenu\n\n";
    $source_content .= strip_tags($fetch_result['content']);
    $source_content .= "\n\n---\n\n";
    $source_content .= "*Source importée via API le " . date('Y-m-d H:i:s') . "*\n";
    
    if (file_put_contents($source_file, $source_content) === false) {
        return array('error' => 'Erreur lors de la création du fichier source');
    }
    
    // Ajouter dans sources.md
    $date = date('Y-m-d');
    $new_entry = $next_id . ". **" . $titre . "** - URL: " . $url . " (Date: " . $date . ")\n";
    
    if (file_exists($sources_file)) {
        $existing_content = file_get_contents($sources_file);
        
        // Ajouter à la fin avant la dernière ligne si elle existe
        if (preg_match('/\n$/', $existing_content)) {
            $existing_content .= $new_entry;
        } else {
            $existing_content .= "\n" . $new_entry;
        }
    } else {
        $existing_content = "# Sources\n\n" . $new_entry;
    }
    
    if (file_put_contents($sources_file, $existing_content) === false) {
        return array('error' => 'Erreur lors de la mise à jour de sources.md');
    }
    
    return array(
        'id' => $next_id,
        'titre' => $titre,
        'url' => $url,
        'message' => 'Source ajoutée avec succès'
    );
}

/**
 * Met à jour une source existante
 */
function update_source($projet, $id, $titre = null, $url = null) {
    $projet_dir = API_DATA_PATH . $projet . '/';
    $sources_file = $projet_dir . 'sources.md';
    
    if (!file_exists($sources_file)) {
        return array('error' => 'Projet introuvable');
    }
    
    // Lire le contenu actuel
    $content = file_get_contents($sources_file);
    
    // Chercher l'entrée existante
    $pattern = '/^(' . $id . '\.\s+\*\*)[^*]+(\*\*[^-]*- URL:\s*)(https?:\/\/[^\s]+)(.*)$/m';
    
    if (!preg_match($pattern, $content, $matches)) {
        return array('error' => 'Source introuvable');
    }
    
    // Mettre à jour le titre si fourni
    $new_titre = $titre ?: substr($matches[1], strpos($matches[1], '**') + 2);
    $new_url = $url ?: $matches[3];
    
    // Reconstruire la ligne
    $old_line = $matches[0];
    $new_line = $id . ". **" . $new_titre . "** - URL: " . $new_url;
    
    // Garder la date si présente
    if (preg_match('/\(Date:\s*[^)]+\)/', $matches[4], $date_match)) {
        $new_line .= " " . $date_match[0];
    }
    
    $new_content = str_replace($old_line, $new_line, $content);
    
    if (file_put_contents($sources_file, $new_content) === false) {
        return array('error' => 'Erreur lors de la mise à jour');
    }
    
    return array(
        'id' => $id,
        'titre' => $new_titre,
        'url' => $new_url,
        'message' => 'Source mise à jour avec succès'
    );
}

/**
 * Supprime une source
 */
function delete_source($projet, $id) {
    $projet_dir = API_DATA_PATH . $projet . '/';
    $sources_dir = $projet_dir . 'sources/';
    $sources_file = $projet_dir . 'sources.md';
    
    // Supprimer le fichier source
    $source_file = $sources_dir . sprintf('%02d', $id) . '.md';
    if (file_exists($source_file)) {
        unlink($source_file);
    }
    
    // Supprimer aussi le resume si existant
    $resume_file = $projet_dir . 'resume-' . sprintf('%02d', $id) . '.md';
    if (file_exists($resume_file)) {
        unlink($resume_file);
    }
    
    // Retirer de sources.md
    if (file_exists($sources_file)) {
        $content = file_get_contents($sources_file);
        $content = preg_replace('/^' . $id . '\.\s+\*\*[^*]+\*\*[^-]*- URL:\s*https?:\/\/[^\s]+(?:\s+\(Date:\s*[^)]+\))?\n?/m', '', $content);
        file_put_contents($sources_file, $content);
    }
    
    return array('message' => 'Source supprimée avec succès', 'id' => $id);
}

// ============================================
// ROUTING
// ============================================

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';

// Parser les query params
$projet = isset($_GET['projet']) ? trim($_GET['projet']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Router selon la méthode et le chemin
if ($path === '/sources' || $path === '/sources/') {
    // GET /api/sources?projet=XXX - Liste des sources
    if ($method === 'GET') {
        require_api_key();
        
        if (empty($projet)) {
            api_response(false, 'Paramètre "projet" requis', null, 400);
        }
        
        $sources = list_sources($projet);
        api_response(true, 'Liste des sources', $sources);
    }
    
} elseif ($path === '/source' || $path === '/source/') {
    
    // GET /api/source?projet=XXX&id=1 - Détail d'une source
    if ($method === 'GET') {
        require_api_key();
        
        if (empty($projet) || $id <= 0) {
            api_response(false, 'Paramètres "projet" et "id" requis', null, 400);
        }
        
        $source = get_source($projet, $id);
        if ($source === null) {
            api_response(false, 'Source introuvable', null, 404);
        }
        
        api_response(true, 'Détails de la source', $source);
    }
    
    // POST /api/source - Ajouter une source
    elseif ($method === 'POST') {
        require_api_key();
        
        // Lire le body JSON
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['projet']) || empty($input['url'])) {
            api_response(false, 'Paramètres "projet" et "url" requis', null, 400);
        }
        
        $result = add_source($input['projet'], $input['url'], isset($input['titre']) ? $input['titre'] : null);
        
        if (isset($result['error'])) {
            api_response(false, $result['error'], null, 500);
        }
        
        api_response(true, 'Source ajoutée', $result);
    }
    
    // PUT /api/source - Modifier une source
    elseif ($method === 'PUT') {
        require_api_key();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['projet']) || empty($input['id'])) {
            api_response(false, 'Paramètres "projet" et "id" requis', null, 400);
        }
        
        $result = update_source(
            $input['projet'], 
            $input['id'], 
            isset($input['titre']) ? $input['titre'] : null,
            isset($input['url']) ? $input['url'] : null
        );
        
        if (isset($result['error'])) {
            api_response(false, $result['error'], null, 404);
        }
        
        api_response(true, 'Source mise à jour', $result);
    }
    
    // DELETE /api/source?projet=XXX&id=1 - Supprimer une source
    elseif ($method === 'DELETE') {
        require_api_key();
        
        if (empty($projet) || $id <= 0) {
            api_response(false, 'Paramètres "projet" et "id" requis', null, 400);
        }
        
        $result = delete_source($projet, $id);
        api_response(true, 'Source supprimée', $result);
    }
    
} else {
    // Route non trouvée
    api_response(false, 'Route non trouvée: ' . $path, null, 404);
}

// ============================================
// ENDPOINTS SUPPLÉMENTAIRES
// ============================================

/**
 * Parse les métadonnées YAML d'un fichier definition.md
 */
function parse_definition_metadata($content) {
    $metadata = array(
        'titre' => '',
        'description' => '',
        'categorie' => '',
        'tags' => array(),
        'date_creation' => ''
    );
    
    if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
        $yaml = $matches[1];
        
        if (preg_match('/titre:\s*(.*)$/m', $yaml, $m)) {
            $metadata['titre'] = trim($m[1]);
        }
        if (preg_match('/description:\s*(.*)$/m', $yaml, $m)) {
            $metadata['description'] = trim($m[1]);
        }
        if (preg_match('/categorie:\s*(.*)$/m', $yaml, $m)) {
            $metadata['categorie'] = trim($m[1]);
        }
        if (preg_match('/tags:\s*(.*)$/m', $yaml, $m)) {
            $tags_str = trim($m[1]);
            $metadata['tags'] = array_map('trim', explode(',', $tags_str));
            $metadata['tags'] = array_filter($metadata['tags']);
        }
        if (preg_match('/date_creation:\s*(.*)$/m', $yaml, $m)) {
            $metadata['date_creation'] = trim($m[1]);
        }
    }
    
    return $metadata;
}

/**
 * GET /api/projects - Liste des projets avec filtres optionnels
 * GET /api/projects?tag=IA&categorie=Formation
 */
if ($path === '/projects' || $path === '/projects/') {
    if ($method === 'GET') {
        require_api_key();
        
        $filter_tag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
        $filter_categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';
        
        $projects = array();
        
        if (is_dir(API_DATA_PATH)) {
            $dirs = scandir(API_DATA_PATH);
            
            foreach ($dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir(API_DATA_PATH . $dir)) {
                    $project_path = API_DATA_PATH . $dir;
                    $definition_file = $project_path . '/definition.md';
                    
                    if (file_exists($definition_file)) {
                        $content = file_get_contents($definition_file);
                        $metadata = parse_definition_metadata($content);
                        
                        if (empty($metadata['titre'])) {
                            $metadata['titre'] = $dir;
                        }
                        
                        // Appliquer les filtres
                        $include = true;
                        
                        if (!empty($filter_tag)) {
                            if (!in_array($filter_tag, $metadata['tags'])) {
                                $include = false;
                            }
                        }
                        
                        if (!empty($filter_categorie)) {
                            if (strcasecmp($metadata['categorie'], $filter_categorie) !== 0) {
                                $include = false;
                            }
                        }
                        
                        if ($include) {
                            $projects[] = array(
                                'name' => $dir,
                                'titre' => $metadata['titre'],
                                'description' => $metadata['description'],
                                'categorie' => $metadata['categorie'],
                                'tags' => array_values($metadata['tags'])
                            );
                        }
                    }
                }
            }
        }
        
        api_response(true, count($projects) . ' projet(s) trouvé(s)', $projects);
    }
}

/**
 * GET /api/tags - Liste de tous les tags et catégories
 */
if ($path === '/tags' || $path === '/tags/') {
    if ($method === 'GET') {
        require_api_key();
        
        $all_tags = array();
        $all_categories = array();
        
        if (is_dir(API_DATA_PATH)) {
            $dirs = scandir(API_DATA_PATH);
            
            foreach ($dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir(API_DATA_PATH . $dir)) {
                    $project_path = API_DATA_PATH . $dir;
                    $definition_file = $project_path . '/definition.md';
                    
                    if (file_exists($definition_file)) {
                        $content = file_get_contents($definition_file);
                        
                        if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
                            $yaml = $matches[1];
                            
                            if (preg_match('/categorie:\s*(.*)$/m', $yaml, $m)) {
                                $cat = trim($m[1]);
                                if (!empty($cat)) {
                                    $all_categories[$cat] = true;
                                }
                            }
                            if (preg_match('/tags:\s*(.*)$/m', $yaml, $m)) {
                                $tags_str = trim($m[1]);
                                $tags = array_map('trim', explode(',', $tags_str));
                                foreach ($tags as $tag) {
                                    if (!empty($tag)) {
                                        $all_tags[$tag] = true;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        ksort($all_tags);
        ksort($all_categories);
        
        api_response(true, 'Tags et catégories récupérés', array(
            'tags' => array_keys($all_tags),
            'categories' => array_keys($all_categories)
        ));
    }
}
