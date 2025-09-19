<?php
session_start();
$numero_pro = $_SESSION['id_pro'];
require '../vendor/autoload.php';
require_once '../db/dbconnect2.php';
require_once '../includes/webhook.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$data_pro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
$data_pro->execute([$numero_pro]);
$pro = $data_pro->fetch(PDO::FETCH_ASSOC);

$data_prestation = $db->prepare('SELECT * FROM prestations WHERE numero_pro = ?');
$data_prestation->execute([$numero_pro]);
$prestations = $data_prestation->fetchAll(PDO::FETCH_ASSOC);

// Construction des lignes du tableau prestations avec calcul TVA et TTC
$prestationsHtml = '';
foreach ($prestations as $p) {
    $prixHT = (float)$p['prix'];
    $tva = isset($p['tva']) ? (float)$p['tva'] : 20; // par défaut 20% si non défini
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
    $duree_totale += (float)$p['duree'];
}
$taux_horaire = isset($pro['taux_horaire']) ? (float)$pro['taux_horaire'] : 0;
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
<p>Sauf demande contraire,<strong>' . htmlspecialchars($pro['denomination']) . '</strong>autorise le client à mentionner son nom ou logo à titre de référence.</p>

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

$filename = "CGP-" . preg_replace('/[^a-zA-Z0-9_-]/', '', $pro['denomination']) . ".pdf";
$dompdf->stream($filename, ['Attachment' => false]);
