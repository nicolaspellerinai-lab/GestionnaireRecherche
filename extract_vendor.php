<?php
// Script d'extraction du vendor.zip
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting extraction...\n";

$zip = new ZipArchive();
if ($zip->open('vendor.zip') === TRUE) {
    echo "Opening zip file...\n";
    $result = $zip->extractTo('.');
    echo "Extract result: " . ($result ? 'success' : 'failed') . "\n";
    $zip->close();
    echo 'Vendor extracted successfully!';
    // Supprimer le zip après extraction
    @unlink('vendor.zip');
    @unlink('extract_vendor.php');
} else {
    echo 'Failed to extract vendor.zip';
}
