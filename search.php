<?php
/**
 * Gestionnaire de Recherche - Recherche Full-Text
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Configuration
define('MAX_RESULTS', 50);
define('EXCERPT_LENGTH', 150);

/**
 * Extrait un excerpt autour du terme recherché
 * @param string $content Contenu complet
 * @param string $query Terme recherché
 * @return string Excerpt avec highlight
 */
function getExcerpt($content, $query) {
    if (empty($query)) {
        return '';
    }
    
    $queryLower = mb_strtolower($query, 'UTF-8');
    $contentLower = mb_strtolower($content, 'UTF-8');
    
    // Trouver la position du terme
    $pos = mb_stripos($contentLower, $queryLower);
    
    if ($pos === false) {
        // Terme pas trouvé directement, retourner le début
        $excerpt = mb_substr($content, 0, EXCERPT_LENGTH);
    } else {
        // Calculer le début de l'excerpt (centré sur le terme si possible)
        $start = max(0, $pos - floor(EXCERPT_LENGTH / 2));
        
        // Ajuster pour ne pas couper un mot
        if ($start > 0) {
            $spacePos = mb_strpos($content, ' ', $start);
            if ($spacePos !== false && $spacePos < $pos + mb_strlen($query)) {
                $start = $spacePos + 1;
            }
        }
        
        $excerpt = mb_substr($content, $start, EXCERPT_LENGTH);
        
        // Ajouter "..." si nécessaire
        if ($start > 0) {
            $excerpt = '...' . $excerpt;
        }
        if ($start + EXCERPT_LENGTH < mb_strlen($content)) {
            $excerpt = $excerpt . '...';
        }
    }
    
    // Nettoyer l'excerpt (retirer les sauts de ligne excessifs)
    $excerpt = preg_replace('/\s+/', ' ', $excerpt);
    $excerpt = trim($excerpt);
    
    // Appliquer le highlight
    $excerpt = highlightTerm($excerpt, $query);
    
    return $excerpt;
}

/**
 * Met en évidence le terme recherché dans le texte
 * @param string $text Texte
 * @param string $query Terme à mettre en évidence
 * @return string Texte avec highlight
 */
function highlightTerm($text, $query) {
    if (empty($query)) {
        return $text;
    }
    
    // Utiliser une regex insensible à la casse
    $pattern = '/(' . preg_quote($query, '/') . ')/iu';
    return preg_replace($pattern, '<mark>$1</mark>', $text);
}

/**
 * Effectue la recherche dans les fichiers
 * @param string $query Terme rechercher
 * @return array Résultats de la recherche
 */
function search($query) {
    $results = [];
    $query = trim($query);
    
    if (empty($query)) {
        return $results;
    }
    
    $count = 0;
    
    // Vérifier que le dossier data existe
    if (!is_dir(DATA_PATH)) {
        return $results;
    }
    
    // Parcourir les projets
    $projects = scandir(DATA_PATH);
    
    foreach ($projects as $project) {
        if ($project === '.' || $project === '..' || !is_dir(DATA_PATH . $project)) {
            continue;
        }
        
        $projectPath = DATA_PATH . $project;
        
        // 1. Rechercher dans definition.md
        $definitionFile = $projectPath . '/definition.md';
        if (file_exists($definitionFile)) {
            $content = file_get_contents($definitionFile);
            if (stripos($content, $query) !== false) {
                if ($count < MAX_RESULTS) {
                    $results[] = [
                        'type' => 'definition',
                        'file' => 'definition.md',
                        'path' => $definitionFile,
                        'projet' => $project,
                        'excerpt' => getExcerpt($content, $query),
                        'url' => 'projet.php?projet=' . urlencode($project)
                    ];
                    $count++;
                }
            }
        }
        
        // 2. Rechercher dans les fichiers sources (sources.md)
        $sourcesFile = $projectPath . '/sources.md';
        if (file_exists($sourcesFile) && $count < MAX_RESULTS) {
            $content = file_get_contents($sourcesFile);
            if (stripos($content, $query) !== false) {
                $results[] = [
                    'type' => 'sources',
                    'file' => 'sources.md',
                    'path' => $sourcesFile,
                    'projet' => $project,
                    'excerpt' => getExcerpt($content, $query),
                    'url' => 'projet.php?projet=' . urlencode($project) . '#sources'
                ];
                $count++;
            }
        }
        
        // 3. Rechercher dans le dossier sources/
        $sourcesDir = $projectPath . '/sources';
        if (is_dir($sourcesDir) && $count < MAX_RESULTS) {
            $sourceFiles = scandir($sourcesDir);
            
            foreach ($sourceFiles as $file) {
                if ($file === '.' || $file === '..' || !preg_match('/\.md$/', $file)) {
                    continue;
                }
                
                if ($count >= MAX_RESULTS) {
                    break;
                }
                
                $filePath = $sourcesDir . '/' . $file;
                $content = file_get_contents($filePath);
                
                if (stripos($content, $query) !== false) {
                    $results[] = [
                        'type' => 'source',
                        'file' => $file,
                        'path' => $filePath,
                        'projet' => $project,
                        'excerpt' => getExcerpt($content, $query),
                        'url' => 'source.php?projet=' . urlencode($project) . '&fichier=' . urlencode($file)
                    ];
                    $count++;
                }
            }
        }
        
        // 4. Rechercher dans les fichiers resume-*.md
        if ($count < MAX_RESULTS) {
            $resumeFiles = glob($projectPath . '/resume-*.md');
            
            foreach ($resumeFiles as $filePath) {
                if ($count >= MAX_RESULTS) {
                    break;
                }
                
                $file = basename($filePath);
                $content = file_get_contents($filePath);
                
                if (stripos($content, $query) !== false) {
                    $results[] = [
                        'type' => 'resume',
                        'file' => $file,
                        'path' => $filePath,
                        'projet' => $project,
                        'excerpt' => getExcerpt($content, $query),
                        'url' => 'projet.php?projet=' . urlencode($project) . '#resumes'
                    ];
                    $count++;
                }
            }
        }
    }
    
    return $results;
}

/**
 * Retourne le type de fichier traduit
 * @param string $type Type de fichier
 * @return string Type traduit
 */
function getTypeLabel($type) {
    $labels = [
        'definition' => 'Définition',
        'sources' => 'Liste des sources',
        'source' => 'Source',
        'resume' => 'Résumé'
    ];
    
    return isset($labels[$type]) ? $labels[$type] : $type;
}

// Vérifier si c'est une requête API
$isApi = isset($_GET['api']);

// Traitement de la recherche
$query = isset($_GET['q']) ? $_GET['q'] : '';
$results = [];
$searchTime = 0;

if (!empty($query)) {
    $startTime = microtime(true);
    $results = search($query);
    $searchTime = microtime(true) - $startTime;
}

// Si requête API, retourner JSON
if ($isApi) {
    header('Content-Type: application/json');
    echo json_encode([
        'query' => $query,
        'count' => count($results),
        'time' => round($searchTime, 3),
        'results' => $results
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche - Gestionnaire de Recherche</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Styles pour la recherche */
        .search-container {
            margin-bottom: 30px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-form input[type="text"] {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .search-form input[type="text"]:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .search-form .btn {
            padding: 12px 30px;
        }
        
        .search-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #555;
        }
        
        .search-time {
            color: #27ae60;
            font-weight: 500;
        }
        
        .results-list {
            list-style: none;
        }
        
        .result-item {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: box-shadow 0.3s ease;
        }
        
        .result-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .result-project {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .result-type {
            display: inline-block;
            padding: 4px 10px;
            background: #e9ecef;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #495057;
        }
        
        .result-file {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .result-excerpt {
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            margin-bottom: 15px;
        }
        
        .result-excerpt mark {
            background: #fff3cd;
            padding: 2px 4px;
            border-radius: 2px;
            color: #856404;
        }
        
        .result-link {
            display: inline-block;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        
        .result-link:hover {
            text-decoration: underline;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .no-results h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #555;
        }
        
        .search-tips {
            margin-top: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .search-tips h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .search-tips ul {
            margin-left: 20px;
            color: #555;
            font-size: 14px;
        }
        
        .search-tips li {
            margin-bottom: 8px;
        }
        
        .api-link {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 10px;
        }
        
        .api-link a {
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Gestionnaire de Recherche</h1>
            <div>
                <a href="index.php" class="btn btn-secondary">← Retour à l'accueil</a>
            </div>
        </header>
        
        <div class="search-container">
            <form method="get" action="search.php" class="search-form">
                <input type="text" 
                       name="q" 
                       value="<?php echo htmlspecialchars($query); ?>" 
                       placeholder="Rechercher dans les sources, résumés et définitions..."
                       autofocus>
                <button type="submit" class="btn btn-primary">Rechercher</button>
            </form>
            
            <?php if (!empty($query)): ?>
                <div class="search-info">
                    <span>
                        <?php echo count($results); ?> résultat(s) trouvé(s)
                        <?php if (count($results) >= MAX_RESULTS): ?>
                            (limité à <?php echo MAX_RESULTS; ?>)
                        <?php endif; ?>
                    </span>
                    <span class="search-time">Temps de recherche: <?php echo round($searchTime, 3); ?>s</span>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($query)): ?>
            <?php if (empty($results)): ?>
                <div class="no-results">
                    <h3>Aucun résultat trouvé</h3>
                    <p>Aucun résultat ne correspond à votre recherche "<?php echo htmlspecialchars($query); ?>"</p>
                </div>
            <?php else: ?>
                <ul class="results-list">
                    <?php foreach ($results as $result): ?>
                        <li class="result-item">
                            <div class="result-header">
                                <span class="result-project"><?php echo htmlspecialchars($result['projet']); ?></span>
                                <span class="result-type"><?php echo getTypeLabel($result['type']); ?></span>
                            </div>
                            <div class="result-file"><?php echo htmlspecialchars($result['file']); ?></div>
                            <div class="result-excerpt"><?php echo $result['excerpt']; ?></div>
                            <a href="<?php echo htmlspecialchars($result['url']); ?>" class="result-link">Voir la source →</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <div class="api-link">
                API: <a href="?q=<?php echo urlencode($query); ?>&api=1" target="_blank">/api/search?q=<?php echo urlencode($query); ?></a>
            </div>
        <?php else: ?>
            <div class="search-tips">
                <h3>Conseils de recherche</h3>
                <ul>
                    <li>La recherche est insensible à la casse (majuscules/minuscules)</li>
                    <li>Vous pouvez rechercher dans les sources, résumés et définitions</li>
                    <li>Les résultats affichent un extrait du contenu avec le terme mis en évidence</li>
                    <li>Maximum 50 résultats affichés pour des performances optimales</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
