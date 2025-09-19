<?php
require_once '../header.php';
session_start();
require '../db/dbconnect2.php';
require_once '../includes/monitor_init.php'; 
require_once '../includes/webhook.php';
require_once '../api/traker.php';
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';
function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}
function dechiffrer($texte_chiffre, $cle)
{
    return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}
$token = $_GET['s'];
$numero_pro = dechiffrer($token, $cle_secrete);
$client_numero = $_SESSION['id_client'] ?? null;

if (!$numero_pro) {
    die("Numéro SIRET manquant.");
}

$stmt = $db->prepare("SELECT * FROM entreprises WHERE numero_pro = ? AND statut = 'actif' AND profil_valid = 1");
$stmt->execute([$numero_pro]);
$entreprise = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entreprise) {
    die("Entreprise introuvable.");
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <title>Détails de l'entreprise</title>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener('DOMContentLoaded', function () {
            fetch('/api/record_view.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_pro:"<?= htmlspecialchars($numero_pro) ?>"
                })
            })

                    .then(response => response.json())
            });
    </script>
</head>

<body class="bg-gray-50 text-gray-800 antialiased">
    <div id="loader">
        <div class="spinner"></div>
    </div>

    <?php include('../includes/dropdown.php'); ?>

    <!-- Section principale -->
    <main class="py-10">
        <div class="max-w-6xl mx-auto px-4 flex flex-col md:grid md:grid-cols-3 gap-10">

            <!-- Bloc infos + actions -->
            <div class="md:col-span-2 space-y-6 order-2 md:order-1">
                <!-- Logo centré en mobile -->
                <?php if (!empty($entreprise['logo'])): ?>
                    <div class="flex justify-center md:justify-start mb-6 md:mb-0">
                        <div class="w-200 h-200  overflow-hidden shadow-sm">
                            <img src="/uploads/<?= htmlspecialchars($entreprise['logo']) ?>"
                                alt="Logo <?= htmlspecialchars($entreprise['denomination']) ?>"
                                class="w-full h-full object-cover">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex justify-center md:justify-start mb-6 md:mb-0">
                        <div class="w-200 h-200  overflow-hidden shadow-sm">
                            <img src="https://placehold.co/200x200?text=Automoclick.com" alt="Automoclick.com"
                                class="w-full h-full object-cover">
                        </div>
                    </div>
                <?php endif; ?>
                <h1 class="text-3xl font-bold text-green-600 uppercase">
                    <?= htmlspecialchars($entreprise['denomination']) ?>
                </h1>

                <div class="bg-gray-100 p-6 rounded-lg shadow-md">
                    <ul class="space-y-4 text-lg">
                        <li class="flex items-start space-x-3">
                            <span class="material-symbols-outlined mt-1">build</span>
                            <span><?= htmlspecialchars(getNomSpe($entreprise['spe'] ?? '')) ?></span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <span class="material-symbols-outlined mt-1">apartment</span>
                            <span><?= htmlspecialchars($entreprise['adresse']) ?></span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <span class="material-symbols-outlined mt-1">drafts</span>
                            <span><?= htmlspecialchars($entreprise['email']) ?></span>
                        </li>
                        <?php if (!empty($entreprise['phone_number'])): ?>
                            <li class="flex items-start space-x-3">
                                <span class="material-symbols-outlined mt-1">call</span>
                                <span><?= htmlspecialchars($entreprise['phone_number']) ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if ($entreprise['spe'] !== "vendeur-auto" && $entreprise['spe'] !== "loueur"): ?>
                            <?php if (!empty($entreprise['taux_horaire'])): ?>
                                <li class="flex items-start space-x-3">
                                    <span class="material-symbols-outlined mt-1">hourglass</span>
                                    <span><?= htmlspecialchars($entreprise['taux_horaire']) ?>€/h</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($entreprise['description'])): ?>
                            <li class="flex items-start space-x-3">
                                <span class="material-symbols-outlined mt-1">description</span>
                                <span class="block"><?= nl2br(htmlspecialchars($entreprise['description'])) ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if ($entreprise['spe'] == "vendeur-auto" || $entreprise['spe'] == "loueur"): ?>
                    <div class="flex flex-col md:flex-row gap-4 mt-6">
                        <a href="devis?d=<?= chiffrer($numero_pro, $cle_secrete) ?>"
                            class="inline-block px-8 py-4 bg-green-600 text-white rounded-md font-medium hov transition">Devis
                            express</a>
                        <a href="view-v?v=<?= chiffrer($numero_pro, $cle_secrete) ?>"
                            class="inline-block px-8 py-4 bg-green-600 text-white rounded-md font-medium hov transition">Parcourir
                            les véhicules
                        </a>
                        <a href="chat?z=<?= chiffrer($numero_pro, $cle_secrete) ?>&a=<?= chiffrer($client_numero, $cle_secrete) ?>"
                            class="inline-block px-8 py-4 bg-black text-white rounded-md font-medium hov transition">Envoyer
                            un message</a>
                    </div>
                </div>

            <?php else: ?>
                <div class="flex flex-col md:flex-row gap-4 mt-6">
                    <a href="devis?d=<?= chiffrer($numero_pro, $cle_secrete) ?>"
                        class="inline-block px-8 py-4 bg-green-600 text-white rounded-md font-medium hov transition">Devis
                        express</a>
                    <a href="rdv?v=<?= chiffrer($numero_pro, $cle_secrete) ?>"
                        class="inline-block px-8 py-4 bg-green-600 text-white rounded-md font-medium hov transition">Prendre
                        rendez-vous</a>
                    <a href="chat?z=<?= chiffrer($numero_pro, $cle_secrete) ?>&a=<?= chiffrer($client_numero, $cle_secrete) ?>"
                        class="inline-block px-8 py-4 bg-black text-white rounded-md font-medium hov transition">Envoyer
                        un message</a>
                </div>
            </div>
        <?php endif; ?>
        <!-- Sidebar logo et carte -->
        <?php if (!empty($entreprise['latitude']) && !empty($entreprise['longitude'])): ?>
            <aside class="space-y-6 order-1 md:order-2">
                <div class="w-full h-64 rounded-md overflow-hidden shadow-sm">
                    <iframe width="100%" height="100%" style="border:0;" loading="lazy" allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.google.com/maps/embed/v1/place?key=AIzaSyCxSYMnBapanxmvZ77sGnWdupt6yDsJc7g&zoom=15&q=<?= $entreprise['latitude'] ?>,<?= $entreprise['longitude'] ?>">
                    </iframe>
                </div>
            </aside>
        <?php endif; ?>
        </div>

        <section class="max-w-6xl mx-auto mt-12 px-4">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Avis des clients</h2>
            <?php
            $stmt = $db->prepare("SELECT note, commentaire, date FROM avis WHERE professionnel_id = ?");
            $stmt->execute([$numero_pro]);
            $avis = $stmt->fetchAll();
            if (empty($avis)) {
                echo '<p class="text-gray-500">Aucun avis pour le moment.</p>';
            } else {
                foreach ($avis as $a):
                    ?>
                    <div class="bg-white rounded-lg shadow p-5 mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm text-gray-500"><?= date('d/m/Y', strtotime($a['date'])) ?></div>
                        </div>
                        <div class="flex items-center mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="w-5 h-5 <?= $i <= $a['note'] ? 'text-yellow-400' : 'text-gray-300' ?>"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.975h4.18c.969 0 1.371 1.24.588 1.81l-3.39 2.46 1.286 3.975c.3.921-.755 1.688-1.54 1.118L10 13.347l-3.39 2.46c-.785.57-1.84-.197-1.54-1.118l1.286-3.975-3.39-2.46c-.783-.57-.38-1.81.588-1.81h4.18l1.286-3.975z" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <p class="text-gray-700"><?= nl2br(htmlspecialchars($a['commentaire'])) ?></p>
                    </div>
                <?php endforeach;
            } ?>
        </section>

        <?php if (isset($_SESSION['id_client']) || isset($_SESSION['id_pro'])): ?>
            <section class="max-w-6xl mx-auto mt-12 px-4">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Laisser un avis</h2>
                <form method="post" action="ajouter_avis.php" class="bg-white p-6 rounded-lg shadow-md space-y-6">
                    <input type="hidden" name="id_pro" value="<?= $numero_pro ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                        <select name="note" required
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Choisir une note</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                        <textarea name="commentaire" required rows="4"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"
                            placeholder="Décrivez votre expérience..."></textarea>
                    </div>
                    <div class="text-right">
                        <button type="submit"
                            class="inline-block bg-green-600 text-white px-6 py-2 rounded-md font-medium hov transition">Envoyer
                            l’avis</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

    </main>

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
    <?php include('../includes/footer.php'); ?>
</body>

</html>