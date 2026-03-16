<?php
/**
 * Gestionnaire de Recherche - Connexion
 * PHP 5.6 Compatible
 */

require_once 'config.php';

// Si déjà connecté, redirection vers index.php
if (is_authenticated()) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (authenticate($password)) {
        header('Location: index.php');
        exit;
    } else {
        $message = 'Mot de passe incorrect.';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestionnaire de Recherche</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h1>🔐 Gestionnaire de Recherche</h1>
            <h2>Connexion</h2>
            
            <?php if (!empty($message)): ?>
                <div class="message message-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="password">Mot de passe:</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                
                <button type="submit" class="btn btn-primary">Se connecter</button>
            </form>
            
            <p class="back-link">
                <a href="index.php">← Retour à l'accueil</a>
            </p>
        </div>
    </div>
</body>
</html>
