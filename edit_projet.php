<?php
/**
 * Gestionnaire de Recherche - Édition de Projet
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Vérifier l'authentification
if (!is_authenticated()) {
    header('Location: login.php');
    exit;
}

// Vérifier si le paramètre projet est présent
if (!isset($_GET['projet']) || empty($_GET['projet'])) {
    header('Location: index.php');
    exit;
}

$project_name = $_GET['projet'];
$project_path = DATA_PATH . $project_name;

// Variables pour le formulaire
$form_titre = $project_name;
$form_description = '';
$form_contenu = '';
$form_categorie = '';
$form_tags = '';

// Messages
$message = '';
$message_type = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $form_titre = isset($_POST['titre']) ? $_POST['titre'] : $project_name;
    $form_description = isset($_POST['description']) ? $_POST['description'] : '';
    $form_contenu = isset($_POST['contenu']) ? $_POST['contenu'] : '';
    $form_categorie = isset($_POST['categorie']) ? $_POST['categorie'] : '';
    $form_tags = isset($_POST['tags']) ? $_POST['tags'] : '';
    
    // Créer le dossier du projet si nécessaire
    if (!is_dir($project_path)) {
        mkdir($project_path, 0755, true);
    }
    
    // Générer le contenu du fichier definition.md
    $definition_content = "---\n";
    $definition_content .= "titre: " . trim($form_titre) . "\n";
    $definition_content .= "description: " . trim($form_description) . "\n";
    $definition_content .= "categorie: " . trim($form_categorie) . "\n";
    $definition_content .= "tags: " . trim($form_tags) . "\n";
    $definition_content .= "---\n\n";
    $definition_content .= "# " . trim($form_titre) . "\n\n";
    $definition_content .= trim($form_contenu);
    
    // Sauvegarder le fichier
    $definition_file = $project_path . '/definition.md';
    if (file_put_contents($definition_file, $definition_content) !== false) {
        $message = 'Projet modifié avec succès!';
        $message_type = 'success';
        
        // Redirection vers projet.php après sauvegarde
        header('Location: projet.php?projet=' . urlencode($project_name));
        exit;
    } else {
        $message = 'Erreur lors de la sauvegarde du projet.';
        $message_type = 'error';
    }
} else {
    // Charger les données existantes du projet
    if (is_dir($project_path)) {
        $definition_file = $project_path . '/definition.md';
        if (file_exists($definition_file)) {
            $content = file_get_contents($definition_file);
            
            // Extraire les métadonnées YAML
            if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
                $yaml = $matches[1];
                
                // Parser chaque champ
                if (preg_match('/titre:\s*(.*)$/m', $yaml, $m)) {
                    $form_titre = trim($m[1]);
                }
                if (preg_match('/description:\s*(.*)$/m', $yaml, $m)) {
                    $form_description = trim($m[1]);
                }
                if (preg_match('/categorie:\s*(.*)$/m', $yaml, $m)) {
                    $form_categorie = trim($m[1]);
                }
                if (preg_match('/tags:\s*(.*)$/m', $yaml, $m)) {
                    $form_tags = trim($m[1]);
                }
            }
            
            // Extraire le contenu après le titre markdown
            if (preg_match('/^#\s+.+\s*\n(.*)$/s', $content, $matches)) {
                $form_contenu = trim($matches[1]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier <?php echo htmlspecialchars($project_name); ?> - Gestionnaire de Recherche</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Gestionnaire de Recherche</h1>
            <a href="projet.php?projet=<?php echo urlencode($project_name); ?>" class="btn btn-secondary">← Retour au projet</a>
        </header>
        
        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!is_dir($project_path) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <div class="message message-error">
                Le projet "<?php echo htmlspecialchars($project_name); ?>" n'existe pas. Il sera créé lors de la sauvegarde.
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <h2>Modifier le projet</h2>
            
            <form method="POST" action="edit_projet.php?projet=<?php echo urlencode($project_name); ?>">
                <input type="hidden" name="action" value="save">
                
                <div class="form-group">
                    <label for="titre">Nom du projet:</label>
                    <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($form_titre); ?>" readonly style="background-color: #e9ecef; cursor: not-allowed;">
                    <small>Le nom du projet ne peut pas être modifié.</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Description courte:</label>
                    <input type="text" id="description" name="description" value="<?php echo htmlspecialchars($form_description); ?>" placeholder="Une brève description du projet">
                </div>
                
                <div class="form-group">
                    <label for="categorie">Catégorie:</label>
                    <input type="text" id="categorie" name="categorie" value="<?php echo htmlspecialchars($form_categorie); ?>" placeholder="Ex: Formation, Recherche, Projet">
                </div>
                
                <div class="form-group">
                    <label for="tags">Tags:</label>
                    <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars($form_tags); ?>" placeholder="Ex: IA, Coding, Tools (séparés par des virgules)">
                </div>
                
                <div class="form-group">
                    <label for="contenu">Contenu:</label>
                    <textarea id="contenu" name="contenu" rows="15" placeholder="Contenu principal du projet..."><?php echo htmlspecialchars($form_contenu); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    <a href="projet.php?projet=<?php echo urlencode($project_name); ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .form-container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-container h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-group textarea {
            font-family: 'Courier New', monospace;
            line-height: 1.6;
            resize: vertical;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .form-actions .btn {
            padding: 12px 25px;
        }
    </style>
</body>
</html>
