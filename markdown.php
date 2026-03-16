<?php
/**
 * Markdown Renderer - Gestionnaire de Recherche
 * PHP 5.6 Compatible
 * Utilise Parsedown pour le rendu Markdown vers HTML
 */

require_once __DIR__ . '/Parsedown.php';

/**
 * Convertit le contenu Markdown en HTML
 * @param string $content Contenu Markdown
 * @return string Contenu HTML
 */
function renderMarkdown($content) {
    if (empty($content)) {
        return '';
    }
    
    $parser = new \Parsedown();
    return $parser->text($content);
}

/**
 * Alias pour renderMarkdown - rendu de contenu Markdown
 * @param string $content Contenu Markdown
 * @return string Contenu HTML
 */
function markdown_to_html($content) {
    return renderMarkdown($content);
}
