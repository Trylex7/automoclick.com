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

$token = $_GET['v'] ?? null;
$numero_pro = $token ? dechiffrer($token, $cle_secrete) : ($_GET['numero_pro'] ?? null);
if (!$numero_pro)
    die("Prestataire non défini.");

// Gestion des filtres dynamiques
$where = "WHERE numero_pro = :numero_pro";
$params = [':numero_pro' => $numero_pro];

if (!empty($_GET['prix_min'])) {
    $where .= " AND prix >= :prix_min";
    $params[':prix_min'] = (float) $_GET['prix_min'];
}
if (!empty($_GET['prix_max'])) {
    $where .= " AND prix <= :prix_max";
    $params[':prix_max'] = (float) $_GET['prix_max'];
}
if (!empty($_GET['carburant'])) {
    $where .= " AND carburant = :carburant";
    $params[':carburant'] = $_GET['carburant'];
}
if (!empty($_GET['km_min'])) {
    $where .= " AND kilometrage >= :km_min";
    $params[':km_min'] = (int) $_GET['km_min'];
}
if (!empty($_GET['km_max'])) {
    $where .= " AND kilometrage <= :km_max";
    $params[':km_max'] = (int) $_GET['km_max'];
}
if (!empty($_GET['annee'])) {
    $where .= " AND model_annee = :annee";
    $params[':annee'] = $_GET['annee'];
}

$order = "ORDER BY id DESC";
if (!empty($_GET['tri'])) {
    if ($_GET['tri'] === 'prix_asc')
        $order = "ORDER BY prix ASC";
    elseif ($_GET['tri'] === 'prix_desc')
        $order = "ORDER BY prix DESC";
}

$sql = "SELECT * FROM prestations_vehicule $where $order";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$data_v = $stmt->fetchAll(PDO::FETCH_ASSOC);
$data_pro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
$data_pro->execute([$numero_pro]);
$pro_info = $data_pro->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <title>Véhicules</title>
</head>

<body class="bg-gray-100">
      <?php include ('../includes/dropdown.php'); ?>
    <div class="container mx-auto py-10 px-4">
        <div class="flex gap-6">
            <!-- SIDEBAR FILTRES -->
            <form method="GET" class="w-1/4 bg-white p-4 rounded shadow space-y-4">
                <input type="hidden" name="v" value="<?= htmlspecialchars($_GET['v'] ?? '') ?>">

                <div>
                    <label class="block font-medium mb-1">Prix min</label>
                    <input type="number" name="prix_min" value="<?= htmlspecialchars($_GET['prix_min'] ?? '') ?>"
                        class="w-full p-2 border rounded">
                </div>

                <div>
                    <label class="block font-medium mb-1">Prix max</label>
                    <input type="number" name="prix_max" value="<?= htmlspecialchars($_GET['prix_max'] ?? '') ?>"
                        class="w-full p-2 border rounded">
                </div>

                <div>
                    <label class="block font-medium mb-1">Kilométrage min</label>
                    <input type="number" name="km_min" value="<?= htmlspecialchars($_GET['km_min'] ?? '') ?>"
                        class="w-full p-2 border rounded">
                </div>

                <div>
                    <label class="block font-medium mb-1">Kilométrage max</label>
                    <input type="number" name="km_max" value="<?= htmlspecialchars($_GET['km_max'] ?? '') ?>"
                        class="w-full p-2 border rounded">
                </div>

                <div>
                    <label class="block font-medium mb-1">Année</label>
                    <input type="number" name="annee" value="<?= htmlspecialchars($_GET['annee'] ?? '') ?>"
                        class="w-full p-2 border rounded">
                </div>

                <div>
                    <label class="block font-medium mb-1">Carburant</label>
                    <select name="carburant" class="w-full p-2 border rounded">
                        <option value="">Tous</option>
                        <option value="Essence" <?= (($_GET['carburant'] ?? '') === 'Essence') ? 'selected' : '' ?>>Essence
                        </option>
                        <option value="Diesel" <?= (($_GET['carburant'] ?? '') === 'Diesel') ? 'selected' : '' ?>>Diesel
                        </option>
                        <option value="Electrique" <?= (($_GET['carburant'] ?? '') === 'Electrique') ? 'selected' : '' ?>>
                            Électrique</option>
                        <option value="Hybride" <?= (($_GET['carburant'] ?? '') === 'Hybride') ? 'selected' : '' ?>>Hybride
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block font-medium mb-1">Trier par</label>
                    <select name="tri" class="w-full p-2 border rounded">
                        <option value="">Pertinence</option>
                        <option value="prix_asc" <?= (($_GET['tri'] ?? '') === 'prix_asc') ? 'selected' : '' ?>>Prix
                            croissant</option>
                        <option value="prix_desc" <?= (($_GET['tri'] ?? '') === 'prix_desc') ? 'selected' : '' ?>>Prix
                            décroissant</option>
                    </select>
                </div>

                <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded">Filtrer</button>
            </form>

            <!-- LISTE DES VEHICULES -->
            <?php if ($pro_info['spe'] == "vendeur-auto"): ?>
                <div class="flex-1 space-y-6">
                    <?php foreach ($data_v as $v):
                        $stmt = $db->prepare("SELECT chemin FROM photos_prestations_vehicule WHERE id_prestation = ? LIMIT 1");
                        $stmt->execute([$v['id']]);
                        $image = $stmt->fetchColumn();
                        $imageUrl = $image ? htmlspecialchars($image) : 'https://placehold.co/600x600?text=Automoclick.com';
                        ?>

                        <div class="flex bg-white rounded-xl shadow hover:shadow-lg transition overflow-hidden">
                            <div class="w-60 h-60 flex-shrink-0 bg-gray-100 flex items-center justify-center">
                                <img src="<?= $imageUrl ?>" class="max-w-full max-h-full object-contain">
                            </div>
                            <div class="flex flex-col justify-between p-4 flex-grow">
                                <div>
                                    <h2 class="text-xl font-bold mb-2"><?= htmlspecialchars($v['marque']) ?>
                                        <?= htmlspecialchars($v['model']) ?>
                                    </h2>
                                    <ul class="text-sm text-gray-700 space-y-1">
                                        <li><strong>Année :</strong> <?= htmlspecialchars($v['model_annee'] ?? '-') ?></li>
                                        <li><strong>Kilométrage :</strong> <?= htmlspecialchars($v['kilometrage']) ?> km</li>
                                        <li><strong>Carburant :</strong> <?= htmlspecialchars($v['carburant']) ?></li>
                                        <li><strong>Boîte :</strong> <?= htmlspecialchars($v['boite']) ?></li>
                                    </ul>
                                    <?php if (!empty($v['prix'])): ?>
                                        <p class="text-green-600 font-bold text-lg mt-2">
                                            <?= number_format($v['prix'], 2, ',', ' ') ?> €
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right mt-4">
                                    <a href="details_v?j=<?= $v['token'] ?>"
                                        class="text-sm bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">Voir
                                        détails</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($pro_info['spe'] == 'loueur'): ?>
                    <div class="flex-1 space-y-6">
                        <?php foreach ($data_v as $v):
                            $stmt = $db->prepare("SELECT chemin FROM photos_prestations_vehicule WHERE id_prestation = ? LIMIT 1");
                            $stmt->execute([$v['id']]);
                            $image = $stmt->fetchColumn();
                            $imageUrl = $image ? htmlspecialchars($image) : 'https://placehold.co/300x300?text=Automoclick.com';
                            ?>

                            <div class="flex bg-white rounded-xl shadow hover:shadow-lg transition overflow-hidden">
                                <div class="w-60 h-60 flex-shrink-0 bg-gray-100 flex items-center justify-center">
                                    <img src="<?= $imageUrl ?>" class="max-w-full max-h-full object-contain">
                                </div>
                                <div class="flex flex-col justify-between p-4 flex-grow">
                                    <div>
                                        <h2 class="text-xl font-bold mb-2"><?= htmlspecialchars($v['marque']) ?>
                                            <?= htmlspecialchars($v['model']) ?>
                                        </h2>
                                        <ul class="text-sm text-gray-700 space-y-1">
                                            <li><strong>Année :</strong> <?= htmlspecialchars($v['model_annee'] ?? '-') ?></li>
                                            <li><strong>Carburant :</strong> <?= htmlspecialchars($v['carburant']) ?></li>
                                            <li><strong>Boîte :</strong> <?= htmlspecialchars($v['boite']) ?></li>
                                        </ul>
                                        <?php if (!empty($v['prix'])): ?>
                                            <p class="text-green-600 font-bold text-lg mt-2">
                                                <?= number_format($v['prix_j'], 2, ',', ' ') ?> €
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right mt-4">
                                        <a href="details_v?j=<?= $v['token'] ?>"
                                            class="text-sm bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">Voir
                                            détails</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include('../includes/footer.php'); ?>
</body>

</html>
