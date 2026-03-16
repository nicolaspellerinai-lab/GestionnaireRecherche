<?php
/**
 * Gestionnaire de Recherche - Vue Source (SIMPLE VERSION)
 * Mode fichier uniquement
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once 'config.php';

$projet = isset($_GET['projet']) ? trim($_GET['projet']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($projet) || $id <= 0) {
    die('Paramètres invalides.');
}

$project_dir = DATA_PATH . $projet . '/';
$sources_dir = $project_dir . 'sources/';
$source_file = $sources_dir . sprintf('%02d', $id) . '.md';
$resume_file = $project_dir . 'resume-' . sprintf('%02d', $id) . '.md';

$sources_file = $project_dir . 'sources.md';
$source_meta = array('titre' => 'Source #' . $id, 'url' => '');
if (file_exists($sources_file)) {
    $content = file_get_contents($sources_file);
    preg_match('/' . $id . '\.\s+\*\*([^*]+)\*\*[^-]*- URL:\s*(https?:\/\/[^\s]+)/', $content, $matches);
    if (!empty($matches[1])) $source_meta['titre'] = trim($matches[1]);
    if (!empty($matches[2])) $source_meta['url'] = $matches[2];
}

$contenu_source = file_exists($source_file) ? file_get_contents($source_file) : 'Fichier introuvable.';
$resume = file_exists($resume_file) ? file_get_contents($resume_file) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Source #<?php echo $id; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Source #<?php echo $id; ?></h1>
            <a href="projet.php?projet=<?php echo urlencode($projet); ?>">← Retour au projet</a>
        </header>
        
        <h2><?php echo htmlspecialchars($source_meta['titre']); ?></h2>
        
        <?php if (!empty($source_meta['url'])): ?>
            <p><a href="<?php echo htmlspecialchars($source_meta['url']); ?>" target="_blank">Voir URL source</a></p>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <h3>Contenu:</h3>
            <pre style="background:#f5f5f5;padding:10px;overflow:auto;"><?php echo htmlspecialchars($contenu_source); ?></pre>
        </div>
        
        <?php if (!empty($resume)): ?>
        <div style="margin-top: 20px;">
            <h3>Résumé:</h3>
            <div><?php echo nl2br(htmlspecialchars($resume)); ?></div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
