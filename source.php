<?php
/**
 * Gestionnaire de Recherche - Vue Source
 * PHP 5.6 Compatible
 * Mode fichier uniquement (sans base de données)
 */

// Buffer output to prevent session errors
ob_start();

require_once 'config.php';

// Vérification des paramètres
$projet = isset($_GET['projet']) ? trim($_GET['projet']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($projet) || $id <= 0) {
    die('Paramètres invalides.');
}

// Chemins - supporter plusieurs formats de fichiers
$project_dir = DATA_PATH . $projet . '/';
$sources_dir = $project_dir . 'sources/';
$source_file = $sources_dir . sprintf('%02d', $id) . '.md';
$resume_file = $project_dir . 'resume-' . sprintf('%02d', $id) . '.md';

// Lire les métadonnées depuis sources.md
$sources_file = $project_dir . 'sources.md';
$source_meta = array('titre' => 'Source #' . $id, 'url' => '');
if (file_exists($sources_file)) {
    $content = file_get_contents($sources_file);
    // Parser le format: 1. **Titre** - URL: https://...
    preg_match('/' . $id . '\.\s+\*\*([^*]+)\*\*[^-]*- URL:\s*(https?:\/\/[^\s]+)/', $content, $matches);
    if (!empty($matches[1])) {
        $source_meta['titre'] = trim($matches[1]);
    }
    if (!empty($matches[2])) {
        $source_meta['url'] = $matches[2];
    }
}

// Pas de connexion BD nécessaire - mode fichier uniquement

// Traitement des actions (tout en fichiers)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_authenticated()) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_resume') {
            // Mise à jour du résumé
            $resume_content = $_POST['resume'];
            
            // Sauvegarder dans le fichier
            if (!is_dir($project_dir)) {
                mkdir($project_dir, 0755, true);
            }
            
            if (file_put_contents($resume_file, $resume_content) !== false) {
                $message = 'Résumé mis à jour avec succès!';
                $message_type = 'success';
            } else {
                $message = 'Erreur lors de la sauvegarde du résumé.';
                $message_type = 'error';
            }
        } elseif ($_POST['action'] === 'delete') {
            // Supprimer les fichiers
            $deleted = false;
            if (file_exists($source_file)) {
                unlink($source_file);
                $deleted = true;
            }
            if (file_exists($resume_file)) {
                unlink($resume_file);
                $deleted = true;
            }
            
            if ($deleted) {
                header('Location: projet.php?projet=' . urlencode($projet));
                exit;
            } else {
                $message = 'Erreur lors de la suppression.';
                $message_type = 'error';
            }
        }
    }
}

// Lire le contenu de la source
$contenu_source = '';
if (file_exists($source_file)) {
    $contenu_source = file_get_contents($source_file);
    $contenu_source = renderMarkdown($contenu_source);
} else {
    $contenu_source = 'Fichier source introuvable. Tried: ' . htmlspecialchars($source_file);
}

// Lire le résumé
$resume = '';
if (file_exists($resume_file)) {
    $resume = file_get_contents($resume_file);
    $resume = renderMarkdown($resume);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Source #<?php echo $id; ?> - <?php echo htmlspecialchars($projet); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .source-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }
        
        @media (max-width: 900px) {
            .source-container {
                grid-template-columns: 1fr;
            }
        }
        
        .source-content {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
        }
        
        .source-content h2 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .source-text {
            white-space: pre-wrap;
            line-height: 1.8;
            max-height: 600px;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .resume-panel {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
        }
        
        .resume-panel h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .metadata {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .metadata-item {
            display: flex;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .metadata-label {
            font-weight: 600;
            color: #555;
            width: 80px;
            flex-shrink: 0;
        }
        
        .metadata-value {
            color: #333;
            word-break: break-all;
        }
        
        .metadata-value a {
            color: #3498db;
            text-decoration: none;
        }
        
        .metadata-value a:hover {
            text-decoration: underline;
        }
        
        .resume-form {
            margin-top: 15px;
        }
        
        .resume-form textarea {
            width: 100%;
            min-height: 150px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
        }
        
        .resume-form textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-back {
            background: #95a5a6;
            color: #fff;
        }
        
        .btn-back:hover {
            background: #7f8c8d;
        }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .actions-bar h1 {
            font-size: 22px;
            color: #2c3e50;
        }
        
        .source-actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="actions-bar">
            <h1>Source #<?php echo $id; ?></h1>
            <div class="source-actions">
                <a href="projet.php?id=<?php echo urlencode($projet); ?>" class="btn btn-back">← Retour au Projet</a>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="source-container">
            <!-- Contenu de la source -->
            <div class="source-content">
                <h2>Contenu de la source</h2>
                <div class="source-text"><?php echo $contenu_source; ?></div>
            </div>
            
            <!-- Panneau résumé et métadonnées -->
            <div class="resume-panel">
                <h3>Métadonnées</h3>
                <div class="metadata">
                    <?php if (!empty($source_meta)): ?>
                        <div class="metadata-item">
                            <span class="metadata-label">Titre:</span>
                            <span class="metadata-value"><?php echo htmlspecialchars($source_meta['titre']); ?></span>
                        </div>
                        <?php if (!empty($source_meta['url'])): ?>
                            <div class="metadata-item">
                                <span class="metadata-label">URL:</span>
                                <span class="metadata-value">
                                    <a href="<?php echo htmlspecialchars($source_meta['url']); ?>" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($source_meta['url']); ?>
                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>
                                <span class="metadata-label">Date:</span>
                                <span class="metadata-value"><?php echo htmlspecialchars($source['date_ajout']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($source['auteur'])): ?>
                            <div class="metadata-item">
                                <span class="metadata-label">Auteur:</span>
                                <span class="metadata-value"><?php echo htmlspecialchars($source['auteur']); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="metadata-item">
                            <span class="metadata-label">Projet:</span>
                            <span class="metadata-value"><?php echo htmlspecialchars($projet); ?></span>
                        </div>
                        <div class="metadata-item">
                            <span class="metadata-label">ID:</span>
                            <span class="metadata-value"><?php echo $id; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h3>Résumé</h3>
                <?php if (is_authenticated()): ?>
                    <form method="post" action="" class="resume-form">
                        <input type="hidden" name="action" value="update_resume">
                        <textarea name="resume" placeholder="Entrez le résumé de cette source..."><?php echo htmlspecialchars($resume); ?></textarea>
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">Enregistrer le résumé</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="source-text" style="max-height: 200px; overflow-y: auto;">
                        <?php echo $resume; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (is_authenticated()): ?>
                    <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
                        <h3>Actions</h3>
                        <div class="action-buttons">
                            <a href="edit_source.php?projet=<?php echo urlencode($projet); ?>&id=<?php echo $id; ?>" class="btn btn-secondary">Modifier le résumé</a>
                            <form method="post" action="" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette source?');">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger">Supprimer</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
