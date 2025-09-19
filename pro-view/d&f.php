<?php
require_once '../includes/webhook.php';
require '../db/dbconnect2.php';
session_start();

if (!isset($_SESSION['id_pro'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /connexion?success_url={$currentUrl}");
    exit;
}
$pro_id = $_SESSION['id_pro'] ?? 0;

$search = trim($_GET['q'] ?? '');
$statut_filter = trim($_GET['statut'] ?? '');

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

if ($pro_id <= 0) {
    if ($isAjax) {
        echo "❌ Pro non identifié.";
        exit;
    } else {
        die("❌ Pro non identifié.");
    }
}

$search_param = '%' . $search . '%';

$sql = "
    SELECT devis.*, login_user.nom, login_user.prenom, login_user.email, login_user.numero_client
    FROM devis
    LEFT JOIN login_user ON devis.client_id = login_user.numero_client
    WHERE devis.pro_id = :pro_id
";

if (!empty($search)) {
    $sql .= "
        AND (
            devis.numero LIKE :search
            OR login_user.nom LIKE :search
            OR login_user.prenom LIKE :search
            OR login_user.email LIKE :search
            OR login_user.numero_client LIKE :search
        )
    ";
}

if (!empty($statut_filter)) {
    $sql .= " AND devis.statut = :statut";
}

$sql .= " ORDER BY devis.date_creation DESC";

$stmt = $db->prepare($sql);

$params = [':pro_id' => $pro_id];
if (!empty($search)) {
    $params[':search'] = $search_param;
}
if (!empty($statut_filter)) {
    $params[':statut'] = $statut_filter;
}

$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$devis = array_filter($results, fn($d) => strtolower($d['statut']) === 'devis');
$factures = array_filter($results, fn($d) => strtolower($d['statut']) === 'facture');

function renderResults($devis, $factures, $results, $search, $statut_filter)
{
    if (empty($search) && empty($statut_filter)) {
        // Pas de filtre, on affiche onglets et sections
        ?>
        <div class="mb-4 flex gap-4">
            <button onclick="showTab('devis', this)" class="tab-button text-blue-700 font-semibold">Devis</button>
            <button onclick="showTab('factures', this)" class="tab-button text-gray-600">Factures</button>
        </div>

        <div id="devis" class="tab-section">
            <div class="mb-4">
                <a href="devis-pro" class="px-4 py-2 bg-green-600 text-white rounded">+ Créer un devis</a>
            </div>
            <?php if (!empty($devis)): ?>
                <div class="bg-white shadow rounded p-4">
                    <h2 class="text-xl font-bold mb-4">Liste des devis</h2>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($devis as $item): ?>
                            <li class="py-2">
                                <strong>N°<?= htmlspecialchars($item['numero']) ?></strong> -
                                <?= htmlspecialchars($item['nom'] . ' ' . $item['prenom']) ?> -
                                <?= number_format($item['montant_total'], 2) ?> € -
                                <?= (new DateTime($item['date_creation']))->format('d/m/Y') ?> -
                                <a href="/dzt/<?= urlencode($item['chemin_pdf']) ?>" class="text-blue-600 underline"
                                    target="_blank">Voir PDF</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <p class="text-gray-600">Aucun devis trouvé.</p>
            <?php endif; ?>
        </div>

        <div id="factures" class="tab-section hidden">
            <div class="mb-4">
                <a href="facture_pro" class="px-4 py-2 bg-green-600 text-white rounded">+ Créer une facture</a>
            </div>
            <?php if (!empty($factures)): ?>
                <div class="bg-white shadow rounded p-4">
                    <h2 class="text-xl font-bold mb-4">Liste des factures</h2>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($factures as $facture): ?>
                            <li class="py-2">
                                <strong>N°<?= htmlspecialchars($facture['numero']) ?></strong> -
                                <?= htmlspecialchars($facture['nom'] . ' ' . $facture['prenom']) ?> -
                                <?= number_format($facture['montant_total'], 2) ?> € -
                                <?= (new DateTime($facture['date_creation']))->format('d/m/Y') ?> -
                                <a href="/fzt/<?= urlencode($facture['chemin_pdf']) ?>" class="text-blue-600 underline"
                                    target="_blank">Voir PDF</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <p class="text-gray-600">Aucune facture trouvée.</p>
            <?php endif; ?>
        </div>
        <?php
    } else {
        // Résultats de recherche affichés en liste simple
        if (empty($results)) {
            echo '<p class="text-gray-600">Aucun résultat trouvé.</p>';
        } else {
            ?>
            <div class="mb-8 bg-white shadow rounded p-4">
                <h2 class="text-xl font-bold mb-4">Résultats</h2>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($results as $item): ?>
                        <li class="py-2">
                            <strong>N°<?= htmlspecialchars($item['numero']) ?></strong> -
                            <?= htmlspecialchars($item['nom'] . ' ' . $item['prenom']) ?> -
                            <?= number_format($item['montant_total'], 2) ?> € -
                            <?= (new DateTime($item['date_creation']))->format('d/m/Y') ?> -
                            <a href="<?= ($item['statut'] === 'devis' ? '/devis_pdf/' : '/factures_pdf/') . urlencode($item['chemin_pdf']) ?>"
                                class="text-blue-600 underline" target="_blank">Voir PDF</a> -
                            <em><?= ucfirst(htmlspecialchars($item['statut'])) ?></em>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }
}

if ($isAjax) {
    // Juste envoyer la partie résultats
    renderResults($devis, $factures, $results, $search, $statut_filter);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Devis & Factures</title>
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
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
</head>

<body class="bg-gray-100 p-0 md:pl-64">
    <?php include('../includes/aside.php'); ?>
    <!-- Main Content -->
    <main class="p-6 max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Espace Devis & Factures</h1>

        <!-- Formulaire de recherche -->
        <form id="searchForm" class="mb-6 flex gap-2 flex-wrap">
            <input type="text" name="q" id="searchInput" value="<?= htmlspecialchars($search) ?>"
                placeholder="Rechercher par numéro client, nom, prénom, email ou numéro de devis ou numéro de facture..."
                class="w-full md:w-auto flex-grow px-4 py-2 rounded border border-gray-300 shadow-sm"
                autocomplete="off">
            <select name="statut" id="statutSelect" class="px-4 py-2 rounded border border-gray-300 shadow-sm">
                <option value="">Tous</option>
                <option value="devis" <?= $statut_filter === 'devis' ? 'selected' : '' ?>>Devis</option>
                <option value="facture" <?= $statut_filter === 'facture' ? 'selected' : '' ?>>Factures</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded shadow">Rechercher</button>
        </form>

        <div id="resultsContainer">
            <?php renderResults($devis, $factures, $results, $search, $statut_filter); ?>
        </div>
    </main>

    <!-- JS pour mobile menu toggle -->
    <script>
        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }
    </script>

    <script>
        function showTab(tab, btn) {
            document.querySelectorAll('.tab-section').forEach(el => el.classList.add('hidden'));
            document.getElementById(tab).classList.remove('hidden');
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('text-blue-700', 'font-semibold'));
            btn.classList.add('text-blue-700', 'font-semibold');
        }

        // Auto sélection onglet par défaut
        window.addEventListener('DOMContentLoaded', () => {
            const btn = document.querySelector('.tab-button.text-blue-700');
            if (btn) btn.click();
        });

        // AJAX recherche dynamique
        const form = document.getElementById('searchForm');
        const resultsContainer = document.getElementById('resultsContainer');

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(form);
            // Ajout du paramètre ajax
            formData.append('ajax', '1');

            // Construire URL avec GET params
            const params = new URLSearchParams(formData).toString();

            fetch(window.location.pathname + '?' + params)
                .then(response => response.text())
                .then(html => {
                    resultsContainer.innerHTML = html;

                    // Si recherche, on veut désactiver onglets (car pas affichés)
                    // sinon réactiver l'onglet devis par défaut
                    if (!formData.get('q') && !formData.get('statut')) {
                        const btn = document.querySelector('.tab-button.text-blue-700');
                        if (btn) btn.click();
                    }
                })
                .catch(err => {
                    resultsContainer.innerHTML = '<p class="text-red-600">Erreur de chargement.</p>';
                    console.error(err);
                });
        });

        // Tu peux aussi déclencher la recherche automatique au changement des champs, exemple :
        document.getElementById('searchInput').addEventListener('input', () => form.dispatchEvent(new Event('submit')));
        document.getElementById('statutSelect').addEventListener('change', () => form.dispatchEvent(new Event('submit')));
    </script>

</body>

</html>