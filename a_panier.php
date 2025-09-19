<?php
session_start();
header('Content-Type: application/json');

// Simuler l'ajout au panier
$produit_id = $_POST['produit_id'] ?? null;
if ($produit_id) {
    $_SESSION['panier'][$produit_id] = ($_SESSION['panier'][$produit_id] ?? 0) + 1;
}

// Générer le HTML du panier mis à jour
ob_start();
include 'panier_partial.php'; // ou la partie <aside> de ton panier
$panier_html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $panier_html
]);