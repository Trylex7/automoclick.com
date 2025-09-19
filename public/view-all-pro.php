<?php
require_once '../header.php';
session_start();
require_once '../db/dbconnect2.php'; // Connexion PDO
require_once '../includes/monitor_init.php'; 
require_once '../includes/webhook.php';
require_once '../api/traker.php';
function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}

$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

$sql = "
    SELECT 
        e.*, 
        AVG(a.note) AS note_moyenne, 
        COUNT(a.id) AS avis_count
    FROM entreprises e
    LEFT JOIN avis a ON e.numero_pro = a.professionnel_id
    WHERE e.profil_valid = 1 AND e.statut = 'actif'
    GROUP BY e.numero_pro
";

$stmt = $db->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as &$row) {
    $row['token'] = chiffrer($row['numero_pro'], $cle_secrete);

    // Assure des valeurs numériques correctes même s’il n’y a aucun avis
    $row['note_moyenne'] = round(floatval($row['note_moyenne']), 1);
    $row['avis_count'] = intval($row['avis_count']);
}
unset($row);
function getNomSpe(string $spe): string
{
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
    <title>Pro | Automoclick</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<div id="loader">
    <div class="spinner"></div>
</div>

<?php include('../includes/dropdown.php'); ?>


<body class="bg-gray-100 min-h-screen font-sans">
    <div class="max-w-6xl mx-auto p-6">
        <h1 class="text-4xl font-extrabold text-center text-green-700 mb-10">
            Professionnels autour de vous
        </h1>

        <?php if (!empty($results)): ?>
            <div class="space-y-6">
                <?php foreach ($results as $row): ?>
                    <?php $token = chiffrer($row['numero_pro'], $cle_secrete); ?>
                    <a href="pro-details?s=<?= $token ?>"
                        class="block bg-white rounded-lg shadow-md hover:shadow-xl transition p-6 border border-gray-200 flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-semibold text-green-700"><?= htmlspecialchars($row['denomination']) ?></h2>
                            <p class="mt-1 text-gray-700 flex items-center space-x-2">
                                <span class="material-symbols-outlined  ">build</span>
                                <span><?= htmlspecialchars(getNomSpe($row['spe'])) ?></span>
                            </p>
                            <p class="mt-1 text-gray-700 flex items-center space-x-2">
                                <span class="material-symbols-outlined  ">apartment</span>
                                <span><?= htmlspecialchars($row['adresse']) ?></span>
                            </p>
                            <?php if (!empty($row['taux_horaire'])): ?>
                                <p class="text-gray-700 flex items-center space-x-2">
                                    <span class="material-symbols-outlined ">hourglass</span>
                                    <span class="font-semibold"><?= htmlspecialchars($row['taux_horaire']) ?>€/h</span>
                                </p>
                            <?php endif; ?>
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
            <p class="text-center text-gray-600 text-lg mt-16">Aucun professionnel trouvé autour de votre position.</p>
        <?php endif; ?>


        <section class="mt-10 max-w-5xl mx-auto">
            <h2 class="text-3xl font-bold text-green-700 mb-6">Professionnels autour de vous (5 km)</h2>
            <section class="bg-white rounded-lg shadow-md p-6 mb-10">
                <h3 class="text-2xl font-semibold text-green-700 mb-4">Filtrer les professionnels</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Spécialisation</label>
                        <select id="filter-spe" class="w-full border-gray-300 rounded-md">
                            <option value="">Toutes</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Taux horaire max (€)</label>
                        <input type="number" id="filter-price" class="w-full border-gray-300 rounded-md"
                            placeholder="Ex : 50" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Avis minimum</label>
                        <select id="filter-rating" class="w-full border-gray-300 rounded-md">
                            <option value="">Tous</option>
                            <option value="1">1 étoile</option>
                            <option value="2">2 étoiles</option>
                            <option value="3">3 étoiles</option>
                            <option value="4">4 étoiles</option>
                            <option value="5">5 étoiles</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Distance max (km)</label>
                        <input type="number" id="filter-distance" class="w-full border-gray-300 rounded-md"
                            placeholder="Ex : 5" value="5" />
                    </div>
                </div>
            </section>
            <div id="pros-autour">
                <p class="text-center text-gray-600">Chargement des professionnels proches...</p>
            </div>
        </section>
    </div>
    <!-- <script nonce="<?= htmlspecialchars($nonce) ?>">
        window.addEventListener("load", function () {
            $('#loader').fadeOut(2000); // 2000ms = 2 secondes
        });
    </script> -->
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        const entreprises = <?= json_encode($results, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
        let userLat = null;
        let userLon = null;

        // Remplit dynamiquement les spécialisations uniques
        function remplirSpecialisations() {
            const select = document.getElementById('filter-spe');
            const specialisations = [...new Set(entreprises.map(pro => pro.spe).filter(Boolean))];
            specialisations.forEach(spe => {
                const opt = document.createElement('option');
                opt.value = spe;
                opt.textContent = specialites[spe];
                select.appendChild(opt);
            });
        }
    </script>  
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        const specialites = <?= json_encode([
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
        ]) ?>;
        function haversineDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // rayon Terre en km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function afficherProsAutour(latUser, lonUser, rayonKm = 5) {
            userLat = latUser;
            userLon = lonUser;

            remplirSpecialisations();

            // Appliquer les filtres
            const spe = document.getElementById('filter-spe').value;
            const maxPrice = parseFloat(document.getElementById('filter-price').value) || Infinity;
            const minRating = parseInt(document.getElementById('filter-rating').value) || 0;
            const maxDist = parseFloat(document.getElementById('filter-distance').value) || rayonKm;

            const prosFiltres = entreprises.filter(pro => {
                if (!pro.latitude || !pro.longitude) return false;

                const dist = haversineDistance(latUser, lonUser, pro.latitude, pro.longitude);
                pro.distance = dist;

                const rating = parseFloat(pro.avis || 4); // à adapter selon ta base

                return (
                    dist <= maxDist &&
                    (spe === '' || pro.spe === spe) &&
                    (!isNaN(rating) && rating >= minRating) &&
                    (!isNaN(pro.taux_horaire) && pro.taux_horaire <= maxPrice)
                );
            });

            prosFiltres.sort((a, b) => a.distance - b.distance);

            const container = document.getElementById('pros-autour');
            if (!prosFiltres.length) {
                container.innerHTML =
                    `<p class="text-center text-gray-600">Aucun professionnel trouvé selon vos critères.</p>`;
                return;
            }

            container.innerHTML = prosFiltres.map(pro => `
      <a href="pro-details?s=${encodeURIComponent(pro.token)}"
   class="block bg-white rounded-lg shadow-md hover:shadow-lg transition p-6 border border-gray-200 mb-4 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-green-700">${pro.denomination}</h2>
                <p class="text-sm text-gray-500">Spécialisation : ${specialites[pro.spe]}</p>
                <p class="mt-1 text-gray-700">Adresse : ${pro.adresse}</p>
                <p class="text-gray-700">Taux horaire : <strong>${pro.taux_horaire}€/h</strong></p>
                <p class="text-sm text-gray-500 mt-1">Distance : ${pro.distance.toFixed(2)} km</p>
            </div>
        </a>
    `).join('');
        }
        // Demander géolocalisation et afficher
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                afficherProsAutour(position.coords.latitude, position.coords.longitude, 5);
            }, error => {
                console.warn('Géolocalisation refusée ou erreur:', error.message);
                document.getElementById('pros-autour').innerHTML =
                    '<p class="text-center text-gray-600">Impossible de récupérer votre position.</p>';
            });
        } else {
            document.getElementById('pros-autour').innerHTML =
                '<p class="text-center text-gray-600">La géolocalisation n\'est pas supportée par votre navigateur.</p>';
        }
        ['filter-spe', 'filter-price', 'filter-rating', 'filter-distance'].forEach(id => {
            document.getElementById(id).addEventListener('input', () => {
                if (userLat && userLon) {
                    afficherProsAutour(userLat, userLon);
                }
            });
        });
    </script>
    <?php include('../includes/footer.php'); ?>
</body>

</html>