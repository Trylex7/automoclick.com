<?php
require_once '../header.php';
require '../vendor/autoload.php';
require_once '../db/dbconnect2.php';
require_once '../includes/webhook.php';
use Dompdf\Dompdf;
use Dompdf\Options;


session_start();
if (!isset($_SESSION['id_pro'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /connexion?success_url={$currentUrl}");
    exit;
}

$id_pro = $_SESSION['id_pro'];
$data_abonnement = $db->prepare('SELECT * FROM pro_abonnement WHERE numero_pro = ?');
$data_abonnement->execute([$id_pro]);
$abonnement = $data_abonnement->fetch(PDO::FETCH_ASSOC);
$data_pro = $db->prepare('SELECT profil_valid FROM entreprises WHERE numero_pro = ?');
$data_pro->execute([$id_pro]);
$profil_pro = $data_pro->fetch(PDO::FETCH_ASSOC)['profil_valid'];
$data_spec = $db->prepare('SELECT spe FROM entreprises WHERE numero_pro = ? ');
$data_spec->execute([$id_pro]);
$data_s = $data_spec->fetch(PDO::FETCH_ASSOC);
$specialisation = $data_s['spe'];
if (empty($abonnement)) {
    header('Location: abonnement');
    exit;
}
function getProfilCompletion($id_pro, $db)
{
    $total = 5;
    $score = 0;
    $missing = [];

    // 1. Prestations
    $stmt = $db->prepare("SELECT COUNT(*) FROM prestations WHERE numero_pro = ?");
    $stmt->execute([$id_pro]);
    if ($stmt->fetchColumn() > 0) {
        $score++;
    } else {
        $missing[] = "Ajouter au moins une prestation";
    }


    $stmt = $db->prepare("SELECT COUNT(*) FROM horaires WHERE numero_pro = ?");
    $stmt->execute([$id_pro]);
    if ($stmt->fetchColumn() > 0) {
        $score++;
    } else {
        $missing[] = "Définir les horaires de travail";
    }

    $stmt = $db->prepare("SELECT taux_horaire FROM entreprises WHERE numero_pro = ?");
    $stmt->execute([$id_pro]);
    $row = $stmt->fetch();
    if (!empty($row['taux_horaire'])) {
        $score++;
    } else {
        $missing[] = "Définir le taux horaire";
    }


    $stmt = $db->prepare("SELECT phone_number FROM entreprises WHERE numero_pro = ?");
    $stmt->execute([$id_pro]);
    $row = $stmt->fetch();
    if (!empty($row['phone_number'])) {
        $score++;
    } else {
        $missing[] = "Renseigner un numéro de téléphone";
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM entreprises WHERE numero_pro = ? AND  spe != 'inconnue'");
    $stmt->execute([$id_pro]);
    if ($stmt->fetchColumn() > 0) {
        $score++;
    } else {
        $missing[] = "Ajouter votre catégorie";
    }

    $percent = ($score / $total) * 100;

    return [
        'completion' => round($percent),
        'missing' => $missing
    ];
}
if ($percent = 100) {
    $update = $db->prepare('UPDATE entreprises SET profil_valid = "1" WHERE numero_pro = ? ');
    $update->execute([$id_pro]);
    $data_pro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
    $data_pro->execute([$id_pro]);
    $pro = $data_pro->fetch(PDO::FETCH_ASSOC);

    $data_prestation = $db->prepare('SELECT * FROM prestations WHERE numero_pro = ?');
    $data_prestation->execute([$id_pro]);
    $prestations = $data_prestation->fetchAll(PDO::FETCH_ASSOC);

    // Construction des lignes du tableau prestations avec calcul TVA et TTC
    $prestationsHtml = '';
    foreach ($prestations as $p) {
        $prixHT = (float) $p['prix'];
        $tva = isset($p['tva']) ? (float) $p['tva'] : 20; // par défaut 20% si non défini
        $prixTTC = $prixHT * (1 + $tva / 100);
        $prestationsHtml .= '<tr>
        <td>' . htmlspecialchars($p['nom']) . '</td>
        <td>' . number_format($p['duree'], 2, ',', ' ') . ' h</td>
        <td>' . number_format($prixHT, 2, ',', ' ') . ' €</td>
        <td>' . number_format($tva, 2, ',', ' ') . ' %</td>
        <td>' . number_format($prixTTC, 2, ',', ' ') . ' €</td>
    </tr>';
    }

    // Calcul durée totale et coût main d'oeuvre
    $duree_totale = 0;
    foreach ($prestations as $p) {
        $duree_totale += (float) $p['duree'];
    }
    $taux_horaire = isset($pro['taux_horaire']) ? (float) $pro['taux_horaire'] : 0;
    $cout_main_oeuvre = $duree_totale * $taux_horaire;

    $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Conditions Générales de Prestations - ' . htmlspecialchars($pro['denomination']) . '</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.4; }
    h1, h2 { color: #2F4F4F; }
    h1 { font-size: 18px; margin-bottom: 10px; }
    h2 { font-size: 14px; margin-top: 20px; }
    p, ul { margin: 8px 0; }
    ul { padding-left: 20px; }
    strong { color: #000; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>
</head>
<body>
<h1>Conditions Générales de Prestations de Services (CGPS)</h1>
<p>Les présentes Conditions Générales de prestations de services régissent les relations entre <strong>' . htmlspecialchars($pro['denomination']) . '</strong> et ses clients.</p>

<h2>ARTICLE 1 : DISPOSITIONS GÉNÉRALES</h2>
<p>Les présentes CGPS régissent les relations entre <strong>' . htmlspecialchars($pro['denomination']) . '</strong> et ses clients dans le cadre des prestations de services. Toute commande implique l’adhésion pleine et entière du client aux présentes CGPS.</p>

<h2>ARTICLE 2 : NATURE DES PRESTATIONS</h2>
<p><strong>' . htmlspecialchars($pro['denomination']) . '</strong> propose les prestations suivantes :</p>
<table>
<thead>
<tr><th>Prestation</th><th>Durée</th><th>Prix HT (€)</th><th>TVA (%)</th><th>Prix TTC (€)</th></tr>
</thead>
<tbody>' . $prestationsHtml . '</tbody>
</table>

<p><strong>Taux horaire de la main d’œuvre :</strong> ' . number_format($pro['taux_horaire'], 2, ',', ' ') . ' € HT</p>

<h2>ARTICLE 3 : DEVIS ET COMMANDE</h2>
<p>Un devis peut être établi à la demande du client, précisant les éléments de la prestation. La commande peut être validée directement via la plateforme sans signature préalable du devis.</p>

<h2>ARTICLE 4 : PRIX</h2>
<p>Les prix des prestations sont exprimés en euros hors taxes (HT) et toutes taxes comprises (TTC), selon le taux de TVA en vigueur.</p>

<h2>ARTICLE 5 : MODALITÉS DE PAIEMENT</h2>
<p>Le paiement des prestations s’effectue intégralement lors de la prise de rendez-vous sur la plateforme Automoclick, par carte bancaire ou tout autre moyen de paiement sécurisé proposé.</p>

<h2>ARTICLE 6 : MODIFICATION DES RENDEZ-VOUS</h2>
<p>Les prestations sont <strong>modifiables</strong> jusqu\'à 48 heures avant la date prévue. <strong>Aucun remboursement ni annulation</strong> ne sera accepté après la validation du paiement.</p>

<h2>ARTICLE 7 : FORCE MAJEURE</h2>
<p>En cas de force majeure, aucune des parties ne pourra être tenue responsable. La partie concernée devra informer l’autre dans un délai de 5 jours ouvrés.</p>

<h2>ARTICLE 8 : OBLIGATIONS ET CONFIDENTIALITÉ</h2>
<p><strong>' . htmlspecialchars($pro['denomination']) . '</strong> s’engage à garantir la confidentialité des données échangées avec le client.</p>

<h2>ARTICLE 9 : RESPONSABILITÉ</h2>
<p>Le prestataire s’engage à fournir les moyens nécessaires à la bonne exécution de la prestation. Sa responsabilité ne peut excéder le montant HT payé pour la prestation.</p>

<h2>ARTICLE 10 : RÉFÉRENCES</h2>
<p>Sauf demande contraire,<strong>' . htmlspecialchars($pro['denomination']) . '</strong> autorise le client à mentionner son nom ou logo à titre de référence.</p>

<h2>ARTICLE 11 : LITIGES</h2>
<p>Toute contestation sera soumise à un arbitrage selon les règles de la Chambre de Commerce Internationale.</p>

<h2>ARTICLE 12 : LOI APPLICABLE</h2>
<p>Le présent contrat est régi par la loi française.</p>
</body>
</html>';


    // Génération PDF avec Dompdf
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Nettoyer le nom de fichier
    $filename = "CGP-" . preg_replace('/[^a-zA-Z0-9_-]/', '', $pro['denomination']) . ".pdf";

    // Chemin complet vers le dossier
    $pdf_directory = __DIR__ . 'docs/cgp/';
    $pdf_path = $pdf_directory . $filename;

    // Créer le dossier s’il n'existe pas
    if (!is_dir($pdf_directory)) {
        if (!mkdir($pdf_directory, 0775, true)) {
            die("❌ Erreur : Impossible de créer le dossier docs/cgp.");
        }
    }

    // Générer et enregistrer le PDF
    $pdfOutput = $dompdf->output();
    if (file_put_contents($pdf_path, $pdfOutput) === false) {
        die("❌ Erreur : Impossible d'enregistrer le fichier PDF.");
    }

    // (Optionnel) Encodage base64 du fichier (par ex. pour pièce jointe e-mail)
    $file_content = file_get_contents($pdf_path);
    if ($file_content === false) {
        die("❌ Erreur : Impossible de lire le fichier PDF.");
    }
    $file_content_base64 = chunk_split(base64_encode($file_content));
}

function getStats($db, $id_pro, $period)
{
    switch ($period) {
        case 'daily':
            $dateFormat = '%Y-%m-%d';
            break;
        case 'weekly':
            // Année-Semaine (ISO)
            $dateFormat = '%x-%v';
            break;
        case 'monthly':
            $dateFormat = '%Y-%m';
            break;
        case 'quarterly':
            // Trimestre: concat année + trimestre
            // MySQL: QUARTER(date)
            // Pas possible avec DATE_FORMAT directement, on fait concat
            // On fera une requête spécifique
            break;
        case 'yearly':
            $dateFormat = '%Y';
            break;
        default:
            $dateFormat = '%Y-%m';
    }

    if ($period === 'quarterly') {
        $revenus = [];

        if (!empty($numero_client)) {
            $sqlRevenus = "
        SELECT 
            CONCAT(YEAR(transaction_date), '-Q', QUARTER(transaction_date)) AS periode,
            SUM(amount_cents) / 100 AS total
        FROM transactions
        WHERE numero_pro = ? AND status = 'captured' AND numero_client IS NOT NULL
        GROUP BY periode
        ORDER BY periode ASC
    ";

            $params = [$id_pro, $numero_client];

            $stmt = $db->prepare($sqlRevenus);
            $stmt->execute($params);
            $revenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sqlRdvs = "
            SELECT 
                CONCAT(YEAR(date), '-Q', QUARTER(date)) AS periode,
                COUNT(*) AS total
            FROM rdvs
            WHERE numero_pro = ? AND etat = 'confirme'
            GROUP BY periode
            ORDER BY periode ASC
        ";
        $stmtRdvs = $db->prepare($sqlRdvs);
        $stmtRdvs->execute([$id_pro]);
        $rdvs = $stmtRdvs->fetchAll(PDO::FETCH_KEY_PAIR);

        return [$revenus, $rdvs];
    } else {
        $sqlRevenus = "
            SELECT DATE_FORMAT(transaction_date, '$dateFormat') AS periode, SUM(amount_cents) / 100 AS total
            FROM transactions
            WHERE numero_pro = ? AND status = 'captured' AND numero_client IS NOT NULL
            GROUP BY periode
            ORDER BY periode ASC
        ";
        $stmtRev = $db->prepare($sqlRevenus);
        $stmtRev->execute([$id_pro]);
        $revenus = $stmtRev->fetchAll(PDO::FETCH_KEY_PAIR);

        $sqlRdvs = "
            SELECT DATE_FORMAT(date, '$dateFormat') AS periode, COUNT(*) AS total
            FROM rdvs
            WHERE numero_pro = ? AND etat = 'confirme'
            GROUP BY periode
            ORDER BY periode ASC
        ";
        $stmtRdvs = $db->prepare($sqlRdvs);
        $stmtRdvs->execute([$id_pro]);
        $rdvs = $stmtRdvs->fetchAll(PDO::FETCH_KEY_PAIR);

        return [$revenus, $rdvs];
    }
}

// Récupérer toutes les données pour chaque période
$periods = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
$stats = [];

foreach ($periods as $period) {
    list($revenus, $rdvs) = getStats($db, $id_pro, $period);

    // Fusionner les périodes (clé) pour avoir une liste ordonnée des périodes uniques
    $allPeriods = array_unique(array_merge(array_keys($revenus), array_keys($rdvs)));
    sort($allPeriods);

    $revenusData = [];
    $rdvsData = [];
    foreach ($allPeriods as $p) {
        $revenusData[] = $revenus[$p] ?? 0;
        $rdvsData[] = $rdvs[$p] ?? 0;
    }

    // Formatage label pour affichage selon période
    $labels = [];
    foreach ($allPeriods as $p) {
        switch ($period) {
            case 'daily':
                // '2025-06-18' -> '18 Jun 2025'
                $labels[] = date('d M Y', strtotime($p));
                break;
            case 'weekly':
                // '2025-25' -> Semaine 25 2025
                // Le format ISO année-semaine : %x-%v (ex: 2025-25)
                $parts = explode('-', $p);
                if (count($parts) == 2) {
                    $labels[] = "Semaine {$parts[1]} {$parts[0]}";
                } else {
                    $labels[] = $p;
                }
                break;
            case 'monthly':
                // '2025-06' -> 'Jun 2025'
                $labels[] = date('M Y', strtotime($p . '-01'));
                break;
            case 'quarterly':
                // '2025-Q2'
                $labels[] = $p;
                break;
            case 'yearly':
                // '2025'
                $labels[] = $p;
                break;
            default:
                $labels[] = $p;
        }
    }

    $stats[$period] = [
        'labels' => $labels,
        'revenus' => $revenusData,
        'rdvs' => $rdvsData,
    ];
}

// Récupération des autres données affichées sur le dashboard (identique à ton code)
$sqlBalance = "SELECT SUM(amount_cents) AS total_cents FROM transactions WHERE numero_pro = ? AND status = 'captured' AND numero_client IS NOT NULL";
$stmtBalance = $db->prepare($sqlBalance);
$stmtBalance->execute([$id_pro]);
$resultBalance = $stmtBalance->fetch(PDO::FETCH_ASSOC);
$walletBalanceCents = $resultBalance['total_cents'] ?? 0;
$walletBalance = $walletBalanceCents / 100;

$sqlRdvs = "SELECT COUNT(*) AS total_rdvs FROM rdvs WHERE numero_pro = ? AND etat = 'confirme' ";
$stmtRdvs = $db->prepare($sqlRdvs);
$stmtRdvs->execute([$id_pro]);
$resultRdvs = $stmtRdvs->fetch(PDO::FETCH_ASSOC);
$totalRdvs = $resultRdvs['total_rdvs'] ?? 0;

$sqlvue = "SELECT COUNT(*) AS total_vue FROM vues_professionnels WHERE numero_pro = ? AND date = CURDATE() ";
$stmtvue = $db->prepare($sqlvue);
$stmtvue->execute([$id_pro]);
$resultvue = $stmtvue->fetch(PDO::FETCH_ASSOC);
$totalvue = $resultvue['total_vue'] ?? 0;
// Dernières transactions
$sql = "SELECT * FROM transactions 
        WHERE numero_pro = ? 
        AND status = 'captured' 
        AND numero_client IS NOT NULL 
        ORDER BY transaction_date DESC 
        LIMIT 10";
$stmt = $db->prepare($sql);
$stmt->execute([$id_pro]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($transactions)) {
    // Extraire tous les numero_client distincts
    $client_ids = array_column($transactions, 'numero_client');
    $placeholders = implode(',', array_fill(0, count($client_ids), '?'));

    $sql_t = "SELECT * FROM login_user WHERE numero_client IN ($placeholders)";
    $stmt_t = $db->prepare($sql_t);
    $stmt_t->execute($client_ids);
    $clients = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
} else {
    $clients = [];
}

// Derniers RDVs confirmés
$sqlRdv = "
SELECT r.*, u.nom AS client_nom, u.prenom AS client_prenom, u.email AS client_email
FROM rdvs r
JOIN login_user u ON r.numero_client = u.numero_client
WHERE r.numero_pro = ? AND r.etat = 'confirme'
ORDER BY r.date DESC
LIMIT 10
";
$stmtRdv = $db->prepare($sqlRdv);
$stmtRdv->execute([$id_pro]);
$rdvs = $stmtRdv->fetchAll(PDO::FETCH_ASSOC);
$stmtlocation = $db->prepare("SELECT * FROM locations WHERE numero_pro = ?");
$stmtlocation->execute([$id_pro]);
$locations = $stmtlocation->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
    <title>Dashboard - E-Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js/minified/introjs.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener("DOMContentLoaded", function () {
            const menuBtn = document.getElementById("menuBtn");
            menuBtn.addEventListener("click", toggleMenu);
        });

        function toggleMenu() {
            // Exemple de logique
            const menu = document.getElementById("menu");
            if (menu) menu.classList.toggle("hidden");
        }
    </script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        function chargerNombreMessagesNonLus() {
            $.post("check-message", {}, function (response) {
                const badge = $('#message-badge');
                if (response.nb_non_lus > 0) {
                    badge.text(response.nb_non_lus > 99 ? '99+' : response.nb_non_lus);
                    badge.removeClass('hidden');
                } else {
                    badge.addClass('hidden');
                }
            }, 'json').fail(() => {
                console.warn("Erreur lors du chargement des messages non lus");
            });
        }
        setInterval(chargerNombreMessagesNonLus, 12000);
        chargerNombreMessagesNonLus();

    </script>


</head>


<body class="bg-gray-100 flex flex-col md:flex-row">

    <?php include('../includes/aside.php'); ?>
    <main id="mainContent" class="flex-grow p-4 pt-20 md:pt-6 md:p-6 transition-all duration-300 ease-in-out md:ml-64">
        <header class=" top-0 z-50" x-data="{ openMobile: false, openDropdown: false }">
            <div class="max-w-7xl mx-auto flex justify-between items-center p-4 md:p-6">
                <a href="/" class="flex items-center space-x-3">

                    <nav class="hidden md:flex space-x-8 items-center text-gray-700 font-medium">
                        <a href="dashbord" class="hover:text-green-600 transition">Accueil</a>
                        <a href="contact" class="hover:text-green-600 transition">Contact</a>

                        <?php if (!isset($_SESSION['id_pro'])) { ?>
                            <a href="connexion" class="hover:text-green-600">Connexion</a>
                            <a href="inscription-pro"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-semibold transition">Inscription
                                pro</a>
                            <a href="inscription-particulier"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-semibold transition">Inscription
                                particulier</a>
                        <?php } else { ?>
                            <div class="relative" x-data="{ openDropdown: false }" @click.outside="openDropdown = false">
                                <button @click="openDropdown = !openDropdown"
                                    class="inline-flex items-center bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                    <span class="material-symbols-outlined mr-2">
                                        <?= isset($_SESSION['id_pro']) ? 'enterprise' : 'person' ?>
                                    </span>
                                    <?= isset($_SESSION['id_pro']) ? htmlspecialchars($_SESSION['name_company']) : htmlspecialchars($_SESSION['prenom']) ?>
                                    <svg class="w-4 h-4 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="openDropdown" x-transition
                                    class="absolute right-0 mt-2 w-56 bg-white border rounded-md shadow-lg z-50">
                                    <a href="dashbord" class="flex items-center px-4 py-2 text-sm hover:bg-gray-100"><span
                                            class="material-symbols-outlined mr-2">bar_chart</span> Tableau de bord</a>
                                    <a href="mes_rdvs.php"
                                        class="flex items-center px-4 py-2 text-sm hover:bg-gray-100"><span
                                            class="material-symbols-outlined mr-2">event</span> Rendez-vous</a>
                                    <?php if (!isset($_SESSION['role2']) || $_SESSION['role2'] !== 'technicien'): ?>
                                        <a href="message"
                                            class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 relative">
                                            <span class="material-symbols-outlined mr-2">chat</span>
                                            Messages
                                            <span id="message-badge"
                                                class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full">
                                                3
                                            </span>
                                        </a>
                                        <a href="setting" class="flex items-center px-4 py-2 text-sm hover:bg-gray-100"><span
                                                class="material-symbols-outlined mr-2">settings</span> Paramètres</a>
                                    <?php endif; ?>
                                    <a href="z"
                                        class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-100"><span
                                            class="material-symbols-outlined mr-2">logout</span> Déconnexion</a>
                                </div>
                            </div>
                        <?php } ?>
                    </nav>
                    <button @click="openMobile = !openMobile" class="md:hidden focus:outline-none">
                        <svg class="w-7 h-7 text-gray-700" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
            </div>
            <div x-show="openMobile" x-transition class="md:hidden px-4 py-4 space-y-2 bg-white shadow-inner">
                <a href="dzshbord" class="block px-4 py-2 rounded hover:bg-green-50">Accueil</a>
                <a href="contact" class="block px-4 py-2 rounded hover:bg-green-50">Contact</a>

                <?php if (!isset($_SESSION['id_client']) && !isset($_SESSION['id_pro'])) { ?>
                    <a href="connexion" class="block text-green-600 font-semibold text-center">Connexion</a>
                    <a href="inscription-pro"
                        class="block bg-green-600 text-white py-2 rounded-md text-center font-semibold hover:bg-green-700">Inscription
                        pro</a>
                    <a href="inscription-particulier"
                        class="block bg-green-600 text-white py-2 rounded-md text-center font-semibold hover:bg-green-700">Inscription
                        particulier</a>
                <?php } else { ?>
                    <div x-data="{ openDropdownMobile: false }" class="border-t pt-4">
                        <button @click="openDropdownMobile = !openDropdownMobile"
                            class="w-full flex justify-between items-center bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            <div class="flex items-center">
                                <span class="material-symbols-outlined mr-2">
                                    <?= isset($_SESSION['id_pro']) ? 'enterprise' : 'person' ?>
                                </span>
                                <?= isset($_SESSION['id_pro']) ? htmlspecialchars($_SESSION['name_company']) : htmlspecialchars($_SESSION['prenom']) ?>
                            </div>
                            <svg class="w-4 h-4 ml-2 transform" :class="{ 'rotate-180': openDropdownMobile }"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="openDropdownMobile" x-transition class="mt-2 space-y-2">
                            <a href="dashbord" class="block px-4 py-2 text-sm hover:bg-gray-100"><span
                                    class="material-symbols-outlined mr-2">bar_chart</span> Tableau de bord</a>
                            <a href="mes_rdvs.php" class="block px-4 py-2 text-sm hover:bg-gray-100"><span
                                    class="material-symbols-outlined mr-2">event</span> Rendez-vous</a>

                            <a href="message" class="block px-4 py-2 text-sm hover:bg-gray-100"><span
                                    class="material-symbols-outlined mr-2">chat</span> Messages</a>
                            <a href="setting" class="block px-4 py-2 text-sm hover:bg-gray-100"><span
                                    class="material-symbols-outlined mr-2">settings</span> Paramètres</a>

                            <a href="z" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100"><span
                                    class="material-symbols-outlined mr-2">logout</span> Déconnexion</a>
                        </div>
                    </div>
                <?php } ?>
            </div>

        </header>
        <h1 class="text-2xl font-bold mb-6">Tableau de bord professionnel</h1>
        <?= $_SESSION['role2'] ?>
        <?php
        $data = getProfilCompletion($id_pro, $db);
        $completion = $data['completion'];
        $missing = $data['missing'];
        ?>
        <?php if (isset($completion) && $completion < 100): ?>
            <div class="bg-white rounded shadow p-4">
                <h2 class="text-lg font-bold text-green-600 mb-2">Complétion du profil</h2>

                <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                    <div class="bg-green-500 h-4 rounded-full transition-all duration-500 ease-in-out"
                        style="width: <?= intval($completion) ?>%;"></div>
                </div>

                <p class="text-sm text-gray-600 mb-2"><?= intval($completion) ?>% du profil complété</p>
            </div>
        <?php endif; ?>
        <?php if (!empty($missing)): ?>
            <div class="mt-2">
                <p class="text-sm font-semibold text-red-600 mb-1">Champs à compléter :</p>
                <ul class="list-disc list-inside text-sm text-gray-700">
                    <?php foreach ($missing as $item): ?>
                        <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        </div>
        <!-- Cartes -->
        <div id="solde" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <?php if (!isset($_SESSION['role2']) || $_SESSION['role2'] !== 'technicien'): ?>
                <div class="bg-white p-4 rounded shadow relative">
                    <h2 class="text-lg font-semibold">Solde du portefeuille</h2>
                    <p class="text-2xl font-bold text-green-500">€<span
                            id="wallet-balance"><?= number_format($walletBalance, 2, ',', ' ') ?></span></p>

                    <button id="withdraw-btn"
                        class="mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">
                        Retirer mon solde
                    </button>


                    <div id="withdraw-popup"
                        class="hidden absolute top-16 right-0 bg-white border border-gray-300 shadow-lg rounded p-4 w-64 z-50">
                        <p class="mb-4">Voulez-vous vraiment retirer votre solde actuel ?</p>
                        <div class="flex justify-end gap-2">
                            <button id="cancel-btn"
                                class="px-3 py-1 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition">Annuler</button>
                            <form method="post" action="withdraw_balance.php">
                                <button type="submit"
                                    class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 transition">
                                    Retirer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
            <?php if ($abonnement['nom_abonnement'] == "Restylé" || $abonnement['nom_abonnement'] == "autoline") { ?>
                <?php if (!isset($_SESSION['role2']) || $_SESSION['role2'] !== 'technicien'): ?>
                    <div class="bg-white p-4 rounded shadow">
                        <h2 class="text-lg font-semibold">Vues du profil</h2>
                        <p class="text-2xl font-bold text-blue-500" id="profile-views"><?= $totalvue ?></p>
                    </div>
                <?php endif; ?>
                <div class="bg-white p-4 rounded shadow">
                    <h2 class="text-lg font-semibold">RDV pris</h2>
                    <p class="text-2xl font-bold text-purple-500" id="appointments"><?= $totalRdvs ?></p>
                </div>
            </div>
        <?php } else { ?>
            <?php if (!isset($_SESSION['role2']) || $_SESSION['role2'] !== 'technicien'): ?>
                <div class="bg-white p-4 rounded shadow relative overflow-hidden">
                    <h2 class="text-lg font-semibold">Vues du profil</h2>
                    <div class="flex items-center text-yellow-600 text-xl font-semibold mt-2">
                        <span class="material-symbols-outlined text-yellow-600 mr-2 text-3xl">
                            crown
                        </span>
                        <a href="/abonnement" class="hover:underline">Passer à un abonnement supérieur</a>
                    </div>
                </div>

                <!-- Bloc RDV pris bloqué -->
                <div class="bg-white p-4 rounded shadow relative overflow-hidden">
                    <h2 class="text-lg font-semibold">RDV pris</h2>
                    <div class="flex items-center text-yellow-600 text-xl font-semibold mt-2">
                        <span class="material-symbols-outlined text-yellow-600 mr-2 text-3xl">
                            crown
                        </span>
                        <a href="/abonnement" class="hover:underline">Passer à un abonnement supérieur</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php } ?>
        </div>
        <?php if ($specialisation == "vendeur-auto" || $specialisation == "loueur"): ?>
            <div class="bg-white p-4 rounded shadow mb-6">
                <h2 class="text-lg font-semibold mb-4">Vehicule réserver</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm font-light">
                        <thead class="bg-gray-200 text-gray-700">
                            <tr>
                                <th class="px-4 py-2">Immatriculation</th>
                                <th class="px-4 py-2">Modèle</th>
                                <th class="px-4 py-2">Marque</th>
                                <th class="px-4 py-2">Client</th>
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Heure</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($locations) > 0): ?>
                                <?php foreach ($locations as $loc): ?>
                                    <?php $data_client = $db->prepare("SELECT * FROM login_user WHERE numero_client = ?");
                                    $data_client->execute([$loc['id_client']]);
                                    $client = $data_client->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                    <tr class="border-b">
                                        <td class="px-4 py-2"><?= htmlspecialchars($loc['plaque']) ?></td>
                                        <td class="px-4 py-2"><?= htmlspecialchars($loc['modele']) ?></td>
                                        <td class="px-4 py-2"><?= htmlspecialchars($loc['marque']) ?></td>
                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars($client['nom']) . ' ' . htmlspecialchars($client['prenom']) ?>
                                        </td>
                                        <td class="px-4 py-2"><?= date('d/m/Y', strtotime($loc['date_depart'])) ?></td>
                                        <td class="px-4 py-2"><?= htmlspecialchars($loc['heure_depart']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-2">Aucun véhicule enregistré.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>

            <div class="bg-white p-6 rounded-2xl shadow-lg mb-6">
                <h2 class="text-xl sm:text-2xl font-bold mb-4 text-green-700">Derniers RDV pris</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm font-light divide-y divide-gray-200">
                        <thead class="bg-green-100 text-green-800 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Prestation</th>
                                <th class="px-4 py-3">Client</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Heure</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php if (!empty($rdvs)): ?>
                                <?php foreach ($rdvs as $rdv): ?>
                                    <?= $rdv['immatriculation'] ?>
                                    <tr class="hover:bg-green-50 cursor-pointer rdv-row"
                                        data-immat="<?= htmlspecialchars($rdv['immatriculation']) ?>"
                                        data-prestation="<?= htmlspecialchars($rdv['nom_prestation']) ?>"
                                        data-client="<?= htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']) ?>"
                                        data-date="<?= date('d/m/Y', strtotime($rdv['date'])) ?>"
                                        data-heure="<?= htmlspecialchars($rdv['heure']) ?>">
                                        <td class="px-4 py-3"><?= htmlspecialchars($rdv['nom_prestation']) ?></td>
                                        <td class="px-4 py-3">
                                            <?= htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']) ?>
                                        </td>
                                        <td class="px-4 py-3"><?= date('d/m/Y', strtotime($rdv['date'])) ?></td>
                                        <td class="px-4 py-3"><?= htmlspecialchars($rdv['heure']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-center text-gray-400">Aucun rendez-vous pris</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal RDV -->
            <div id="rdvModal"
                class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 relative">
                    <button type="button" id="closeRdvModal"
                        class="absolute top-4 right-4 text-gray-400 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <h3 class="text-xl font-bold text-green-700 mb-4">Détails du RDV</h3>
                    <div class="space-y-2 text-gray-700">
                        <p><strong>Prestation :</strong> <span id="modalPrestation"></span></p>
                        <p><strong>Client :</strong> <span id="modalClient"></span></p>
                        <p><strong>Date :</strong> <span id="modalDate"></span></p>
                        <p><strong>Heure :</strong> <span id="modalHeure"></span></p>
                        <p><strong>Véhicule :</strong> <span id="modalVehicule"></span></p>
                        <p><strong>Immatriculation :</strong> <span id="modalImmat"></span></p>
                    </div>
                </div>
            </div>

            <script nonce="<?= htmlspecialchars($nonce) ?>">
                document.addEventListener('DOMContentLoaded', function () {
                    const rdvModal = document.getElementById('rdvModal');
                    const closeModalBtn = document.getElementById('closeRdvModal');

                    const modalPrestation = document.getElementById('modalPrestation');
                    const modalClient = document.getElementById('modalClient');
                    const modalDate = document.getElementById('modalDate');
                    const modalHeure = document.getElementById('modalHeure');
                    const modalVehicule = document.getElementById('modalVehicule');
                    const modalImmat = document.getElementById('modalImmat');

                    document.querySelectorAll('.rdv-row').forEach(row => {
                        row.addEventListener('click', async () => {
                            const immat = row.dataset.immat;

                            try {
                                const res = await fetch('get_vehicule?immat=' + encodeURIComponent(immat));
                                const data = await res.json();

                                modalPrestation.textContent = row.dataset.prestation;
                                modalClient.textContent = row.dataset.client;
                                modalDate.textContent = row.dataset.date;
                                modalHeure.textContent = row.dataset.heure;
                                modalVehicule.textContent = data.marque + ' ' + data.modele;
                                modalImmat.textContent = data.immatriculation;

                                rdvModal.classList.remove('hidden');
                            } catch (err) {
                                console.error('Erreur fetch vehicule:', err);
                            }
                        });
                    });

                    closeModalBtn.addEventListener('click', () => rdvModal.classList.add('hidden'));
                    rdvModal.addEventListener('click', (e) => { if (e.target === rdvModal) rdvModal.classList.add('hidden'); });
                });
            </script>


        <?php endif; ?>
        <div id="transac" class="bg-white p-4 rounded shadow mb-6">
            <?php if (!isset($_SESSION['role2']) || $_SESSION['role2'] !== 'technicien'): ?>
                <h2 class="text-lg font-semibold mb-4">Dernières transactions acceptées</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm font-light">
                        <thead class="bg-gray-200 text-gray-700">
                            <tr>
                                <th class="px-4 py-2">N° de transaction</th>
                                <th class="px-4 py-2">Montant (€)</th>
                                <th class="px-4 py-2">Client</th>
                                <th class="px-4 py-2">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <?php
                                    // Trouver le client correspondant
                                    $client = null;
                                    foreach ($clients as $c) {
                                        if ($c['numero_client'] == $transaction['numero_client']) {
                                            $client = $c;
                                            break;
                                        }
                                    }
                                    ?>
                                    <tr class="border-b">
                                        <td class="px-4 py-2"><?= htmlspecialchars($transaction['transaction_id']) ?></td>
                                        <td class="px-4 py-2 text-green-600 font-bold">
                                            +<?= number_format($transaction['amount_cents'] / 100, 2, ',', ' ') ?></td>
                                        <td class="px-4 py-2">
                                            <?= $client ? htmlspecialchars($client['nom'] . ' ' . $client['prenom']) : 'Client inconnu' ?>
                                        </td>
                                        <td class="px-4 py-2">
                                            <?= htmlspecialchars(date('d/m/Y', strtotime($transaction['transaction_date']))) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td class="px-4 py-2" colspan="4">Aucune transaction acceptée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($abonnement['nom_abonnement'] == "Restylé" || $abonnement['nom_abonnement'] == "autoline") { ?>
                <div class="bg-white p-4 rounded shadow">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold">Statistiques des revenus et RDV</h2>
                        <select id="periodFilter" class="border border-gray-300 rounded p-1">
                            <option value="daily">Journalier</option>
                            <option value="weekly">Hebdomadaire</option>
                            <option value="monthly" selected>Mensuel</option>
                            <option value="quarterly">Trimestriel</option>
                            <option value="yearly">Annuel</option>
                        </select>
                    </div>
                    <canvas id="statsChart" width="600" height="300"></canvas>
                </div>
            <?php } else { ?>
                <div class="relative bg-white p-4 rounded shadow overflow-hidden">
                    <!-- Le titre reste visible -->
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Statistiques des revenus et RDV</h2>
                    </div>

                    <!-- Contenu flouté + bloqué -->
                    <div class="relative opacity-40 blur-sm pointer-events-none select-none">
                        <div class="flex items-center justify-between mb-4">
                            <select id="periodFilter" class="border border-gray-300 rounded p-1 bg-white">
                                <option value="daily">Journalier</option>
                                <option value="weekly">Hebdomadaire</option>
                                <option value="monthly" selected>Mensuel</option>
                                <option value="quarterly">Trimestriel</option>
                                <option value="yearly">Annuel</option>
                            </select>
                        </div>
                        <canvas id="statsChart" width="600" height="300"></canvas>
                    </div>

                    <!-- Overlay VIP (couvre toute la zone sauf le h2) -->
                    <div
                        class="absolute top-12 left-0 right-0 bottom-0 bg-black bg-opacity-100 flex flex-col items-center justify-center text-white rounded-b">
                        <!-- <img src="/assets/icons/vip.png" alt="VIP" class="w-16 h-16 mb-4"> -->
                        <p class="text-xl font-semibold mb-2 text-center px-4">Statistiques réservées aux abonnement Restylé et
                            Autoline</p>
                        <a href="/abonnement" class="bg-green-500 hover:bg-green-600 text-black font-bold py-2 px-4 rounded">
                            Passer à un abonnement supérieur
                        </a>
                    </div>
                </div>
            <?php } ?>
        <?php endif; ?>

    </main>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        // Menu mobile toggle
        function toggleMenu() {
            document.getElementById("mobileMenu").classList.toggle("hidden");
        }
    </script>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        // Sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('openSidebarBtn');
        const closeBtn = document.getElementById('closeSidebarBtn');
        const mainContent = document.getElementById('mainContent');

        // Données PHP encodées en JSON pour JS
        const stats = <?= json_encode($stats) ?>;

        const ctx = document.getElementById('statsChart').getContext('2d');
        let chart;

        function createChart(period) {
            const data = stats[period];
            if (!data) return;

            const config = {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Revenus (€)',
                        data: data.revenus,
                        borderColor: 'rgba(34,197,94,1)', // vert
                        backgroundColor: 'rgba(34,197,94,0.2)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y',
                    },
                    {
                        label: 'RDV confirmés',
                        data: data.rdvs,
                        borderColor: 'rgba(59,130,246,1)', // bleu
                        backgroundColor: 'rgba(59,130,246,0.2)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y1',
                    }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    stacked: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenus (€)'
                            },
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                            title: {
                                display: true,
                                text: 'RDV confirmés'
                            }
                        }
                    },
                }
            };

            if (chart) {
                chart.destroy();
            }
            chart = new Chart(ctx, config);
        }

        // Initialisation sur 'monthly'
        createChart('monthly');

        // Changement du filtre
        document.getElementById('periodFilter').addEventListener('change', (e) => {
            createChart(e.target.value);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/intro.js/minified/intro.min.js"></script>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        const profilPro = <?php echo json_encode($profil_pro); ?>;

        function startTutorial() {

            const intro = introJs();
            intro.setOptions({
                steps: [{
                    intro: "Bienvenue dans votre tableau de bord professionnel. Voici un rapide tour d'horizon."
                },
                {
                    element: '#solde',
                    intro: "Voici votre solde, les vues de votre profil, et le nombre de rendez-vous."
                },
                {
                    element: '#transac',
                    intro: "Voici les 10 dernières transactions, avec leur numéro, le montant ainsi que le nom et prénom du client."
                },

                {
                    element: '#statsChart',
                    intro: "Visualisez ici l'évolution de vos revenus et de vos RDV."
                },
                {
                    element: '#sidebar',
                    intro: "Utilisez ce menu pour accéder à toutes les sections de votre compte."
                }
                ],
                showProgress: true,
                exitOnOverlayClick: false,
                showStepNumbers: true,
                disableInteraction: true,
                doneLabel: "Terminer",
                nextLabel: "Suivant",
                prevLabel: "Précédent",

            });
            intro.oncomplete(function () {
                // redirection quand l'utilisateur clique sur "Terminer"
                window.location.href = 'profil';
            });
            intro.start();
        }

        // Démarrer automatiquement si profil non complété
        if (profilPro === 0) {
            ;
            startTutorial();
        }
    </script>
    <script>
        const btn = document.getElementById('withdraw-btn');
        const popup = document.getElementById('withdraw-popup');
        const cancel = document.getElementById('cancel-btn');

        btn.addEventListener('click', () => {
            popup.classList.remove('hidden');
        });

        cancel.addEventListener('click', () => {
            popup.classList.add('hidden');
        });
    </script>
</body>

</html>