<?php
require_once 'header.php';
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';
require_once 'includes/webhook.php';
function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}

session_start();
require 'db/dbconnect2.php';
header('Content-Type: application/json');

function genererChaineAleatoire($longueur = 50)
{
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $resultat = '';
    for ($i = 0; $i < $longueur; $i++) {
        $resultat .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $resultat;
}

// Récupération des données
$numero_client = $_SESSION['id_client'] ?? null;
$date = $_POST['date'] ?? null;
$heure = $_POST['heure'] ?? null;
$numero_pro = $_POST['numero_pro'] ?? null;
$prestation_ids = $_POST['prestation_id'] ?? [];
$immatriculation = $_POST['immatriculation'] ?? null;

$sqlclient = $db->prepare("SELECT nom, prenom, email FROM login_user WHERE numero_client = ?");
$sqlclient->execute([$numero_client]);
$data_client = $sqlclient->fetch(PDO::FETCH_ASSOC);

$sqlpro = $db->prepare("SELECT * FROM entreprises WHERE numero_pro = ?");
$sqlpro->execute([$numero_pro]);
$data_pro = $sqlpro->fetch(PDO::FETCH_ASSOC);

if (!$date || !$heure || !$numero_pro || !$numero_client || !is_array($prestation_ids) || count($prestation_ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes ou invalides.']);
    exit;
}

// Validation format date/heure
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $heure)) {
    echo json_encode(['success' => false, 'message' => 'Format de date ou d\'heure invalide.']);
    exit;
}

// Récupération des prestations
$placeholders = implode(',', array_fill(0, count($prestation_ids), '?'));
$sql = "SELECT id, nom, prix, duree, tva FROM prestations WHERE id IN ($placeholders) AND numero_pro = ?";
$params = array_merge($prestation_ids, [$numero_pro]);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$prestations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$bloquer_par_duree = isset($data_pro['rdvs_bloque']) ? (bool)$data_pro['rdvs_bloque'] : false;
if (count($prestations) !== count($prestation_ids)) {
    echo json_encode(['success' => false, 'message' => 'Certaines prestations sont introuvables.']);
    exit;
}
// Calcul de la durée totale en minutes (séparé du prix)
$duree_totale = 0;
foreach ($prestations as $p) {
    $duree_totale += (!empty($p['duree']) ? (float)$p['duree'] : 0) * 60;
}

// Calcul du prix total
$prix_total = 0;
foreach ($prestations as $p) {
    $duree = (!empty($p['duree']) ? (float)$p['duree'] : 1); // en heures
    $prix = (float)$p['prix'] * $duree;

    $tva = isset($p['tva']) ? (float)$p['tva'] : 0;
    $ttc = $prix * (1 + $tva / 100);

    $prix_total += $ttc;
}

// Création objet DateTime pour heure début
$heure_debut = DateTime::createFromFormat('H:i', $heure);

// Définir durée du créneau selon mode
if ($bloquer_par_duree) {
    $duree_creneau = $duree_totale > 0 ? $duree_totale : 30;
} else {
    $duree_creneau = 30;
}

// Calcul heure fin
$heure_fin = (clone $heure_debut)->modify("+$duree_creneau minutes");

// ⚠️ Si l’heure fin dépasse minuit → on bloque car ça ne doit pas déborder sur le jour suivant
$fin_journee = DateTime::createFromFormat('Y-m-d H:i', $date . ' 23:59');

if ($heure_fin > $fin_journee) {
    $heure_fin = $fin_journee;
    // On ajuste aussi la durée pour refléter le nouveau créneau
    $duree_creneau = $heure_debut->diff($heure_fin)->h * 60 + $heure_debut->diff($heure_fin)->i;
}

// Vérification des chevauchements sur la même journée
$sql = "SELECT heure, duree FROM rdvs WHERE numero_pro = ? AND date = ? AND etat != 'annule'";
$q = $db->prepare($sql);
$q->execute([$numero_pro, $date]);
$rdvsExistants = $q->fetchAll(PDO::FETCH_ASSOC);

foreach ($rdvsExistants as $rdv) {
    $h_exist = DateTime::createFromFormat('H:i', $rdv['heure']);
    $h_fin_exist = (clone $h_exist)->modify("+{$rdv['duree']} minutes");

    // Test chevauchement
    if ($heure_debut < $h_fin_exist && $heure_fin > $h_exist) {
        echo json_encode(['success' => false, 'message' => 'Le créneau sélectionné chevauche un autre rendez-vous.']);
        exit;
    }
}


// Enregistrement du rendez-vous
try {
    $db->beginTransaction();

    $rdv_id = genererChaineAleatoire(50);
    $etat = 'en_attente_paiement';
    $noms_prestations = implode(' + ', array_column($prestations, 'nom'));

    $insert = $db->prepare("
        INSERT INTO rdvs (date, numero_client, heure, numero_pro, nom_prestation, duree, etat, rdv_id, immatriculation , transaction_rdv)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insert->execute([
        $date,
        $numero_client,
        $heure,
        $numero_pro,
        $noms_prestations,
        $duree_creneau,
        $etat,
        $rdv_id,
        $immatriculation
    ]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la réservation : ' . $e->getMessage()]);
    exit;
}

// Calcul TVA main d'oeuvre selon code postal
$tva_main_oeuvre = 20;
$code_postal = $data_pro['code_postal'] ?? '';
$prefixe = substr($code_postal, 0, 3);
if (in_array($prefixe, ['971', '972', '973', '974', '976'])) {
    $tva_main_oeuvre = 8.5;
} else {
    $tva_main_oeuvre = 20;
}

$taxes = 0;
$taxes_ttc = $taxes / 100;
$total_ttc = $prix_total +  $taxes_ttc;
$prix_total = $total_ttc;

// Stockage session paiement en cours
// $_SESSION['paiements_en_cours'][$rdv_id] = [
//     'numero_pro' => $numero_pro,
//     'date' => $date,
//     'heure' => $heure,
//     'prestation_ids' => $prestation_ids,
//     'prestations' => $prestations,
//     'prix_total' => $prix_total,
//     'etat' => $etat,
//     'timestamp' => time(),
//     'expire' => time() + 4000
// ];

$data_rdv = [
    'numero_pro' => $numero_pro,
    'prestation_ids' => $prestation_ids,
    'date' => $date,
    'heure' => $heure,
    'rdv_id' => json_encode([$rdv_id]),
    'prix_total' => $prix_total,
    'etat' => $etat,
    'timestamp' => time(),
    'expire' => time() + 4000
];

$token = chiffrer(json_encode($data_rdv), $cle_secrete);

// Redirection vers la page de paiement
$redirect_url = 'paiement-rdv.php?h=' . $token;

echo json_encode([
    'success' => true,
    'message' => 'Créneau réservé temporairement pour vos prestations.',
    'redirect_url' => $redirect_url
]);
exit;
