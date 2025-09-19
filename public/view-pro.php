<?php
require_once '../header.php';
require_once '../db/dbconnect2.php';
require_once '../includes/monitor_init.php';  
require_once '../includes/webhook.php';// Connexion PDO
require_once '../api/traker.php';
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';
function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}

$keywords = $_POST['keywords'] ?? '';
$specialisation = $_POST['specialisation'] ?? '';
$pays = $_POST['pays'] ?? '';

$conditions = [];
$params = [];

// ---- Requête SQL de base ----
$sql = "SELECT 
            e.numero_pro,
            e.denomination,
            e.adresse,
            e.commune,
            e.spe,
            e.pays,
            e.date_creation_account,
            e.profil_valid,
            e.statut,
            AVG(a.note) AS note_moyenne, 
            COUNT(DISTINCT a.id) AS avis_count,
            COUNT(DISTINCT p.id) AS prestations_count
        FROM entreprises e
        LEFT JOIN avis a ON e.numero_pro = a.professionnel_id
        LEFT JOIN prestations p ON e.numero_pro = p.numero_pro
        WHERE e.profil_valid = 1 
          AND e.statut = 'actif'";

// ---- Conditions dynamiques ----
if (!empty($keywords)) {
    $conditions[] = "(e.denomination LIKE :keywords 
                      OR e.adresse LIKE :keywords 
                      OR e.commune LIKE :keywords 
                      OR e.spe LIKE :keywords
                      OR p.nom LIKE :keywords)";
    $params[':keywords'] = '%' . $keywords . '%';
}

if (!empty($specialisation)) {
    $conditions[] = "e.spe = :spe";
    $params[':spe'] = $specialisation;
}

if (!empty($pays)) {
    $conditions[] = "e.pays = :pays";
    $params[':pays'] = $pays;
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// ---- GROUP BY & tri ----
$sql .= " GROUP BY e.numero_pro, e.denomination, e.adresse, e.commune, e.spe, e.pays, e.date_creation_account, e.profil_valid, e.statut
          ORDER BY e.date_creation_account DESC";

// ---- Exécution ----
$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getNomSpe(string $spe): string {
    $specialites = [
        'mecanique' => 'Mécanique',
        'carrosserie' => 'Carrossier(e)',
        'depanneur' => 'Dépanneur',
        'controle' => 'Contrôleur(se) technique',
        'electro' => 'Électromécanicien(ne)',
        'garage' => 'Garage',
        'nettoyage' => 'Nettoyage',
        'peintre' => 'Peintre',
        'soudeur' => 'Soudeur(se)',
        'prepa' => 'Préparateur automobile',
        'loueur' => 'Location de véhicule',
        'vendeur-piece' => 'Vendeur de pièce',
        'vendeur-auto' => 'Vendeur de véhicule',
        'tunning' => 'Tuning'
    ];

    return $specialites[$spe] ?? 'Non défini';
}
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
    <title>Résultats | Automoclick</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<div id="loader">
    <div class="spinner"></div>
</div>
 <?php include ('../includes/dropdown.php'); ?>
<body class="bg-gray-100 min-h-screen font-sans">
    <div class="max-w-6xl mx-auto p-6">
        <h1 class="text-4xl font-extrabold text-center text-green-700 mb-10">
            Résultats de la recherche
        </h1>

        <?php if (!empty($results)): ?>
            <div class="space-y-6">
                <?php foreach ($results as $row): ?>
                    <?php $token = chiffrer($row['numero_pro'], $cle_secrete); ?>
                    <a href="pro-details?s=<?= $token ?>"
                        class="block bg-white rounded-lg shadow-md hover:shadow-xl transition p-6 border border-gray-200 flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-semibold text-green-700"><?= htmlspecialchars($row['denomination']) ?></h2>
                            <p class="text-sm text-gray-500">Spécialisation : <?= htmlspecialchars(getNomSpe($row['spe'])) ?></p>
                            <p class="mt-1 text-gray-700">Adresse : <?= htmlspecialchars($row['adresse']) ?> -
                                <?= htmlspecialchars($row['commune']) ?>
                            </p>
                        </div>
                         <div class="text-right">
                            <?php
                            $moyenne = round(floatval($row['note_moyenne']), 1);
                            $avisCount = intval($row['avis_count']);
                            ?>
                            <div class="flex items-center justify-end space-x-1 text-yellow-400 text-lg">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= floor($moyenne)) {
                                        // Étoile pleine
                                        echo '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 fill-current" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.967h4.175c.969 0 1.371 1.24.588 1.81l-3.38 2.455 1.287 3.967c.3.921-.755 1.688-1.54 1.118L10 13.347l-3.38 2.455c-.784.57-1.838-.197-1.539-1.118l1.287-3.967-3.38-2.455c-.783-.57-.38-1.81.588-1.81h4.175L9.05 2.927z" />
                                </svg>';
                                    } elseif ($i - $moyenne < 1) {
                                        // Étoile demi
                                        echo '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <defs>
                                    <linearGradient id="halfGrad">
                                        <stop offset="50%" stop-color="currentColor"/>
                                        <stop offset="50%" stop-color="#D1D5DB"/>
                                    </linearGradient>
                                </defs>
                                <path fill="url(#halfGrad)" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.967h4.175c.969 0 1.371 1.24.588 1.81l-3.38 2.455 1.287 3.967c.3.921-.755 1.688-1.54 1.118L10 13.347l-3.38 2.455c-.784.57-1.838-.197-1.539-1.118l1.287-3.967-3.38-2.455c-.783-.57-.38-1.81.588-1.81h4.175L9.05 2.927z"/>
                                </svg>';
                                    } else {
                                        // Étoile vide
                                        echo '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 fill-current text-gray-300" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.967h4.175c.969 0 1.371 1.24.588 1.81l-3.38 2.455 1.287 3.967c.3.921-.755 1.688-1.54 1.118L10 13.347l-3.38 2.455c-.784.57-1.838-.197-1.539-1.118l1.287-3.967-3.38-2.455c-.783-.57-.38-1.81.588-1.81h4.175L9.05 2.927z" />
                                </svg>';
                                    }
                                }
                                ?>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">(<?= $avisCount ?> avis)</p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-600 text-lg mt-16">Aucun professionnel trouvé selon vos critères.</p>
        <?php endif; ?>
    </div>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        // Toggle mobile menu
        // const btn = document.getElementById('mobile-menu-button');
        // const menu = document.getElementById('mobile-menu');

        // btn.addEventListener('click', () => {
        //   menu.classList.toggle('hidden');
        // });

        window.addEventListener("load", function () {
            $('#loader').fadeOut(2000); // 2000ms = 2 secondes
        });
    </script>

</body>

</html>