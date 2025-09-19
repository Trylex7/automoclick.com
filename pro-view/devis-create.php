<?php
require_once '../header.php';
require_once('../db/dbconnect2.php');
require_once '../includes/webhook.php';
session_start();
if (!isset($_SESSION['id_pro'])) {
    header('Location: ../login.php');
}
require '../vendor/autoload.php';
use Dompdf\Dompdf;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function genererNumeroUnique(PDO $db): string
    {
        do {
            $numero = str_pad(mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("SELECT COUNT(*) FROM devis WHERE numero = ? AND statut = 'devis'");
            $stmt->execute([$numero]);
            $existe = $stmt->fetchColumn() > 0;
        } while ($existe);

        return $numero;
    }

    $numero_f = genererNumeroUnique($db);
    $client_name = trim($_POST['client_name'] ?? 'Client');
    $client_address = trim($_POST['client_address'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $numero_client = trim($_POST['numero_client'] ?? '');

    // 🔍 Recherche du numéro client si email fourni mais numéro absent
    if (empty($numero_client) && !empty($client_email)) {
        $stmt = $db->prepare('SELECT numero_client FROM login_user WHERE email = ?');
        $stmt->execute([$client_email]);
        $data_client = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data_client) {
            $numero_client = $data_client['numero_client'];
        }
    }

    $types = $_POST['type_prestation'] ?? [];
    $refs = $_POST['reference'] ?? [];
    $prix_hts = $_POST['prix_ht'] ?? [];
    $qtes = $_POST['quantite'] ?? [];
    $tvas = $_POST['tva'] ?? [];

    $prestations = [];
    $total_ht_global = 0;
    $total_tva_global = 0;
    $total_ttc_global = 0;

    for ($i = 0; $i < count($types); $i++) {
        $libelle_id = trim($types[$i]);
        $libelle = $libelle_id;
        if (is_numeric($libelle_id)) {
            $stmt = $db->prepare("SELECT nom FROM prestations WHERE id = ? AND numero_pro = ?");
            $stmt->execute([$libelle_id, $_SESSION['numero_pro']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $libelle = $result['nom'];
            }
        }
        if ($libelle !== '') {
            $ref = trim($refs[$i] ?? '');
            $prix_ht = (float) $prix_hts[$i];
            $qte = (int) $qtes[$i];
            $tva = (float) $tvas[$i];

            $total_ht = $prix_ht * $qte;
            $total_tva = $total_ht * ($tva / 100);
            $total_ttc = $total_ht + $total_tva;

            $prestations[] = [
                'libelle' => $libelle,
                'ref' => $ref,
                'prix_ht' => $prix_ht,
                'qte' => $qte,
                'tva' => $tva,
                'total_ht' => $total_ht,
                'total_tva' => $total_tva,
                'total_ttc' => $total_ttc
            ];

            $total_ht_global += $total_ht;
            $total_tva_global += $total_tva;
            $total_ttc_global += $total_ttc;
        }
    }

    $devis_numero = strtoupper(uniqid('DV-'));
    $html = '
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 40px; color: #1f2937; font-size: 14px; }
        h1 { color: #16a34a; text-align: right; font-size: 36px; margin-bottom: 20px; }
        .info-box { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .info-box p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th { color: black; padding: 12px; text-align: left; }
        td { padding: 10px; border-top: 1px solid #e5e7eb; }
        tr:nth-child(even) { background-color: #f0fdf4; }
        .right { text-align: right; }
        .total { font-weight: bold; }
        .footer-devis { margin-top: 40px; font-size: 13px; }
    </style>
</head>
<body>
    <h1>DEVIS</h1>

    <div class="info-box right">
        <p><strong>N° SIRET:</strong> ' . htmlspecialchars($_SESSION['siret']) . '</p>
        <p><strong>Adresse:</strong> ' . htmlspecialchars($_SESSION['adresse']) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($_SESSION['email']) . '</p>
    </div>

    <div class="info-box">
        <p><strong>Client:</strong> ' . htmlspecialchars($client_name) . '</p>
        <p><strong>Adresse:</strong> ' . htmlspecialchars($client_address) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($client_email) . '</p>
        <p><strong>N° de devis:</strong> ' . $devis_numero . '</p>
        <p><strong>Date:</strong> ' . date('d/m/Y') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Référence</th>
                <th class="right">Prix Unitaire HT</th>
                <th class="right">Quantité</th>
                <th class="right">TVA %</th>
                <th class="right">Total TTC</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($prestations as $p) {
        $html .= '<tr>
        <td>' . htmlspecialchars($p['libelle']) . '</td>
        <td>' . htmlspecialchars($p['ref']) . '</td>
        <td class="right">' . number_format($p['prix_ht'], 2, ',', ' ') . ' €</td>
        <td class="right">' . $p['qte'] . '</td>
        <td class="right">' . number_format($p['tva'], 2, ',', ' ') . ' %</td>
        <td class="right">' . number_format($p['total_ttc'], 2, ',', ' ') . ' €</td>
    </tr>';
    }

    $html .= '</tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="right total">Total HT</td>
            <td class="right total">' . number_format($total_ht_global, 2, ',', ' ') . ' €</td>
        </tr>
        <tr>
            <td colspan="5" class="right total">TVA</td>
            <td class="right total">' . number_format($total_tva_global, 2, ',', ' ') . ' €</td>
        </tr>
        <tr>
            <td colspan="5" class="right total">Total TTC</td>
            <td class="right total">' . number_format($total_ttc_global, 2, ',', ' ') . ' €</td>
        </tr>
    </tfoot>
</table>

<div class="footer-devis">
    <p><strong>Durée de validité :</strong> 1 mois</p>
    <p class="right"><strong>Signature du client</strong></p>
    <p class="right style="margin-top: 50px"><strong>Devis générer par <a href="https://www.automoclick.com"style="color: #16a34a;">Automoclick</a> - www.automoclick.com</strong></p>
</div>
</body>
</html>';

    // ✅ Génération PDF
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'D_' . $numero_f . '.pdf';
$dir = realpath(__DIR__ . '/../public/devis_pdf');

if ($dir === false) {
    $dir = __DIR__ . '/../public/devis_pdf';
    mkdir($dir, 0775, true);
    chmod($dir, 0775);
}

$filePath = $dir . '/' . $filename;
file_put_contents($filePath, $dompdf->output());
    $insert = $db->prepare("
    INSERT INTO devis (numero, client_id, pro_id, montant_total, chemin_pdf, statut)
    VALUES (:numero, :client, :pro, :montant, :chemin, :statut)
");
    $insert->execute([
        ':numero' => $numero_f,
        ':client' => $numero_client ?? NULL,
        ':pro' => $_SESSION['id_pro'],
        ':montant' => $total_ttc_global,
        ':chemin' => $filename,
        ':statut' => "devis",
    ]);
    // ✅ Affichage dans le navigateur
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    echo $dompdf->output();
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
    <title>Créer un devis personnalisé</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen text-gray-800 px-4 py-8">

    <div class="max-w-5xl mx-auto bg-white shadow-md rounded-xl p-8 space-y-6">
        <h1 class="text-3xl font-bold text-center text-green-600">Créer votre devis</h1>
        <input type="text" id="client_search" placeholder="🔍 Rechercher un client (nom, email, tel)"
            class="w-full px-4 py-3 mb-4 border border-green-400 rounded-lg shadow-sm focus:ring-2 focus:ring-green-400">
        <input type="hidden" id="client_id" name="client_id">
        <form id="quoteForm" method="POST" autocomplete="off" class="space-y-6">

            <div>
                <h3 class="text-xl font-semibold mb-4 text-gray-700">Infos client</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" name="client_numero" placeholder="Numero Client" required
                        class="w-full px-4 py-3 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-400">
                    <input type="text" name="client_name" placeholder="Nom / Société" required
                        class="w-full px-4 py-3 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-400">
                    <input type="text" name="client_address" placeholder="Adresse" required
                        class="w-full px-4 py-3 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-400">
                    <input type="email" name="client_email" placeholder="Email" required
                        class="w-full sm:col-span-2 px-4 py-3 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-400">
                </div>
            </div>

            <div>
                <h3 class="text-xl font-semibold mb-4 text-gray-700">Prestations</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border border-gray-300 rounded-lg">
                        <thead class="bg-gray-50 text-green-700">
                            <tr>
                                <th class="p-3">Prestations</th>
                                <th class="p-3">Référence</th>
                                <th class="p-3">Prix unitaire HT</th>
                                <th class="p-3">Quantité</th>
                                <th class="p-3">TVA (%)</th>
                                <th class="p-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="lineContainer" class="bg-white divide-y divide-gray-200">

                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <button id="addLineBtn" type="button"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition font-medium">
                        + Ajouter une ligne
                    </button>
                </div>
            </div>

            <div class="text-center">
                <button type="submit"
                    class="bg-green-600 px-6 py-3 text-white font-semibold rounded-lg hover:bg-green-700 transition">
                    Générer le devis
                </button>
            </div>
        </form>
    </div>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener("DOMContentLoaded", () => {
            const lineContainer = document.getElementById('lineContainer');
            const addLineBtn = document.getElementById("addLineBtn");

            async function addLine() {
                const res = await fetch('raz');
                const prestations = await res.json();

                const row = document.createElement('tr');

                let options = `<option value="">-- Choisir --</option>`;
                prestations.forEach(p => {
                    options += `<option value="${p.nom}" data-prix="${p.prix}" data-duree="${p.duree}" data-ref="${p.ref}" data-tva="${p.tva}">${p.nom}</option>`;
                });

                row.innerHTML = `
          <td class="p-2">
            <select name="type_prestation[]" class="w-full border px-2 py-1 rounded-lg prestation-select" required>
              ${options}
            </select>
          </td>
          <td class="p-2"><input name="reference[]" class="w-full border px-2 py-1 rounded-lg"></td>
          <td class="p-2"><input name="prix_ht[]" type="number" step="0.01" required class="w-full border px-2 py-1 rounded-lg"></td>
          <td class="p-2"><input name="quantite[]" type="number" min="1" step="1" required class="w-full border px-2 py-1 rounded-lg"></td>
          <td class="p-2"><input name="tva[]" type="number" value="8.5" class="w-full border px-2 py-1 rounded-lg"></td>
          <td class="p-2 text-center">
            <button type="button" class="delete-btn text-red-600 hover:underline">Supprimer</button>
          </td>
        `;
                lineContainer.appendChild(row);
            }

            lineContainer.addEventListener('change', function (e) {
                if (e.target.classList.contains('prestation-select')) {
                    const opt = e.target.options[e.target.selectedIndex];
                    const row = e.target.closest('tr');
                    row.querySelector('input[name="reference[]"]').value = opt.dataset.ref || '';
                    row.querySelector('input[name="prix_ht[]"]').value = opt.dataset.prix || '';
                    row.querySelector('input[name="tva[]"]').value = opt.dataset.tva || '8.5';
                }
            });

            lineContainer.addEventListener('click', function (e) {
                if (e.target.classList.contains('delete-btn')) {
                    e.target.closest('tr').remove();
                }
            });

            addLineBtn.addEventListener("click", addLine);

            const searchInput = document.getElementById('client_search');
            searchInput.addEventListener('input', function () {
                const val = this.value;
                if (val.length < 2) return;

                fetch('zar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'q=' + encodeURIComponent(val)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const c = data.client;
                            document.getElementById('client_id').value = c.id;
                            document.querySelector('[name="client_name"]').value = c.nom || '';
                            document.querySelector('[name="client_email"]').value = c.email || '';
                            document.querySelector('[name="client_numero"]').value = c.numero_client || '';
                        }
                    });
            });
        });
    </script>
</body>

</html>