<?php
/**
 * Importeur de projet ZIP - Gestionnaire de Recherche
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Vérification d'authentification
if (!is_authenticated()) {
    // Afficher le formulaire de mot de passe
    $show_password_form = true;
} else {
    $show_password_form = false;
}

// Traitement du formulaire de mot de passe
$password_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_password') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($password)) {
        $password_error = 'Veuillez entrer un mot de passe.';
    } elseif (!authenticate($password)) {
        $password_error = 'Mot de passe incorrect.';
    } else {
        $show_password_form = false;
    }
}

// Traitement de l'upload ZIP
$upload_error = '';
$upload_success = '';
if (!$show_password_form && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_file'])) {
    $zip_file = $_FILES['zip_file'];
    
    // Vérification de l'erreur d'upload
    if ($zip_file['error'] !== UPLOAD_ERR_OK) {
        switch ($zip_file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $upload_error = 'Le fichier dépasse la taille maximale allowed.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $upload_error = 'Le fichier a été partiellement uploadé.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $upload_error = 'Aucun fichier n\'a été sélectionné.';
                break;
            default:
                $upload_error = 'Erreur lors de l\'upload du fichier.';
        }
    } else {
        // Vérification de l'extension
        $allowed_extensions = array('zip');
        $file_ext = strtolower(pathinfo($zip_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $upload_error = 'Seuls les fichiers ZIP sont acceptés.';
        } else {
            // Vérification du type MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $zip_file['tmp_name']);
            finfo_close($finfo);
            
            if ($mime_type !== 'application/zip' && $mime_type !== 'application/x-zip-compressed') {
                $upload_error = 'Le fichier doit être une archive ZIP valide.';
            } else {
                // Créer le dossier data si nécessaire
                ensure_data_directory();
                
                // Extraire le ZIP
                $zip = new ZipArchive();
                $zip_result = $zip->open($zip_file['tmp_name']);
                
                if ($zip_result !== true) {
                    $upload_error = 'Impossible d\'ouvrir l\'archive ZIP. Code d\'erreur: ' . $zip_result;
                } else {
                    // Créer un dossier temporaire pour l'extraction
                    $temp_dir = DATA_PATH . 'temp_import_' . time();
                    if (!mkdir($temp_dir, 0755, true)) {
                        $upload_error = 'Impossible de créer le dossier temporaire.';
                        $zip->close();
                    } else {
                        // Extraire dans le dossier temporaire
                        if (!$zip->extractTo($temp_dir)) {
                            $upload_error = 'Erreur lors de l\'extraction de l\'archive.';
                            $zip->close();
                            @rmdir($temp_dir);
                        } else {
                            $zip->close();
                            
                            // Chercher le dossier du projet (premier niveau)
                            $project_folders = glob($temp_dir . '/*', GLOB_ONLYDIR);
                            
                            if (empty($project_folders)) {
                                $upload_error = 'L\'archive ne contient aucun dossier de projet.';
                                @array_map('unlink', glob($temp_dir . '/*'));
                                @rmdir($temp_dir);
                            } else {
                                $project_path = $project_folders[0];
                                $project_name = basename($project_path);
                                
                                // Vérifier la structure requise
                                $definition_file = $project_path . '/definition.md';
                                $sources_file = $project_path . '/sources.md';
                                
                                if (!file_exists($definition_file)) {
                                    $upload_error = 'Le fichier definition.md est requis mais est absent.';
                                    @array_map('unlink', glob($project_path . '/*'));
                                    @rmdir($project_path);
                                    @array_map('unlink', glob($temp_dir . '/*'));
                                    @rmdir($temp_dir);
                                } elseif (!file_exists($sources_file)) {
                                    $upload_error = 'Le fichier sources.md est requis mais est absent.';
                                    @array_map('unlink', glob($project_path . '/*'));
                                    @rmdir($project_path);
                                    @array_map('unlink', glob($temp_dir . '/*'));
                                    @rmdir($temp_dir);
                                } else {
                                    // Déplacer vers le dossier data
                                    $final_path = DATA_PATH . $project_name;
                                    
                                    // Vérifier si le projet existe déjà
                                    if (is_dir($final_path)) {
                                        // Ajouter un suffixe numérique
                                        $counter = 1;
                                        while (is_dir(DATA_PATH . $project_name . '_' . $counter)) {
                                            $counter++;
                                        }
                                        $project_name = $project_name . '_' . $counter;
                                        $final_path = DATA_PATH . $project_name;
                                    }
                                    
                                    // Nettoyer le dossier temporaire et déplacer
                                    @array_map('unlink', glob($temp_dir . '/*'));
                                    @rmdir($temp_dir);
                                    
                                    if (!rename($project_path, $final_path)) {
                                        $upload_error = 'Erreur lors du déplacement du projet.';
                                        if (is_dir($project_path)) {
                                            @array_map('unlink', glob($project_path . '/*/*'));
                                            @array_map('unlink', glob($project_path . '/*'));
                                            @rmdir($project_path);
                                        }
                                    } else {
                                        $upload_success = 'Projet "' . htmlspecialchars($project_name) . '" importé avec succès!';
                                    }
                                }
                            }
                        }
                    }
                }
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
    <title>Importer un projet - Gestionnaire de Recherche</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📦 Importer un projet</h1>
            <nav>
                <a href="index.php">← Retour à l'accueil</a>
                <?php if (is_authenticated()): ?>
                    | <a href="logout.php">Déconnexion</a>
                <?php endif; ?>
            </nav>
        </header>
        
        <main>
            <?php if ($show_password_form): ?>
                <div class="card">
                    <h2>🔐 Authentication requise</h2>
                    <p>Veuillez entrer le mot de passe pour importer un projet.</p>
                    
                    <?php if (!empty($password_error)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($password_error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="verify_password">
                        <div class="form-group">
                            <label for="password">Mot de passe:</label>
                            <input type="password" id="password" name="password" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary">Valider</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2>📁 Upload d'archive ZIP</h2>
                    <p>Téléchargez une archive ZIP contenant votre projet de recherche.</p>
                    
                    <div class="info-box">
                        <strong>Structure requise:</strong>
                        <ul>
                            <li>Le ZIP doit contenir un dossier principal (nom du projet)</li>
                            <li>Le dossier doit contenir <code>definition.md</code> (obligatoire)</li>
                            <li>Le dossier doit contenir <code>sources.md</code> (obligatoire)</li>
                        </ul>
                    </div>
                    
                    <?php if (!empty($upload_error)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($upload_error); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($upload_success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($upload_success); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="zip_file">Fichier ZIP:</label>
                            <input type="file" id="zip_file" name="zip_file" accept=".zip" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Importer</button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>Gestionnaire de Recherche - Import de projets</p>
        </footer>
    </div>
</body>
</html>
