<?php
/**
 * Gestionnaire de Recherche - Index
 * PHP 5.6 Compatible
 */

require_once 'config.php';

/**
 * Parse le YAML front matter d'un fichier definition.md
 * @param string $content
 * @return array
 */
function parse_definition_metadata($content) {
    $metadata = array(
        'titre' => '',
        'description' => '',
        'categorie' => '',
        'tags' => array(),
        'date_creation' => ''
    );
    
    // Extraire le block YAML
    if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
        $yaml = $matches[1];
        
        // Parser chaque champ
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
            // Parser les tags (séparés par virgules)
            $metadata['tags'] = array_map('trim', explode(',', $tags_str));
            $metadata['tags'] = array_filter($metadata['tags']);
        }
        if (preg_match('/date_creation:\s*(.*)$/m', $yaml, $m)) {
            $metadata['date_creation'] = trim($m[1]);
        }
    }
    
    return $metadata;
}

// Récupérer les filtres depuis l'URL
$filter_tag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$filter_categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';

// Traitement de l'upload ZIP
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        // Authentification
        if (authenticate($_POST['password'])) {
            $message = 'Connecté avec succès!';
            $message_type = 'success';
        } else {
            $message = 'Mot de passe incorrect.';
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'upload' && is_authenticated()) {
        // Upload du fichier ZIP
        if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
            $project_name = trim($_POST['project_name']);
            
            if (empty($project_name)) {
                $message = 'Veuillez entrer un nom de projet.';
                $message_type = 'error';
            } else {
                // Sécuriser le nom du projet
                $project_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project_name);
                $project_dir = DATA_PATH . $project_name;
                
                // Créer le dossier du projet
                if (!is_dir(DATA_PATH)) {
                    mkdir(DATA_PATH, 0755, true);
                }
                
                if (!is_dir($project_dir)) {
                    mkdir($project_dir, 0755, true);
                }
                
                // Extraire le ZIP de manière sécurisée (comme importer.php)
                $zip = new ZipArchive();
                $zip_result = $zip->open($_FILES['zip_file']['tmp_name']);
                
                if ($zip_result !== true) {
                    $message = 'Impossible d\'ouvrir l\'archive ZIP.';
                    $message_type = 'error';
                } else {
                    // Créer un dossier temporaire pour l'extraction
                    $temp_dir = DATA_PATH . 'temp_import_' . time();
                    if (!mkdir($temp_dir, 0755, true)) {
                        $message = 'Impossible de créer le dossier temporaire.';
                        $message_type = 'error';
                        $zip->close();
                    } else {
                        // Extraire dans le dossier temporaire
                        if (!$zip->extractTo($temp_dir)) {
                            $message = 'Erreur lors de l\'extraction de l\'archive.';
                            $message_type = 'error';
                            $zip->close();
                            @rmdir($temp_dir);
                        } else {
                            $zip->close();
                            
                            // Chercher le dossier du projet (premier niveau)
                            $project_folders = glob($temp_dir . '/*', GLOB_ONLYDIR);
                            
                            if (empty($project_folders)) {
                                $message = 'L\'archive ne contient aucun dossier de projet.';
                                $message_type = 'error';
                                @array_map('unlink', glob($temp_dir . '/*'));
                                @rmdir($temp_dir);
                            } else {
                                $extracted_project_path = $project_folders[0];
                                $extracted_project_name = basename($extracted_project_path);
                                
                                // Vérifier la structure requise
                                $definition_file = $extracted_project_path . '/definition.md';
                                $sources_file = $extracted_project_path . '/sources.md';
                                
                                if (!file_exists($definition_file)) {
                                    $message = 'Le fichier definition.md est requis mais est absent.';
                                    $message_type = 'error';
                                    @array_map('unlink', glob($extracted_project_path . '/*'));
                                    @rmdir($extracted_project_path);
                                    @array_map('unlink', glob($temp_dir . '/*'));
                                    @rmdir($temp_dir);
                                } elseif (!file_exists($sources_file)) {
                                    $message = 'Le fichier sources.md est requis mais est absent.';
                                    $message_type = 'error';
                                    @array_map('unlink', glob($extracted_project_path . '/*'));
                                    @rmdir($extracted_project_path);
                                    @array_map('unlink', glob($temp_dir . '/*'));
                                    @rmdir($temp_dir);
                                } else {
                                    // Nettoyer le dossier destination si nécessaire
                                    if (is_dir($project_dir)) {
                                        @array_map('unlink', glob($project_dir . '/*/*'));
                                        @array_map('unlink', glob($project_dir . '/*'));
                                        @rmdir($project_dir);
                                    }
                                    
                                    // Déplacer le contenu vers le dossier final
                                    if (!rename($extracted_project_path, $project_dir)) {
                                        $message = 'Erreur lors du déplacement du projet.';
                                        $message_type = 'error';
                                        if (is_dir($extracted_project_path)) {
                                            @array_map('unlink', glob($extracted_project_path . '/*/*'));
                                            @array_map('unlink', glob($extracted_project_path . '/*'));
                                            @rmdir($extracted_project_path);
                                        }
                                        @array_map('unlink', glob($temp_dir . '/*'));
                                        @rmdir($temp_dir);
                                    } else {
                                        // Nettoyer le dossier temporaire
                                        @array_map('unlink', glob($temp_dir . '/*'));
                                        @rmdir($temp_dir);
                                        
                                        $message = 'Projet "' . htmlspecialchars($project_name) . '" importé avec succès!';
                                        $message_type = 'success';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $message = 'Erreur lors de l\'upload du fichier.';
            $message_type = 'error';
        }
    }
}

// Déconnexion
if (isset($_GET['logout'])) {
    logout();
    $message = 'Déconnecté avec succès.';
    $message_type = 'success';
}

// Message de suppression ou autre
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

// Récupérer la liste des projets
$projects = array();
$all_tags = array();
$all_categories = array();

if (is_dir(DATA_PATH)) {
    $dirs = scandir(DATA_PATH);
    
    foreach ($dirs as $dir) {
        if ($dir !== '.' && $dir !== '..' && is_dir(DATA_PATH . $dir)) {
            $project_path = DATA_PATH . $dir;
            
            // Lire les métadonnées du projet
            $metadata = array(
                'titre' => $dir,
                'description' => '',
                'categorie' => '',
                'tags' => array()
            );
            
            $definition_file = $project_path . '/definition.md';
            if (file_exists($definition_file)) {
                $content = file_get_contents($definition_file);
                $metadata = parse_definition_metadata($content);
                if (empty($metadata['titre'])) {
                    $metadata['titre'] = $dir;
                }
            }
            
            // Collecter tous les tags et catégories
            foreach ($metadata['tags'] as $tag) {
                if (!empty($tag)) {
                    $all_tags[$tag] = true;
                }
            }
            if (!empty($metadata['categorie'])) {
                $all_categories[$metadata['categorie']] = true;
            }
            
            // Appliquer les filtres
            $include_project = true;
            
            // Filtre par tag
            if (!empty($filter_tag)) {
                if (!in_array($filter_tag, $metadata['tags'])) {
                    $include_project = false;
                }
            }
            
            // Filtre par catégorie
            if (!empty($filter_categorie)) {
                if (strcasecmp($metadata['categorie'], $filter_categorie) !== 0) {
                    $include_project = false;
                }
            }
            
            // Compter les fichiers sources (dans le dossier sources/)
            $source_count = 0;
            $sources_dir = $project_path . '/sources';
            if (is_dir($sources_dir)) {
                $source_files = scandir($sources_dir);
                foreach ($source_files as $file) {
                    if ($file !== '.' && $file !== '..' && !is_dir($sources_dir . '/' . $file)) {
                        $source_count++;
                    }
                }
            }
            
            // Obtenir la date de modification
            $mod_time = filemtime($project_path);
            $mod_date = date('d/m/Y H:i', $mod_time);
            
            $projects[] = array(
                'name' => $dir,
                'titre' => $metadata['titre'],
                'description' => $metadata['description'],
                'categorie' => $metadata['categorie'],
                'tags' => $metadata['tags'],
                'date' => $mod_date,
                'source_count' => $source_count,
                'path' => $project_path
            );
        }
    }
    
    // Trier par date de modification (plus récent en premier)
    usort($projects, function($a, $b) {
        return filemtime($b['path']) - filemtime($a['path']);
    });
}

// Trier les tags et catégories
ksort($all_tags);
ksort($all_categories);
$all_tags = array_keys($all_tags);
$all_categories = array_keys($all_categories);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire de Recherche</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Gestionnaire de Recherche</h1>
            <?php if (is_authenticated()): ?>
                <a href="?logout=1" class="btn-logout">Déconnexion</a>
            <?php endif; ?>
        </header>
        
        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!is_authenticated()): ?>
            <!-- Formulaire de connexion -->
            <div class="login-form">
                <h2>Connexion pour importer un projet</h2>
                <form method="post" action="">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="password">Mot de passe:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Se connecter</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Formulaire d'upload -->
            <div class="upload-form">
                <h2>Importer un nouveau projet (ZIP)</h2>
                <form method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <div class="form-group">
                        <label for="project_name">Nom du projet:</label>
                        <input type="text" id="project_name" name="project_name" required placeholder="mon_projet">
                    </div>
                    <div class="form-group">
                        <label for="zip_file">Fichier ZIP:</label>
                        <input type="file" id="zip_file" name="zip_file" accept=".zip" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Importer le projet</button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Liste des projets -->
        <div class="projects-section">
            <h2>Projets (<?php echo count($projects); ?>)</h2>
            
            <!-- Filtres par tag et catégorie -->
            <?php if (!empty($all_tags) || !empty($all_categories)): ?>
            <div class="filters-section">
                <form method="get" action="" class="filters-form">
                    <div class="filter-group">
                        <label for="filter-tag">Tag:</label>
                        <select id="filter-tag" name="tag" onchange="this.form.submit()">
                            <option value="">Tous les tags</option>
                            <?php foreach ($all_tags as $tag): ?>
                                <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo ($filter_tag === $tag) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tag); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-categorie">Catégorie:</label>
                        <select id="filter-categorie" name="categorie" onchange="this.form.submit()">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_categorie === $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($filter_tag) || !empty($filter_categorie)): ?>
                        <a href="index.php" class="btn btn-small btn-secondary">✕ Effacer les filtres</a>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if (empty($projects)): ?>
                <p class="no-projects">Aucun projet trouvé. Importez un projet ZIP pour commencer.</p>
            <?php else: ?>
                <table class="projects-table">
                    <thead>
                        <tr>
                            <th>Projet</th>
                            <th>Catégorie</th>
                            <th>Tags</th>
                            <th>Date de modification</th>
                            <th>Sources</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td class="project-name">
                                    <strong><?php echo htmlspecialchars($project['titre'] ?: $project['name']); ?></strong>
                                    <?php if (!empty($project['description'])): ?>
                                        <br><small class="project-desc"><?php echo htmlspecialchars($project['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($project['categorie'])): ?>
                                        <a href="?categorie=<?php echo urlencode($project['categorie']); ?>" class="category-link">
                                            <?php echo htmlspecialchars($project['categorie']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="no-category">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="project-tags">
                                    <?php if (!empty($project['tags'])): ?>
                                        <?php foreach ($project['tags'] as $tag): ?>
                                            <a href="?tag=<?php echo urlencode($tag); ?>" class="tag-link"><?php echo htmlspecialchars($tag); ?></a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="no-tags">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($project['date']); ?></td>
                                <td class="source-count"><?php echo $project['source_count']; ?> fichier(s)</td>
                                <td class="actions">
                                    <a href="projet.php?projet=<?php echo urlencode($project['name']); ?>" class="btn btn-small">Voir projet</a>
                                    <a href="export.php?project=<?php echo urlencode($project['name']); ?>" class="btn btn-small btn-secondary">Exporter ZIP</a>
                                    <a href="supprimer.php?projet=<?php echo urlencode($project['name']); ?>" class="btn btn-small btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer le projet \"<?php echo htmlspecialchars($project['name']); ?>\" ? Cette action est irréversible.');">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<style>
    /* Filtres */
    .filters-section {
        background: #f8f9fa;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .filters-form {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .filter-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filter-group label {
        font-weight: 600;
        color: #555;
        font-size: 14px;
    }
    
    .filter-group select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        min-width: 150px;
        cursor: pointer;
    }
    
    .filter-group select:focus {
        outline: none;
        border-color: #3498db;
    }
    
    /* Tags dans le tableau */
    .project-tags {
        white-space: nowrap;
    }
    
    .tag-link {
        display: inline-block;
        background: #e3f2fd;
        color: #1976d2;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        margin: 2px;
        text-decoration: none;
        transition: background 0.2s;
    }
    
    .tag-link:hover {
        background: #bbdefb;
    }
    
    .category-link {
        color: #7b1fa2;
        text-decoration: none;
        font-weight: 500;
    }
    
    .category-link:hover {
        text-decoration: underline;
    }
    
    .no-tags, .no-category {
        color: #999;
        font-style: italic;
    }
    
    .project-desc {
        color: #666;
        font-weight: normal;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .filters-form {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-group select {
            width: 100%;
        }
    }
</style>
