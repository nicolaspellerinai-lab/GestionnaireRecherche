<?php
/**
 * Gestionnaire de Recherche - Index
 * PHP 5.6 Compatible
 */

require_once 'config.php';

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

if (is_dir(DATA_PATH)) {
    $dirs = scandir(DATA_PATH);
    
    foreach ($dirs as $dir) {
        if ($dir !== '.' && $dir !== '..' && is_dir(DATA_PATH . $dir)) {
            $project_path = DATA_PATH . $dir;
            
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
            
            <?php if (empty($projects)): ?>
                <p class="no-projects">Aucun projet trouvé. Importez un projet ZIP pour commencer.</p>
            <?php else: ?>
                <table class="projects-table">
                    <thead>
                        <tr>
                            <th>Projet</th>
                            <th>Date de modification</th>
                            <th>Sources</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td class="project-name">
                                    <strong><?php echo htmlspecialchars($project['name']); ?></strong>
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
