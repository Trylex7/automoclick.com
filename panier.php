<?php
session_start();
require_once "db/dbconnect2.php"; // connexion PDO ($db)
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

// Fonctions de chiffrement et déchiffrement
function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}

function dechiffrer($texte_chiffre, $cle)
{
    return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}

// Initialiser le panier si inexistant
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// Charger tous les produits
$stmt = $db->query("SELECT * FROM produit");
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
$produitsAssoc = [];
foreach ($produits as $p) {
    $produitsAssoc[$p['id']] = $p;
}

// --- AFFICHAGE PANIER (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'voir') {
    if (empty($_SESSION['panier'])) {
        echo '<p class="text-gray-400 text-center italic">Votre panier est vide.</p>';
        exit;
    }

    $total = 0;
    $data_panier = [];
    echo '<ul class="space-y-4">';
    foreach ($_SESSION['panier'] as $id => $quantite) {
        if (!isset($produitsAssoc[$id]))
            continue;
        $prod = $produitsAssoc[$id];
        $prixTotal = $prod['prix'] * $quantite;
        $total += $prixTotal;

        echo '<li class="flex flex-col sm:flex-row sm:items-center sm:justify-between bg-white border border-gray-200 p-4 rounded-xl shadow-sm hover:shadow-md transition" data-id="' . $id . '">';

        // Image + infos
        echo '<div class="flex items-center space-x-4 mb-3 sm:mb-0">
                <img src="' . htmlspecialchars($prod['image']) . '" alt="' . htmlspecialchars($prod['nom']) . '" class="w-16 h-16 rounded-lg object-cover border border-gray-200">
                <div>
                    <p class="font-semibold text-gray-800">' . htmlspecialchars($prod['nom']) . '</p>
                    <p class="text-sm text-gray-500">' . number_format($prod['prix'], 2) . ' €</p>
                </div>
              </div>';

        // Prix + Supprimer
        echo '<div class="text-right">
                <button class="delete text-red-500 text-xs hover:underline mt-1">Supprimer</button>
              </div>';

        echo '</li>';
        $data_panier[] = [
            'type' => $prod['type'],
            'nom' => $prod['nom'],
            'prix' => $prod['prix'],
            'prix_total' => $total,
            'categorie' => $prod['categorie'],
            'duree_abonnement' => $prod['duree_abonnement'] ?? null,
            'numero_client' => $_SESSION['id_client'] ?? null,
            'numero_pro' => $_SESSION['id_pro'] ?? null
        ];
    }
    echo '</ul>';
    $token = chiffrer(json_encode($data_panier), $cle_secrete);

    // Footer
    echo '<div class="mt-6 border-t pt-4">
            <div class="flex justify-between items-center mb-4">
                <span class="text-lg font-semibold text-gray-700">Total :</span>
                <span class="text-xl font-bold text-green-600">' . number_format($total, 2) . ' €</span>
            </div>
            <a href="checkout?x=' . $token . '" class="block w-full bg-black hover:bg-green-700 text-white text-center font-semibold py-3 rounded-xl shadow-md transition">
                Commander
            </a>
          </div>';
    exit;
}

// --- AJAX (POST) pour CRUD panier ---
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';
$quantite = isset($_POST['quantite']) ? max(1, intval($_POST['quantite'])) : 1; // min 1

switch ($action) {
    case 'ajouter':
        $_SESSION['panier'][$id] = ($_SESSION['panier'][$id] ?? 0) + $quantite;
        break;
    case 'supprimer':
        unset($_SESSION['panier'][$id]);
        break;

}

echo json_encode(['success' => true, 'panier' => $_SESSION['panier']]);
