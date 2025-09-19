<?php
require_once '../header.php';
session_start();
require '../db/dbconnect2.php';
require_once '../includes/webhook.php';
require_once '../api/traker.php';
trackPageView(basename(__FILE__)); 
$numero_pro = $_SESSION['id_pro'];
$stmt = $db->prepare("SELECT nom_abonnement FROM pro_abonnement WHERE numero_pro = ? AND statut = 'active'");
$stmt->execute([$numero_pro]);
$abonnements = $stmt->fetchAll(PDO::FETCH_COLUMN);
$hasTop = in_array('top', $abonnements);
$hasRestyle = in_array('restyle', $abonnements);
$hasAutoLine = in_array('autoline', $abonnements);
$view = $db->prepare("SELECT * FROM pro_abonnement WHERE numero_pro = ?");
$view->execute([$numero_pro]);
$pro = $view->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION['id_pro'])) {
    header('Location: connexion');
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
    <title>Abonnement</title>
    <style>
        #billing-switch:checked+div>div {
            transform: translateX(1.25rem);
        }
    </style>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }

        document.addEventListener("DOMContentLoaded", function () {
            const billingSwitch = document.getElementById("billing-switch");
            const prices = document.querySelectorAll(".price");
            const discounts = document.querySelectorAll(".discount");

            // Remises associées à chaque plan (dans l'ordre des éléments)
            const discountsByPlan = ['10%', '15%', '20%'];

            // 📌 Mise à jour des prix et des remises en fonction du toggle
            function updatePrices() {
                const annually = billingSwitch.checked;

                prices.forEach((priceElement, index) => {
                    const monthly = parseFloat(priceElement.dataset.monthly);
                    const annual = parseFloat(priceElement.dataset.yearly);

                    if (annually) {
                        priceElement.innerHTML = annual.toFixed(2) + '<sup class="text-lg">€</sup>/an';
                        if (discounts[index]) {
                            discounts[index].textContent = `Économisez ${discountsByPlan[index]}`;
                            discounts[index].style.display = "block";
                        }
                    } else {
                        priceElement.innerHTML = monthly.toFixed(2) + '<sup class="text-lg">€</sup>/mois';
                        if (discounts[index]) discounts[index].style.display = "none";
                    }
                });
            }

            billingSwitch.addEventListener("change", updatePrices);
            updatePrices(); // Initialisation au chargement

            // 🚀 Ajout du token + redirection dynamique au clic
            document.querySelectorAll('.sub-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();

                    const isAnnual = billingSwitch.checked;
                    const plan = this.dataset.plan;

                    let nom, prix;

                    if (plan === 'top') {
                        nom = 'top';
                        prix = isAnnual ? 359.10 : 39.99;
                    } else if (plan === 'restyle') {
                        nom = 'restyle';
                        prix = isAnnual ? 519.20 : 64.00;
                    } else if (plan === 'autoline') {
                        nom = 'autoline';
                        prix = isAnnual ? 719.20 : 89.00;
                    }

                    const type = isAnnual ? 'annuel' : 'mensuel';

                    const jsonData = {
                        nom: nom,
                        prix: prix,
                        type: type,
                        timestamp: Date.now(),
                        'session-id': crypto.getRandomValues(new Uint8Array(8)).reduce((a, b) => a + b.toString(16).padStart(2, '0'), '')
                    };

                    const token = btoa(unescape(encodeURIComponent(JSON.stringify(jsonData))));
                    const url = `pay-abonnement.php?n=${encodeURIComponent(token)}`;
                    window.location.href = url;
                });
            });
        });
    </script>
</head>

<body class="bg-gray-100 text-gray-800">
    <div id="loader">
        <div class="spinner"></div>
    </div>

    <?php include ('../includes/dropdown.php'); ?>

    <section class="abonnements py-16 bg-gray-50">
        <h4 class="title sub text-3xl font-bold text-center text-gray-800 mb-6">Nos abonnements</h4>
        <div class="card-container max-w-7xl mx-auto px-4 grid grid-cols-1 md:grid-cols-3 gap-8">
            <p class="card-title col-span-full text-center text-lg text-gray-600 mb-6">
                Rejoignez-nous pour un partenariat où votre réussite est notre priorité. <br>
                Inscrivez-vous maintenant et révolutionnez votre activité !
            </p>

            <div class="billing-toggle flex justify-center items-center gap-4 col-span-full mb-10">
                <span class="switch-label text-sm font-medium text-gray-700">Facturation mensuelle</span>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="billing-switch" class="sr-only peer" />
                    <div
                        class="w-11 h-6 bg-gray-300 rounded-full peer-checked:bg-green-500 transition-colors duration-300 relative">
                        <div
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-300 peer-checked:translate-x-5">
                        </div>
                    </div>
                </label>

                <span class="switch-label text-sm font-medium text-gray-700">Facturation annuelle</span>
            </div>




            <!-- TOP DÉPART -->
            <?php if (!$hasTop): ?>
                <div class="card-sub bg-white rounded-2xl shadow-md p-6 flex flex-col items-center">
                    <div class="subs-title text-xl font-semibold text-gray-800 text-center">Top départ
                        <hr class="my-2 border-gray-200 w-1/2 mx-auto">
                    </div>
                    <div class="price text-3xl font-bold text-green-600 mb-2" data-monthly="39.99" data-yearly="359.10">
                        39.99<sup class="text-lg">€</sup>/mois</div>
                    <div class="discount text-sm" style="display: none; color: green; font-weight: bold;">Économisez 20%
                    </div>
                    <div class="sub-description text-center text-gray-600 mb-4">Gérez rendez-vous, devis et paiements en
                        toute sécurité.</div>
                    <div class="container-option w-full space-y-2">
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Gestion de
                            rendez-vous
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Devis
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Paiement
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Facture
                        </div>
                        <div class="option-sub flex items-center text-sm text-red-500">
                            <span class="material-symbols-outlined icon red mr-2">close</span>Tableau de bord interactif
                        </div>
                        <div class="option-sub flex items-center text-sm text-red-500">
                            <span class="material-symbols-outlined icon red mr-2">close</span>1 Mise en avant
                        </div>
                        <div class="option-sub flex items-center text-sm text-red-500">
                            <span class="material-symbols-outlined icon red mr-2">close</span>Utilisateurs additionnels
                        </div>
                        <div class="option-sub flex items-center text-sm text-red-500">
                            <span class="material-symbols-outlined icon red mr-2">close</span>Commission de 10%
                        </div>
                    </div>
                    <a class="sub-btn mt-6 bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-700 transition"
                        data-plan="top" href="#">S'abonner</a>
                </div>
            <?php endif; ?>
            <!-- RESTYLÉ -->
            <?php if (!$hasRestyle): ?>
                <div class="card-sub bg-white rounded-2xl shadow-md p-6 flex flex-col items-center">
                    <div class="subs-title text-xl font-semibold text-gray-800 text-center">Restylé
                        <hr class="my-2 border-gray-200 w-1/2 mx-auto">
                    </div>
                    <div class="price text-3xl font-bold text-green-600 mb-2" data-monthly="64.00" data-yearly="519.20">
                        64.00<sup class="text-lg">€</sup>/mois</div>
                    <div class="discount text-sm" style="display: none; color: green; font-weight: bold;">Économisez 20%
                    </div>
                    <div class="sub-description text-center text-gray-600 mb-4">À un prix avantageux, profitez d'une
                        visibilité accrue et de services premium, tout en maximisant vos revenus !</div>
                    <div class="container-option w-full space-y-2">
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Gestion de
                            rendez-vous
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Devis
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Paiement
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Facture
                        </div>
                        <div class="option-sub flex items-center text-sm text-green-500">
                            <span class="material-symbols-outlined icon mr-2">check</span>Tableau de bord interactif
                        </div>
                        <div class="option-sub flex items-center text-sm text-red-500">
                            <span class="material-symbols-outlined icon red mr-2">close</span>1 Mise en avant
                        </div>
                        <div class="option-sub flex items-center text-sm text-red-500">
                            <span class="material-symbols-outlined icon red mr-2">close</span>Utilisateurs additionnels
                        </div>
                        <div class="option-sub flex items-center text-sm text-red-500">
                            <span class="material-symbols-outlined icon red mr-2">close</span>Commission de 5%
                        </div>
                    </div>
                    <a class="sub-btn mt-6 bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-700 transition"
                        data-plan="restyle" href="#">S'abonner</a>
                </div>
            <?php endif; ?>

            <!-- AUTOLINE -->
            <?php if (!$hasAutoLine): ?>
                <div
                    class="card-sub bg-white rounded-2xl shadow-md p-6 flex flex-col items-center border-2 border-green-500">
                    <div class="subs-title text-xl font-semibold text-gray-800 text-center">AutoLine
                        <hr class="my-2 border-gray-200 w-1/2 mx-auto">
                    </div>
                    <div class="price text-3xl font-bold text-green-600 mb-2" data-monthly="89.00" data-yearly="719.20">
                        89.00<sup class="text-lg">€</sup>/mois</div>
                    <div class="discount text-sm" style="display: none; color: green; font-weight: bold;">Économisez 20%
                    </div>
                    <div class="sub-description text-center text-gray-600 mb-4">Offres inédites, accès illimité, et des
                        outils de gestion avancés vous attendent pour piloter votre activité vers l'excellence.</div>
                    <div class="container-option w-full space-y-2">
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Gestion de
                            rendez-vous
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Devis
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Paiement
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Facture
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Tableau de bord
                            interactif
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>1 Mise en avant
                        </div>
                        <div class="option-sub flex items-center text-sm text-gray-700">
                            <span class="material-symbols-outlined icon text-green-500 mr-2">check</span>Utilisateurs
                            additionnels
                        </div>
                        <div class="option-sub flex items-center text-sm text-green-500">
                            <span class="material-symbols-outlined icon mr-2">check</span>0% de commission
                        </div>
                    </div>
                    <a class="sub-btn mt-6 bg-green-700 text-white px-6 py-2 rounded-full hover:bg-green-800 transition"
                        data-plan="autoline" href="#">S'abonner</a>
                </div>
            <?php endif; ?>
        </div>
    </section>


</body>

</html>