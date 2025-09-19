<?php
session_start();
require_once 'db/dbconnect2.php';
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

if (!isset($_GET['h'])) die("Token manquant.");

function dechiffrer($texte_chiffre, $cle)
{
    return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}

$token = $_GET['h'];
$all_data = dechiffrer($token, $cle_secrete);
if (!$all_data) die("Token invalide.");

$data = json_decode($all_data, true);
if (!is_array($data)) die("Token JSON invalide.");

$rdv_ids    = json_decode($data['rdv_id'] ?? '[]', true);
$prix_total = $data['prix_total'] ?? 0;
$numero_pro = $data['numero_pro'] ?? null;
$email      = $data['email'] ?? null;

if (empty($rdv_ids) || !$email) die("Données RDV ou email manquantes.");

// Insertion ou mise à jour RDV
foreach ($rdv_ids as $rdv_id) {
    $stmt = $db->prepare("UPDATE rdvs SET email_client = ?, etat = 'en_attente_paiement' WHERE rdv_id = ?");
    $stmt->execute([$email, $rdv_id]);
}

exit;
