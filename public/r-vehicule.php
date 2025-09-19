<?php
require_once '../header.php';
require_once '../includes/webhook.php';
require_once '../api/traker.php';
session_start();
if (!isset($_SESSION['id_client'])) {
    header('Location: /');
    exit;
}

require '../db/dbconnect2.php';

$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

function dechiffrer($texte_chiffre, $cle)
{
    return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}

function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}

$token = $_GET['v'] ?? null;
$numero_pro = $token ? dechiffrer($token, $cle_secrete) : ($_GET['numero_pro'] ?? null);
if (!$numero_pro) die("Prestataire non défini.");

$stmt = $db->prepare("SELECT timezone FROM entreprises WHERE numero_pro = ?");
$stmt->execute([$numero_pro]);
$timezone_pro = $stmt->fetchColumn() ?: 'America/Martinique';
$tz = new DateTimeZone($timezone_pro);

$now = new DateTime('now', $tz);

$dateRecuperation = $_GET['date_recup'] ?? $now->format('Y-m-d');
$dateRentree = $_GET['date_rentree'] ?? $now->format('Y-m-d');

$recup = new DateTime($dateRecuperation, $tz);
$rentree = new DateTime($dateRentree, $tz);

// Vérification validité des dates
if ($rentree < $recup) {
    die("Erreur : la date de rentrée ne peut pas être avant la date de récupération.");
}

$intervalMinutes = 30;
$dureeDemandee = isset($_GET['duree']) ? (int) $_GET['duree'] : 30;

function genererCreneaux($debut, $fin, $date, $tz, $intervalMinutes)
{
    $liste = [];
    $start = DateTime::createFromFormat('Y-m-d H:i:s', "$date $debut", $tz);
    $end = DateTime::createFromFormat('Y-m-d H:i:s', "$date $fin", $tz);
    while ($start < $end) {
        $liste[] = $start->format('H:i');
        $start->modify("+{$intervalMinutes} minutes");
    }
    return $liste;
}

$horaires = $db->prepare("SELECT * FROM horaires WHERE numero_pro = ?");
$horaires->execute([$numero_pro]);
$horairesData = $horaires->fetch(PDO::FETCH_ASSOC);

$joursFr = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];

$periode = new DatePeriod($recup, new DateInterval('P1D'), (clone $rentree)->modify('+1 day'));

$datesDisponibles = [];

foreach ($periode as $jour) {
    $dateStr = $jour->format('Y-m-d');
    $jourSemaine = strtolower($joursFr[(int)$jour->format('N') - 1]);

    $debut1 = $horairesData[$jourSemaine . '_debut'];
    $fin1 = $horairesData[$jourSemaine . '_fin'];
    $debut2 = $horairesData[$jourSemaine . '_debut2'];
    $fin2 = $horairesData[$jourSemaine . '_fin2'];

    if (empty($debut1) && empty($fin1) && empty($debut2) && empty($fin2)) {
        continue; // fermé ce jour
    }

    $creneauxJour = [];

    if (!empty($debut1) && !empty($fin1)) {
        $creneauxJour = array_merge($creneauxJour, genererCreneaux($debut1, $fin1, $dateStr, $tz, $intervalMinutes));
    }
    if (!empty($debut2) && !empty($fin2)) {
        $creneauxJour = array_merge($creneauxJour, genererCreneaux($debut2, $fin2, $dateStr, $tz, $intervalMinutes));
    }

    $rdvs = $db->prepare("SELECT heure, duree FROM rdvs WHERE numero_pro = ? AND date = ?");
    $rdvs->execute([$numero_pro, $dateStr]);
    $rdvsPris = $rdvs->fetchAll(PDO::FETCH_ASSOC);

    $creneauxDispo = array_filter($creneauxJour, function ($creneau) use ($rdvsPris, $dateStr, $tz, $dureeDemandee, $now) {
        $heureCreneau = DateTime::createFromFormat('Y-m-d H:i', "$dateStr $creneau", $tz);
        $finCreneau = (clone $heureCreneau)->modify("+{$dureeDemandee} minutes");

        if ($dateStr === $now->format('Y-m-d') && $finCreneau <= $now)
            return false;

        foreach ($rdvsPris as $rdv) {
            $heureRdv = DateTime::createFromFormat('Y-m-d H:i', "$dateStr {$rdv['heure']}", $tz);
            $finRdv = (clone $heureRdv)->modify("+{$rdv['duree']} minutes");
            if ($heureCreneau < $finRdv && $finCreneau > $heureRdv)
                return false;
        }
        return true;
    });

    if (count($creneauxDispo) > 0) {
        $datesDisponibles[$dateStr] = $creneauxDispo;
    }
}

$totalJours = iterator_count(new DatePeriod($recup, new DateInterval('P1D'), (clone $rentree)->modify('+1 day')));

if (count($datesDisponibles) < $totalJours) {
    echo "Aucun créneau disponible sur l’ensemble de la période sélectionnée.";
    exit;
}

// Affichage des créneaux disponibles
echo "<h2>Créneaux disponibles entre $dateRecuperation et $dateRentree</h2>";
foreach ($datesDisponibles as $date => $creneaux) {
    echo "<h4>$date</h4><ul>";
    foreach ($creneaux as $h) echo "<li>$h</li>";
    echo "</ul>";
}
?>
