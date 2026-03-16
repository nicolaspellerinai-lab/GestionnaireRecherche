<?php
/**
 * Gestionnaire de Recherche - Détail Projet
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Vérifier si le paramètre projet est présent
if (!isset($_GET['projet']) || empty($_GET['projet'])) {
    header('Location: index.php');
    exit;
}

$project_name = $_GET['projet'];
$project_path = DATA_PATH . $project_name;

// Vérifier que le dossier du projet existe
if (!is_dir($project_path)) {
    $message = 'Projet non trouvé.';
    $message_type = 'error';
}

// Traitement des actions (flagguer, restaurer)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $source_id = isset($_POST['source_id']) ? $_POST['source_id'] : '';
    
    if ($action === 'flagger' && !empty($source_id)) {
        // Flaguer source comme non pertinente
        $sources_file = $project_path . '/sources.md';
        $non_pertinentes_dir = $project_path . '/non-pertinentes';
        
        // Créer le dossier non-pertinentes si nécessaire
        if (!is_dir($non_pertinentes_dir)) {
            mkdir($non_pertinentes_dir, 0755, true);
            mkdir($non_pertinentes_dir . '/sources', 0755, true);
            mkdir($non_pertinentes_dir . '/resumes', 0755, true);
        }
        
        // Lire sources.md pour trouver la source
        if (file_exists($sources_file)) {
            $sources_content = file_get_contents($sources_file);
            
            // Chercher la source par ID et déplacer
            // Format attendu: [ID] Titre - URL
            $source_file = $project_path . '/sources/' . $source_id . '.md';
            $resume_file = $project_path . '/resumes/resume-' . $source_id . '.md';
            
            if (file_exists($source_file)) {
                copy($source_file, $non_pertinentes_dir . '/sources/' . $source_id . '.md');
                unlink($source_file);
            }
            
            if (file_exists($resume_file)) {
                copy($resume_file, $non_pertinentes_dir . '/resumes/resume-' . $source_id . '.md');
                unlink($resume_file);
            }
            
            // Mettre à jour sources.md (supprimer la ligne)
            $lines = file($sources_file, FILE_IGNORE_NEW_LINES);
            $new_lines = array();
            foreach ($lines as $line) {
                if (strpos($line, '[' . $source_id . ']') === false) {
                    $new_lines[] = $line;
                }
            }
            file_put_contents($sources_file, implode("\n", $new_lines));
            
            // Ajouter à non-pertinentes/sources.md
            $non_pertinentes_sources = $non_pertinentes_dir . '/sources.md';
            if (file_exists($sources_file)) {
                // Lire l'ancienne ligne pour l'ajouter
                $lines = file($sources_file, FILE_IGNORE_NEW_LINES);
                foreach ($lines as $line) {
                    // La ligne a déjà été supprimée, on cherche dans les fichiers
                }
            }
            
            $message = 'Source flaguée comme non pertinente.';
            $message_type = 'success';
        }
    } elseif ($action === 'restaurer' && !empty($source_id)) {
        // Restaurer une source non pertinente
        $non_pertinentes_dir = $project_path . '/non-pertinentes';
        
        $source_file = $non_pertinentes_dir . '/sources/' . $source_id . '.md';
        $resume_file = $non_pertinentes_dir . '/resumes/resume-' . $source_id . '.md';
        
        if (file_exists($source_file)) {
            copy($source_file, $project_path . '/sources/' . $source_id . '.md');
            unlink($source_file);
        }
        
        if (file_exists($resume_file)) {
            copy($resume_file, $project_path . '/resumes/resume-' . $source_id . '.md');
            unlink($resume_file);
        }
        
        $message = 'Source restaurée avec succès.';
        $message_type = 'success';
    }
}

// Charger la description du projet (definition.md)
$project_description = '';
if (is_dir($project_path)) {
    $definition_file = $project_path . '/definition.md';
    if (file_exists($definition_file)) {
        $project_description = file_get_contents($definition_file);
    }
}

// Charger la liste des sources (sources.md)
$sources = array();
$sources_file = $project_path . '/sources.md';
if (file_exists($sources_file)) {
    $content = file_get_contents($sources_file);
    // Parser les formats possibles:
    // [1] Titre - URL
    // 1. **Titre** - URL: https://...
    // 1. Titre - URL: https://...
    preg_match_all('/(?:\[?(\d+)\]?|\d+\.)\s+\*\*([^*]+)\*\*|(?:\[?(\d+)\]?|\d+\.)\s+([^\n]+?)(?=\s+-|\s+URL)/i', $content, $matches, PREG_SET_ORDER);
    
    // Alternative: chercher les lignes avec "URL:" ou "- URL"
    preg_match_all('/(?:^|\n)\s*(?:\d+\.|\[?(\d+)\]?)\s+(?:\*\*)?([^\n*]+)(?:\*\*)?\s*(?:-|URL:)\s*(https?:\/\/[^\s\n]+)/mi', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $id = !empty($match[1]) ? $match[1] : (isset($match[2]) ? $match[2] : '');
        $titre = trim(!empty($match[2]) ? $match[2] : (isset($match[3]) ? $match[3] : ''));
        $url = isset($match[3]) ? $match[3] : '';
        
        if ($id && $titre && $url) {
            $sources[] = array(
                'id' => $id,
                'titre' => trim($titre),
                'url' => $url
            );
        }
    }
    
    // Si pas de match, essayer un format plus simple
    if (empty($sources)) {
        preg_match_all('/(\d+)\.\s+\*\*([^*]+)\*\*/', $content, $matches1, PREG_SET_ORDER);
        preg_match_all('/- URL:\s*(https?:\/\/[^\s]+)/', $content, $matches2, PREG_SET_ORDER);
        
        $urls = array();
        foreach ($matches2 as $m) {
            $urls[] = $m[1];
        }
        
        foreach ($matches1 as $i => $m) {
            $sources[] = array(
                'id' => $m[1],
                'titre' => trim($m[2]),
                'url' => isset($urls[$i]) ? $urls[$i] : ''
            );
        }
    }
}

// Charger les résumés (dans le dossier racine du projet)
$resumes = array();
// Chercher d'abord dans resumes/, sinon dans le dossier racine
$resumes_dir = $project_path . '/resumes';
if (!is_dir($resumes_dir)) {
    $resumes_dir = $project_path;
}
$files = scandir($resumes_dir);
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..' && preg_match('/resume[-_]?(\d+)(?:[-_]|$)/i', $file, $m)) {
        $resumes[$m[1]] = $file;
    }
}

// Charger les sources non pertinentes
$non_pertinentes = array();
$non_pertinentes_dir = $project_path . '/non-pertinentes';
if (is_dir($non_pertinentes_dir)) {
    $non_pertinentes_sources_file = $non_pertinentes_dir . '/sources.md';
    if (file_exists($non_pertinentes_sources_file)) {
        $content = file_get_contents($non_pertinentes_sources_file);
        
        // Parser le même format que les sources principales
        preg_match_all('/(?:^|\n)\s*(?:\d+\.|\[?(\d+)\]?)\s+(?:\*\*)?([^\n*]+)(?:\*\*)?\s*(?:-|URL:)\s*(https?:\/\/[^\s\n]+)/mi', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $id = !empty($match[1]) ? $match[1] : (isset($match[2]) ? $match[2] : '');
            $titre = trim(!empty($match[2]) ? $match[2] : (isset($match[3]) ? $match[3] : ''));
            $url = isset($match[3]) ? $match[3] : '';
            
            if ($id && $titre && $url) {
                $non_pertinentes[] = array(
                    'id' => $id,
                    'titre' => trim($titre),
                    'url' => $url
                );
            }
        }
    }
}

$all_sources = $sources;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project_name); ?> - Gestionnaire de Recherche</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Gestionnaire de Recherche</h1>
            <a href="index.php" class="btn btn-secondary">← Retour à l'index</a>
        </header>
        
        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!is_dir($project_path)): ?>
            <div class="message message-error">
                Le projet "<?php echo htmlspecialchars($project_name); ?>" n'existe pas.
            </div>
        <?php else: ?>
            
            <!-- Détail du projet -->
            <div class="project-detail">
                <div class="project-header">
                    <h2><?php echo htmlspecialchars($project_name); ?></h2>
                    <?php if (is_authenticated()): ?>
                        <a href="edit_projet.php?projet=<?php echo urlencode($project_name); ?>" class="btn btn-primary">✏️ Modifier</a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($project_description)): ?>
                    <div class="project-description">
                        <?php echo nl2br(htmlspecialchars($project_description)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Liste des sources pertinentes -->
            <div class="section">
                <h3>Sources (<?php echo count($sources); ?>)</h3>
                
                <?php if (empty($sources)): ?>
                    <p class="no-items">Aucune source trouvée.</p>
                <?php else: ?>
                    <table class="sources-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Titre</th>
                                <th>URL</th>
                                <th>Badge</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sources as $source): ?>
                                <tr>
                                    <td><?php echo $source['id']; ?></td>
                                    <td class="source-titre"><?php echo htmlspecialchars($source['titre']); ?></td>
                                    <td class="source-url">
                                        <a href="<?php echo htmlspecialchars($source['url']); ?>" target="_blank" rel="noopener">
                                            <?php echo htmlspecialchars($source['url']); ?>
                                        </a>
                                    </td>
                                    <td><span class="badge badge-pertinent">Pertinent</span></td>
                                    <td class="actions">
                                        <a href="source.php?projet=<?php echo urlencode($project_name); ?>&amp;id=<?php echo $source['id']; ?>" class="btn btn-small btn-primary">Voir contenu</a>
                                        <?php if (isset($resumes[$source['id']])): ?>
                                            <a href="source.php?projet=<?php echo urlencode($project_name); ?>&amp;id=<?php echo $source['id']; ?>&amp;view=resume" class="btn btn-small btn-secondary">Voir résumé</a>
                                        <?php endif; ?>
                                        <form method="post" action="" style="display:inline;">
                                            <input type="hidden" name="action" value="flagger">
                                            <input type="hidden" name="source_id" value="<?php echo $source['id']; ?>">
                                            <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Flagguer cette source comme non pertinente?');">Non pertinente</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Liste des résumés -->
            <div class="section">
                <h3>Résumés (<?php echo count($resumes); ?>)</h3>
                
                <?php if (empty($resumes)): ?>
                    <p class="no-items">Aucun résumé trouvé.</p>
                <?php else: ?>
                    <ul class="resumes-list">
                        <?php foreach ($resumes as $id => $file): ?>
                            <li>
                                <strong>Source #<?php echo $id; ?></strong> - 
                                <a href="source.php?projet=<?php echo urlencode($project_name); ?>&amp;id=<?php echo $id; ?>&amp;view=resume">Voir le résumé</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <!-- Sources non pertinentes -->
            <?php if (!empty($non_pertinentes)): ?>
                <div class="section">
                    <h3>Sources non pertinentes (<?php echo count($non_pertinentes); ?>)</h3>
                    
                    <table class="sources-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Titre</th>
                                <th>URL</th>
                                <th>Badge</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($non_pertinentes as $source): ?>
                                <tr>
                                    <td><?php echo $source['id']; ?></td>
                                    <td class="source-titre"><?php echo htmlspecialchars($source['titre']); ?></td>
                                    <td class="source-url">
                                        <a href="<?php echo htmlspecialchars($source['url']); ?>" target="_blank" rel="noopener">
                                            <?php echo htmlspecialchars($source['url']); ?>
                                        </a>
                                    </td>
                                    <td><span class="badge badge-non-pertinent">Non pertinent</span></td>
                                    <td class="actions">
                                        <form method="post" action="" style="display:inline;">
                                            <input type="hidden" name="action" value="restaurer">
                                            <input type="hidden" name="source_id" value="<?php echo $source['id']; ?>">
                                            <button type="submit" class="btn btn-small btn-primary" onclick="return confirm('Restaurer cette source?');">Restaurer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
    
    <style>
        .project-detail {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .project-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .project-detail h2 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .project-description {
            color: #555;
            line-height: 1.7;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .no-items {
            color: #999;
            font-style: italic;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        /* Table des sources */
        .sources-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .sources-table thead {
            background: #f8f9fa;
        }
        
        .sources-table th,
        .sources-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .sources-table th {
            font-weight: 600;
            color: #555;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .sources-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .source-titre {
            font-weight: 500;
            max-width: 250px;
        }
        
        .source-url {
            font-size: 12px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .source-url a {
            color: #3498db;
            text-decoration: none;
        }
        
        .source-url a:hover {
            text-decoration: underline;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pertinent {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-non-pertinent {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Liste des résumés */
        .resumes-list {
            list-style: none;
            padding: 0;
        }
        
        .resumes-list li {
            padding: 10px 15px;
            background: #f8f9fa;
            margin-bottom: 8px;
            border-radius: 4px;
            border-left: 3px solid #3498db;
        }
        
        .resumes-list a {
            color: #3498db;
            text-decoration: none;
        }
        
        .resumes-list a:hover {
            text-decoration: underline;
        }
        
        /* Bouton danger */
        .btn-danger {
            background: #e74c3c;
            color: #fff;
            border: none;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        /* Actions */
        .actions {
            white-space: nowrap;
        }
        
        .actions .btn,
        .actions form {
            margin-right: 5px;
        }
        
        .actions .btn:last-child {
            margin-right: 0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sources-table {
                font-size: 12px;
            }
            
            .sources-table th,
            .sources-table td {
                padding: 8px;
            }
            
            .source-titre {
                max-width: 120px;
            }
            
            .source-url {
                display: none;
            }
            
            .actions {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .actions .btn {
                margin-right: 0;
                margin-bottom: 3px;
            }
        }
    </style>
</body>
</html>
