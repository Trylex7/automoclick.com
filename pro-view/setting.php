<?php
session_start();
require_once '../db/dbconnect2.php';
require_once '../includes/webhook.php';
if (!isset($_SESSION['id_pro'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /connexion?success_url={$currentUrl}");
    exit;
}

$numero_pro = $_SESSION['id_pro'];
$message = '';
$data_pro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
$data_pro->execute([$numero_pro]);
$entreprise = $data_pro->fetch(PDO::FETCH_ASSOC);

$data_abonnement = $db->prepare('SELECT * FROM pro_abonnement WHERE numero_pro = ?');
$data_abonnement->execute([$numero_pro]);
$abonnement = $data_abonnement->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... traitement formulaire (inchangé) ...
    $message = "Paramètres mis à jour avec succès.";
}

$denomination_preg = preg_replace('/[^a-zA-Z0-9_-]/', '', $entreprise['denomination']);
$filename = "CGP-" . $denomination_preg . ".pdf";
$pdf_directory_web = "cgp/";
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Paramètres - Automoclick PRO</title>
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png" />
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png" />
    <link rel="manifest" href="img/site.webmanifest" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>

    <script >
        document.addEventListener("DOMContentLoaded", function () {
            const menuBtn = document.getElementById("menuBtn");
            menuBtn.addEventListener("click", toggleMenu);
            switchTab('profil', document.querySelector('.tab-btn'));
        });

        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }

        function switchTab(tabId, btn = null) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(button => {
                button.classList.remove('border-green-600', 'text-gray-700', 'font-semibold');
                button.classList.add('text-gray-500', 'hover:text-green-600');
            });
            document.getElementById(tabId).classList.remove('hidden');
            if (btn) {
                btn.classList.add('border-green-600', 'text-gray-700', 'font-semibold');
                btn.classList.remove('text-gray-500', 'hover:text-green-600');
            }
        }
    </script>
</head>

<body class="bg-gray-100 flex flex-col md:flex-row">
    <?php include('../includes/aside.php'); ?>
    <!-- Main Content -->
    <main class="flex-1 max-w-5xl mx-auto p-8 mt-20 md:mt-12 md:ml-64  min-h-[80vh] mb-12">

        <h1 class="text-3xl font-extrabold mb-6 text-gray-900">Paramètres du compte</h1>

        <?php if ($message): ?>
            <div class="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 text-green-700 px-5 py-4 shadow-sm">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Onglets -->
        <div class="flex flex-wrap border-b border-gray-200 mb-8">
            <?php
            $tabs = [
                'profil' => 'Profil',
                'utilisateur' => 'Utilisateurs',
                'appareils' => 'Sécurité',
                'motdepasse' => 'Mot de passe',
                'abonnement' => 'Abonnement',
                'documents' => 'CGP / CGU',
                'notifications' => 'Notifications',
                'banque' => 'RIB / BIC'
            ];
            foreach ($tabs as $id => $label): ?>
                <button type="button"
                    class="tab-btn px-4 py-2 -mb-px border-b-4 border-transparent text-gray-600 hover:border-green-500 hover:text-green-600 font-medium transition"
                    onclick="switchTab('<?= $id ?>', this)">
                    <?= $label ?>
                </button>
            <?php endforeach; ?>
        </div>

        <form method="POST" class="space-y-8">

            <!-- Onglet : Profil -->
            <section id="profil" class="tab-content space-y-6 hidden">
                <div class="bg-gray-50 p-4 sm:p-6 rounded-xl shadow-lg border border-gray-200">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-1">Numéro SIRET</label>
                            <input type="text" name="siret" value="<?= htmlspecialchars($entreprise['siret']) ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-400 focus:outline-none"
                                placeholder="Ex : 123 456 789 00000">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold mb-1">Dénomination</label>
                            <input type="text" name="denomination"
                                value="<?= htmlspecialchars($entreprise['denomination']) ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-400 focus:outline-none"
                                placeholder="Nom de votre entreprise">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-1 mt-4">Adresse</label>
                        <input type="text" name="adresse" value="<?= htmlspecialchars($entreprise['adresse']) ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-400 focus:outline-none"
                            placeholder="Ex : 12 rue Exemple, 75001 Paris">
                    </div>
                    <div class="text-right mt-4">
                        <button type="submit"
                            class="w-full md:w-auto px-6 py-2 bg-green-600 text-white font-semibold rounded-lg shadow hover:bg-green-700 transition">
                            Enregistrer
                        </button>
                    </div>
                </div>
            </section>

            <!-- Onglet : Utilisateurs -->
            <section id="utilisateur" class="tab-content hidden space-y-6">
                <div class="bg-gray-50 p-4 sm:p-6 rounded-xl shadow-lg border border-gray-200">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Gestion des utilisateurs</h2>
                    <p class="text-gray-600 mb-4 sm:mb-6 text-sm sm:text-base">Ajoutez, supprimez ou modifiez les
                        utilisateurs associés à votre compte pro.</p>

                    <a href="add_user"
                        class="inline-flex items-center gap-2 px-4 sm:px-6 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-md hover:bg-green-700 transition mb-4 sm:mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Ajouter un utilisateur
                    </a>

                    <div class="space-y-4">
                        <?php
                        $stmt = $db->prepare("SELECT id, nom, prenom, role FROM user_pro WHERE numero_pro = ?");
                        $stmt->execute([$numero_pro]);
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($users) {
                            foreach ($users as $user) {
                                // Couleurs des rôles
                                $roleColors = [
                                    'admin' => 'bg-red-100 text-red-800',
                                    'technicien' => 'bg-blue-100 text-blue-800',
                                    'gestionnaire' => 'bg-yellow-100 text-yellow-800',
                                    'utilisateur' => 'bg-green-100 text-green-800',
                                ];
                                $roleClass = $roleColors[$user['role']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <div
                                    class="flex flex-col md:flex-row items-start md:items-center justify-between bg-white p-4 rounded-xl shadow hover:shadow-xl transition gap-4">
                                    <!-- Infos utilisateur -->
                                    <div class="flex items-center gap-4 w-full md:w-auto">
                                        <div
                                            class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-bold text-lg">
                                            <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                            </h3>
                                            <span
                                                class="mt-1 inline-block px-2 py-1 text-xs font-medium rounded <?= $roleClass ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div
                                        class="flex flex-col md:flex-row items-start md:items-center gap-2 md:gap-3 w-full md:w-auto">
                                        <form method="post" action="update_user_role.php" class="w-full md:w-auto">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="role"
                                                class="w-full md:w-auto border border-gray-300 rounded-lg px-3 py-2 text-gray-700 hover:border-gray-400 transition"
                                                onchange="this.form.submit()">
                                                <?php
                                                $roles = ['admin', 'technicien', 'gestionnaire', 'utilisateur'];
                                                foreach ($roles as $role) {
                                                    $selected = $user['role'] === $role ? 'selected' : '';
                                                    echo "<option value='$role' $selected>" . ucfirst($role) . "</option>";
                                                } ?>
                                            </select>
                                        </form>

                                        <button type="button" data-modal-target="modal-<?= $user['id'] ?>"
                                            class="flex items-center gap-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition w-full md:w-auto">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Supprimer
                                        </button>

                                        <!-- Modal -->
                                        <div id="modal-<?= $user['id'] ?>"
                                            class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
                                            <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6">
                                                <h3 class="text-lg font-semibold mb-4 text-gray-800">Confirmer la suppression
                                                </h3>
                                                <p class="text-gray-600 mb-6">Voulez-vous vraiment supprimer cet utilisateur ?
                                                    Cette action est irréversible.</p>
                                                <div class="flex justify-end gap-4">
                                                    <button type="button"
                                                        class="modal-close px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition">Annuler</button>
                                                    <form method="post" action="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit"
                                                            class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition">
                                                            Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php }
                        } else {
                            echo '<p class="text-center text-gray-500 py-4">Aucun utilisateur trouvé</p>';
                        }
                        ?>
                    </div>
                </div>
            </section>


            <section id="motdepasse" class="tab-content hidden space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl shadow-lg border border-gray-200 space-y-4">


                    <div>
                        <label class="block text-gray-700 font-semibold mb-1">Mot de passe actuel</label>
                        <div class="relative">
                            <input type="password" name="password_current"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:outline-none pr-10"
                                data-password>
                            <button type="button" class="absolute inset-y-0 right-2 flex items-center text-gray-500"
                                data-toggle>
                                <span class="material-symbols-outlined">visibility_off</span>
                            </button>
                        </div>
                    </div>

                    <!-- Nouveau mot de passe -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-1">Nouveau mot de passe</label>
                        <div class="relative">
                            <input type="password" name="password_new"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:outline-none pr-10"
                                data-password>
                            <button type="button" class="absolute inset-y-0 right-2 flex items-center text-gray-500"
                                data-toggle>
                                <span class="material-symbols-outlined">visibility_off</span>
                            </button>
                        </div>
                    </div>

                    <!-- Confirmer mot de passe -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-1">Confirmer le mot de passe</label>
                        <div class="relative">
                            <input type="password" name="password_confirm"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:outline-none pr-10"
                                data-password>
                            <button type="button" class="absolute inset-y-0 right-2 flex items-center text-gray-500"
                                data-toggle>
                                <span class="material-symbols-outlined">visibility_off</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
            <section id="appareils" class="tab-content hidden space-y-4">
                <div class="bg-gray-50 p-4 sm:p-6 rounded-xl shadow-lg border border-gray-200">
                    <h2 class="text-xl font-semibold mb-4">Appareils connectés</h2>

                    <?php
                    $stmt = $db->prepare("SELECT * FROM sessions_pro WHERE numero_pro = ?");
                    $stmt->execute([$_SESSION['id_pro']]);
                    $sessions = $stmt->fetchAll();

                    if (count($sessions) === 0) {
                        echo "<p class='text-gray-600'>Aucun appareil connecté actuellement.</p>";
                    } else {
                        echo "<div class='space-y-3'>";
                        foreach ($sessions as $s) {

                            // Détection appareil et choix icône
                            $device = strtolower($s['device_info']);
                            $icon = '';
                            if (str_contains($device, 'iphone')) {
                                $icon = '<i class="fa-solid fa-mobile-screen-button text-2xl text-gray-600"></i>';
                            } elseif (str_contains($device, 'ipad')) {
                                $icon = '<i class="fa-solid fa-tablet-screen-button text-2xl text-gray-600"></i>';
                            } elseif (str_contains($device, 'android')) {
                                $icon = '<i class="fa-brands fa-android text-2xl text-green-600"></i>';
                            } elseif (str_contains($device, 'mac')) {
                                $icon = '<i class="fa-brands fa-apple text-2xl text-gray-600"></i>';
                            } elseif (str_contains($device, 'windows')) {
                                $icon = '<i class="fa-brands fa-windows text-2xl text-blue-600"></i>';
                            } else {
                                $icon = '<i class="fa-solid fa-laptop text-2xl text-gray-600"></i>';
                            }

                            $session_text = (session_id() === $s['session_id'])
                                ? '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm font-semibold">Session actuelle</span>'
                                : '<form method="POST" action="logout_devices_p" class="inline">
           <input type="hidden" name="session_id" value="' . $s['session_id'] . '">
           <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded font-semibold hover:bg-red-700 transition text-sm">
               Déconnecter
           </button>
       </form>';
                            ?>

                            <div
                                class="flex items-center justify-between bg-white p-3 rounded-xl shadow hover:shadow-md transition border border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div><?= $icon ?></div>
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($s['device_info']) ?></p>
                                        <p class="text-sm text-gray-500">IP : <?= htmlspecialchars($s['ip_address']) ?> |
                                            Dernière activité : <?= date('d/m/Y H:i', strtotime($s['last_activity'])) ?></p>
                                    </div>
                                </div>
                                <div><?= $session_text ?></div>
                            </div>

                            <?php
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
            </section>

            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />



            <section id="abonnement" class="tab-content hidden space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl shadow-lg border border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Votre abonnement</h2>
                    <p class="text-gray-600"><?= htmlspecialchars($abonnement['nom_abonnement'] ?? 'Aucun') ?></p>
                    <a href="abonnement" class="text-green-600 font-semibold hover:underline">Changer d'abonnement</a>
                </div>
            </section>


            <section id="documents" class="tab-content hidden space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl shadow-lg border border-gray-200">
                    <a href="<?= $pdf_directory_web . $filename ?>" class="text-green-600 font-semibold hover:underline"
                        target="_blank">
                        Télécharger vos CGP / CGU
                    </a>
                </div>
            </section>


            <section id="notifications" class="tab-content hidden space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl shadow-lg border border-gray-200">
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" name="notif_connexion"
                            class="h-5 w-5 text-green-600 rounded focus:ring-2 focus:ring-green-400">
                        <span class="text-gray-700 font-medium">Recevoir une notification à chaque connexion</span>
                    </label>
                </div>
            </section>


            <section id="banque" class="tab-content hidden space-y-6">
                <div class="bg-gray-50 p-6 rounded-xl shadow-lg border border-gray-200">
                    <label class="block text-gray-700 font-semibold mb-1">RIB / IBAN</label>
                    <input type="text" name="rib"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:outline-none">
                    <label class="block text-gray-700 font-semibold mb-1">BIC</label>
                    <input type="text" name="bic"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:outline-none">
                </div>
            </section>

        </form>
    </main>

    <style>
        .input-field {
            @apply block w-full rounded border border-gray-300 px-4 py-2 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-transparent transition;
        }
    </style>
    <script >
        // Menu mobile toggle
        function toggleMenu() {
            document.getElementById("mobileMenu").classList.toggle("hidden");
        }
    </script>

    <script >

        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('openSidebarBtn');
        const closeBtn = document.getElementById('closeSidebarBtn');
        const mainContent = document.getElementById('mainContent');

        document.addEventListener('DOMContentLoaded', function () {
            // Ouvrir modal
            document.querySelectorAll('[data-modal-target]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const modalId = this.dataset.modalTarget;
                    document.getElementById(modalId).classList.remove('hidden');
                });
            });

            // Fermer modal
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', function () {
                    this.closest('.modal').classList.add('hidden');
                });
            });
            document.querySelectorAll("[data-toggle]").forEach(btn => {
                btn.addEventListener("click", () => {
                    const input = btn.previousElementSibling;
                    const icon = btn.querySelector(".material-symbols-outlined");

                    if (input.type === "password") {
                        input.type = "text";
                        icon.textContent = "visibility"; // 👁️ visible
                    } else {
                        input.type = "password";
                        icon.textContent = "visibility_off"; // 👁️ barré
                    }
                });
            });
        });
    </script>

</body>

</html>