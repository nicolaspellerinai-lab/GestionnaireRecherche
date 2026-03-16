<?php
/**
 * Export PDF - Gestionnaire de Recherche
 * Exporte un projet complet en PDF avec TCPDF
 * PHP 5.6 Compatible
 */

require_once 'config.php';
require_once 'vendor/tcpdf/tcpdf.php';

// Timeout de 60 secondes
set_time_limit(60);

// ============================================
// AUTHENTIFICATION
// ============================================

// Pour usage API, autoriser sans session
$allow_api = isset($_GET['api']) && $_GET['api'] === '1';

if (!$allow_api && !is_authenticated()) {
    http_response_code(401);
    echo 'Accès non autorisé';
    exit;
}

// ============================================
// VALIDATION DES PARAMÈTRES
// ============================================

if (!isset($_GET['projet']) || empty($_GET['projet'])) {
    http_response_code(400);
    echo 'Paramètre "projet" requis';
    exit;
}

$projet_id = intval($_GET['projet']);
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'complet'; // 'complet' ou 'resumes_only'
$tags_filter = isset($_GET['tags']) ? $_GET['tags'] : ''; // Optionnel: filtre par tags (séparés par virgule)

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================

$conn = db_connect();

// Récupérer le projet
$projet_id_escaped = db_escape($projet_id, $conn);
$sql_projet = "SELECT * FROM projets WHERE id = $projet_id_escaped";
$result_projet = db_query($sql_projet, $conn);
$projet = db_fetch_array($result_projet);

if (!$projet) {
    db_close($conn);
    http_response_code(404);
    echo 'Projet non trouvé';
    exit;
}

// Récupérer les sources
$sql_sources = "SELECT * FROM sources WHERE projet_id = $projet_id_escaped AND pertinente = 1 ORDER BY date_ajout DESC";
$result_sources = db_query($sql_sources, $conn);
$sources = db_fetch_all($result_sources);

db_close($conn);

// ============================================
// CRÉATION DU PDF
// ============================================

class MYPDF extends TCPDF {
    // En-tête personnalisé
    public function Header() {
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Gestionnaire de Recherche - Export PDF', 0, false, 'L');
        $this->Cell(0, 10, date('d/m/Y'), 0, false, 'R');
        $this->Ln(15);
    }
    
    // Pied de page
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C');
    }
}

// Créer le PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Informations du document
$pdf->SetCreator('Gestionnaire de Recherche');
$pdf->SetAuthor('Export PDF');
$pdf->SetTitle($projet['nom']);

// ============================================
// PAGE DE COUVERTURE
// ============================================

$pdf->AddPage();

// Titre principal
$pdf->SetFont('helvetica', 'B', 24);
$pdf->Ln(40);
$pdf->MultiCell(0, 20, $projet['nom'], 0, 'C', false, 1, '', '', true);

// Description
if (!empty($projet['description'])) {
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 10, $projet['description'], 0, 'C', false, 1, '', '', true);
}

// Date
$pdf->Ln(30);
$pdf->SetFont('helvetica', 'I', 10);
$date_creation = isset($projet['date_creation']) ? date('d/m/Y', strtotime($projet['date_creation'])) : date('d/m/Y');
$pdf->Cell(0, 10, 'Exporté le: ' . date('d/m/Y à H:i'), 0, false, 'C');
$pdf->Ln(10);
$pdf->Cell(0, 10, 'Nombre de sources: ' . count($sources), 0, false, 'C');

// ============================================
// TABLE DES MATIÈRES
// ============================================

$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 15, 'Table des matières', 0, true, 'L');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 11);

// Créer la TOC (Table of Contents)
$toc = array();
foreach ($sources as $index => $source) {
    $toc_number = $index + 1;
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(10, 8, $toc_number . '.', 0, false, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, $source['titre'], 0, true, 'L');
    $toc[] = array(
        'num' => $toc_number,
        'titre' => $source['titre'],
        'page' => 0 // Sera mis à jour après
    );
}

// ============================================
// SOURCES
// ============================================

$source_num = 0;

foreach ($sources as $source) {
    $source_num++;
    
    // Nouvelle page pour chaque source (ou section)
    $pdf->AddPage();
    
    // Titre de la source
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, $source_num . '. ' . $source['titre'], 0, true, 'L');
    
    // URL
    if (!empty($source['url'])) {
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(0, 0, 255);
        $pdf->Write(0, $source['url'], '', 0, 'L', true, '', '', true, 0, '');
        $pdf->SetTextColor(0, 0, 0);
    }
    
    $pdf->Ln(5);
    
    // Résumé (si existant et si mode complet)
    if (!empty($source['resume']) && $mode === 'complet') {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Résumé:', 0, true, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 6, strip_tags($source['resume']), 0, 'L', false, 1, '', '', true);
        $pdf->Ln(5);
    } elseif (!empty($source['resume']) && $mode === 'resumes_only') {
        // En mode résumes uniquement, afficher le résumé en priorité
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Résumé:', 0, true, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 6, strip_tags($source['resume']), 0, 'L', false, 1, '', '', true);
        $pdf->Ln(5);
    }
    
    // Contenu complet (si mode complet)
    if (!empty($source['contenu']) && $mode === 'complet') {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Contenu:', 0, true, 'L');
        
        $pdf->SetFont('helvetica', '', 9);
        
        // Limiter le contenu à 8000 caractères pour éviter les PDF trop lourds
        $contenu = strip_tags($source['contenu']);
        if (strlen($contenu) > 8000) {
            $contenu = substr($contenu, 0, 8000) . '... [Contenu tronqué]';
        }
        
        $pdf->MultiCell(0, 5, $contenu, 0, 'L', false, 1, '', '', true);
    }
}

// ============================================
// GÉNÉRATION DU PDF
// ============================================

$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $projet['nom']);
$filename .= '_' . date('Ymd_His') . '.pdf';

// Sortie du PDF
$pdf->Output($filename, 'I');

exit;
