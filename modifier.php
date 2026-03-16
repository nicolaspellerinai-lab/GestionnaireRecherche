<?php
/**
 * Gestionnaire de Recherche - Modifier Definition/Resume
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Vérification des paramètres GET
$projet = isset($_GET['projet']) ? trim($_GET['projet']) : '';
$fichier = isset($_GET['fichier']) ? trim($_GET['fichier']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if (empty($projet) || empty($fichier) || empty($type)) {
    die('Paramètres invalides. Veuillez fournir: projet, fichier et type.');
}

if (!in_array($type, array('definition', 'resume'))) {
    die('Type invalide. Doit être: definition ou resume');
}

// Chemins
$project_path = DATA_PATH . $projet;
$target_file = $project_path . '/' . $fichier;

// Vérifier que le projet existe
if (!is_dir($project_path)) {
    die('Projet non trouvé.');
}

// Vérifier que le fichier existe
if (!file_exists($target_file)) {
    die('Fichier non trouvé: ' . htmlspecialchars($fichier));
}

// Initialiser les variables
$message = '';
$message_type = '';
$contenu = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier l'authentification
    if (!is_authenticated()) {
        $message = 'Vous devez être connecté pour modifier.';
        $message_type = 'error';
    } else {
        $nouveau_contenu = isset($_POST['contenu']) ? $_POST['contenu'] : '';
        
        // Sauvegarder les modifications
        if (file_put_contents($target_file, $nouveau_contenu) !== false) {
            // Rediriger vers la page appropriée
            if ($type === 'definition') {
                header('Location: projet.php?projet=' . urlencode($projet));
            } else {
                // Extraire l'ID du fichier resume (resume-XXX.md)
                preg_match('/resume-(\d+)\.md/', $fichier, $matches);
                if (!empty($matches[1])) {
                    $source_id = $matches[1];
                    header('Location: source.php?projet=' . urlencode($projet) . '&id=' . $source_id);
                } else {
                    header('Location: projet.php?projet=' . urlencode($projet));
                }
            }
            exit;
        } else {
            $message = 'Erreur lors de la sauvegarde du fichier.';
            $message_type = 'error';
        }
    }
}

// Lire le contenu actuel du fichier
$contenu = file_get_contents($target_file);

// Déterminer le titre de la page
$titre_page = ($type === 'definition') ? 'Modifier la définition' : 'Modifier le résumé';
$nom_fichier = basename($fichier);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titre_page); ?> - <?php echo htmlspecialchars($projet); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($titre_page); ?></h1>
            <div>
                <a href="projet.php?projet=<?php echo urlencode($projet); ?>" class="btn btn-secondary">← Retour au projet</a>
                <?php if (is_authenticated()): ?>
                    <a href="logout.php" class="btn-logout">Déconnexion</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!is_authenticated()): ?>
            <div class="message message-error">
                Vous devez être connecté pour modifier ce fichier.
                <br><br>
                <a href="login.php?redirect=modifier.php&projet=<?php echo urlencode($projet); ?>&fichier=<?php echo urlencode($fichier); ?>&type=<?php echo urlencode($type); ?>" class="btn btn-primary">Se connecter</a>
            </div>
        <?php else: ?>
            <div class="upload-form">
                <h2><?php echo htmlspecialchars($nom_fichier); ?></h2>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="contenu">Contenu:</label>
                        <textarea id="contenu" name="contenu" rows="20" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-family: monospace; line-height: 1.6;"><?php echo htmlspecialchars($contenu); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Sauvegarder</button>
                        <a href="projet.php?projet=<?php echo urlencode($projet); ?>" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
