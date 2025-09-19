<?php
require_once '../header.php';
session_start();
require '../db/dbconnect2.php';
$profil_pro = 0;
if (!isset($_SESSION['id_pro'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /connexion?success_url={$currentUrl}");
    exit;
}
require_once '../includes/webhook.php';
$numero_pro = $_SESSION['id_pro'] ?? null;

$errors = [];
$success = "";

$jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des champs
    $specialisation = $_POST['specialisation'] ?? null;
    $description = $_POST['description'] ?? '';
    $adresse = $_POST['adresse_complete'] ?? '';
    $timezone = $_POST['timezone'] ?? 'Europe/Paris';
    $longitude = $_POST['longitude'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $phone_type = $_POST['phone_type'] ?? 'portable';
    $phone_number = $_POST['phone_number'] ?? '';
    $taux_horaire = floatval($_POST['taux_horaire'] ?? 0);
    $taux_horaire2 = floatval($_POST['taux_horaire2'] ?? 0);
    $taux_horaire3 = floatval($_POST['taux_horaire3'] ?? 0);

    $mode_horaire = $_POST['mode_horaire'] ?? 'semaine';
    $ferme_exceptionnel = isset($_POST['ferme_exceptionnel']);
    $logoFileName = null; // par défaut aucun logo uploadé

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName = basename($_FILES['logo']['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // Taille max 2 Mo
        if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            die("Fichier trop volumineux (max 2 Mo).");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);

        if (!in_array($fileExtension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            die("Format ou type MIME non autorisé.");
        }

        // Supprime l'ancien logo
        $stmt = $db->prepare("SELECT logo FROM entreprises WHERE numero_pro = ?");
        $stmt->execute([$numero_pro]);
        $oldLogo = $stmt->fetchColumn();
        if ($oldLogo && file_exists(__DIR__ . '/uploads/' . $oldLogo)) {
            unlink(__DIR__ . '/uploads/' . $oldLogo);
        }

        $newFileName = uniqid('logo_', true) . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $logoFileName = $newFileName;
        } else {
            die("Erreur lors du déplacement du fichier.");
        }
        $updateLogo = $db->prepare("UPDATE entreprises SET logo = ? WHERE numero_pro = ?");
        $updateLogo->execute([$logoFileName, $numero_pro]);
    }
    $cleanPhoneNumber = preg_replace('/(?!^\+)[^\d]/', '', $phone_number);

    // Supprimer le + pour le calcul de la longueur
    $length = strlen(ltrim($cleanPhoneNumber, '+'));

    // Vérifier la longueur minimale et maximale (standard ITU E.164)
    if ($length < 6 || $length > 15) {
        $errors[] = "Le numéro de téléphone doit contenir entre 6 et 15 chiffres.";
    }
    // Vérification du taux horaire
    if ($taux_horaire <= 0) {
        $errors[] = "Le taux horaire doit être supérieur à zéro.";
    }

    // Construction des horaires
    $horaires = [];

    $stmt = $db->prepare("SELECT * FROM horaires WHERE numero_pro = ?");
    $stmt->execute([$numero_pro]);
    $horairesExistants = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Puis dans ta boucle :
    foreach ($jours as $jour) {
        if ($ferme_exceptionnel) {
            $horaires["{$jour}_debut"] = null;
            $horaires["{$jour}_fin"] = null;
            $horaires["{$jour}_debut2"] = null;
            $horaires["{$jour}_fin2"] = null;
            continue;
        }

        $inclure = false;
        if ($mode_horaire === 'semaine' && in_array($jour, ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'])) {
            $inclure = true;
        } elseif ($mode_horaire === 'weekend' && in_array($jour, ['samedi', 'dimanche'])) {
            $inclure = true;
        } elseif ($mode_horaire === 'personnalise') {
            $inclure = true;
        }

        if ($inclure) {
            // récupération des horaires postés
            $debut = $_POST["{$jour}_debut"] ?? null;
            $fin = $_POST["{$jour}_fin"] ?? null;
            $debut2 = $_POST["{$jour}_debut2"] ?? null;
            $fin2 = $_POST["{$jour}_fin2"] ?? null;

            // validations...

            $horaires["{$jour}_debut"] = $debut ?: null;
            $horaires["{$jour}_fin"] = $fin ?: null;
            $horaires["{$jour}_debut2"] = $debut2 ?: null;
            $horaires["{$jour}_fin2"] = $fin2 ?: null;
        } else {
            // On garde les horaires existants
            $horaires["{$jour}_debut"] = $horairesExistants["{$jour}_debut"] ?? null;
            $horaires["{$jour}_fin"] = $horairesExistants["{$jour}_fin"] ?? null;
            $horaires["{$jour}_debut2"] = $horairesExistants["{$jour}_debut2"] ?? null;
            $horaires["{$jour}_fin2"] = $horairesExistants["{$jour}_fin2"] ?? null;
        }
    }

    // S'il n'y a pas d'erreurs
    if (empty($errors)) {
        // Mise à jour entreprise
        $stmt = $db->prepare("UPDATE entreprises SET description = ?, spe = ?, phone_type = ?, phone_number = ?, latitude = ?, longitude = ?, taux_horaire = ?,taux_horaire2 = ?,taux_horaire3 = ?, timezone = ?, adresse = ? WHERE numero_pro = ?");
        $stmt->execute([$description, $specialisation, $phone_type, $phone_number, $latitude, $longitude, $taux_horaire, $taux_horaire2, $taux_horaire3, $timezone, $adresse, $numero_pro]);

        // Vérifier si on a déjà des horaires
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM horaires WHERE numero_pro = ?");
        $stmtCheck->execute([$numero_pro]);

        if ($stmtCheck->fetchColumn() > 0) {
            // Update
            $set = implode(", ", array_map(fn($key) => "$key = ?", array_keys($horaires)));
            $sql = "UPDATE horaires SET $set WHERE numero_pro = ?";
            $params = array_values($horaires);
            $params[] = $numero_pro;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } else {
            // Insert
            $cols = implode(", ", array_keys($horaires));
            $placeholders = implode(", ", array_fill(0, count($horaires), '?'));
            $sql = "INSERT INTO horaires (numero_pro, $cols) VALUES (?, $placeholders)";
            $params = array_merge([$numero_pro], array_values($horaires));
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        $success = "Profil mis à jour avec succès.";
    }
}

// Chargement pour affichage
$stmt = $db->prepare("SELECT description,taux_horaire, taux_horaire2, taux_horaire3, spe, phone_type, phone_number,adresse,logo FROM entreprises WHERE numero_pro = ?");
$stmt->execute([$numero_pro]);
$entreprise = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM horaires WHERE numero_pro = ?");
$stmt->execute([$numero_pro]);
$horairesData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

function detectMode(array $horaires): string
{
    $sem = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];
    $week = ['samedi', 'dimanche'];

    $allClosed = true;
    foreach ($horaires as $v) {
        if ($v !== null && $v !== '') {
            $allClosed = false;
            break;
        }
    }
    if ($allClosed)
        return 'ferme';

    $isSemaine = true;
    foreach ($sem as $jour) {
        if (empty($horaires[$jour . '_debut']) || empty($horaires[$jour . '_fin'])) {
            $isSemaine = false;
            break;
        }
        if (!empty($horaires[$jour . '_debut2']) || !empty($horaires[$jour . '_fin2'])) {
            $isSemaine = false;
            break;
        }
    }
    foreach ($week as $jour) {
        if (
            !empty($horaires[$jour . '_debut']) || !empty($horaires[$jour . '_fin']) ||
            !empty($horaires[$jour . '_debut2']) || !empty($horaires[$jour . '_fin2'])
        ) {
            $isSemaine = false;
            break;
        }
    }
    if ($isSemaine)
        return 'semaine';

    $isWeekend = true;
    foreach ($sem as $jour) {
        if (
            !empty($horaires[$jour . '_debut']) || !empty($horaires[$jour . '_fin']) ||
            !empty($horaires[$jour . '_debut2']) || !empty($horaires[$jour . '_fin2'])
        ) {
            $isWeekend = false;
            break;
        }
    }
    foreach ($week as $jour) {
        if (empty($horaires[$jour . '_debut']) || empty($horaires[$jour . '_fin'])) {
            $isWeekend = false;
            break;
        }
    }
    if ($isWeekend)
        return 'weekend';

    return 'personnalise';
}

$mode_horaire_selected = detectMode($horairesData);
$ferme_exceptionnel_checked = ($mode_horaire_selected === 'ferme');
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
    <title>Profil Prestataire</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js/minified/introjs.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener("DOMContentLoaded", function () {
    const menuBtn = document.getElementById("menuBtn");
    const menu = document.getElementById("menu");
    
    if (menuBtn && menu) {
        menuBtn.addEventListener("click", function() {
            menu.classList.toggle("hidden");
            console.log("Menu toggled"); // Pour debug
        });
    }
});
    </script>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col md:flex-row">

     <header class="md:hidden flex items-center justify-between bg-white shadow p-4">
        <h2 class="text-xl font-bold text-green-600">Automoclick - PRO </h2>
        <button id="menuBtn" class="text-3xl text-green-600 font-bold">&#9776;</button>
    </header>

    <!-- Sidebar -->
    <aside id="menu"
        class="w-full md:w-64 bg-white shadow-lg p-4 space-y-4 md:space-y-0 md:flex md:flex-col md:fixed md:top-0 md:left-0 md:h-full hidden md:block z-50">
        <div class="p-4 border-b hidden md:block">
            <h2 class="text-xl font-bold text-green-600">Automoclick - PRO</h2>
        </div>
        <nav class="flex flex-col md:p-4 space-y-2 flex-grow">
            <a href="dashbord"
                class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Tableau de
                bord</a>
            <a href="profil"
                class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Profil</a>
            <a href="prestation"
                class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Mes
                prestations</a>
            <a href="d&f" class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Devis et
                factures</a>
            <a href="setting"
                class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Paramètres</a>
            <a href="z" class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold">Se
                déconnecter</a>
        </nav>
        <div class="text-center text-sm text-gray-500 border-t pt-4">&copy; 2025 Automoclick</div>
    </aside>

    <main class="flex-grow md:ml-64 p-4 md:p-8 w-full max-w-full md:max-w-4xl mx-auto">
        <h1 class="text-2xl md:text-3xl font-extrabold mb-6">Profil</h1>
        <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 rounded text-red-700">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 rounded text-green-700">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-8">

            <!-- Onglets -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="flex space-x-4 overflow-x-auto" aria-label="Tabs">
                    <button type="button"
                        class="tab-btn px-4 py-2 text-sm font-medium text-gray-600 hover:text-green-600 border-b-2 border-transparent hover:border-green-600 focus:outline-none active"
                        data-tab="tab-profil">Profil</button>
                    <button type="button"
                        class="tab-btn px-4 py-2 text-sm font-medium text-gray-600 hover:text-green-600 border-b-2 border-transparent hover:border-green-600 focus:outline-none"
                        data-tab="tab-horaires">Horaires</button>
                    <button type="button"
                        class="tab-btn px-4 py-2 text-sm font-medium text-gray-600 hover:text-green-600 border-b-2 border-transparent hover:border-green-600 focus:outline-none"
                        data-tab="tab-adresse">Adresse</button>
                </nav>
            </div>

            <!-- Contenus des onglets -->
            <div>
                <!-- Onglet Profil -->
                <div id="tab-profil" class="tab-content">
                    <fieldset class="border border-gray-300 rounded p-6 bg-white shadow-sm">
                        <div id="logo" class="mb-6">
                            <label for="logoInput" class="block mb-2 font-semibold text-gray-700">Logo
                                professionnel</label>
                            <input type="file" id="logoInput" name="logo" accept="image/*" class="block w-full text-sm text-gray-500
              file:mr-4 file:py-2 file:px-4
              file:rounded file:border-0
              file:text-sm file:font-semibold
              file:bg-green-50 file:text-green-700
              hover:file:bg-green-100 cursor-pointer" />
                            <?php if (!empty($entreprise['logo'])): ?>
                                <img src="/uploads/<?= htmlspecialchars($entreprise['logo']) ?>" alt="Logo du professionnel"
                                    style="max-width:200px; max-height:200px;">
                            <?php else: ?>
                                <p>Aucun logo disponible.</p>
                            <?php endif; ?>
                        </div>

                        <legend id="des" class="text-xl font-semibold mb-4">Description et téléphone</legend>

                        <label for="spe" class="block mb-2 font-medium">Votre spécialisation</label>
                        <select id="spe" name="specialisation"
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Sélectionnez la spécialisation</option>
                            <option value="mecanique" <?= ($entreprise['spe'] ?? '') === 'mecanique' ? 'selected' : '' ?>>
                                Mécanique</option>
                            <option value="carrosserie" <?= ($entreprise['spe'] ?? '') === 'carrosserie' ? 'selected' : '' ?>>Carrossier(e)</option>
                            <option value="depanneur" <?= ($entreprise['spe'] ?? '') === 'depanneur' ? 'selected' : '' ?>>
                                Dépanneur</option>
                            <option value="controle" <?= ($entreprise['spe'] ?? '') === 'controle' ? 'selected' : '' ?>>
                                Contrôleur(se) technique</option>
                            <option value="electro" <?= ($entreprise['spe'] ?? '') === 'electro' ? 'selected' : '' ?>>
                                Électromécanicien(ne)</option>
                            <option value="garage" <?= ($entreprise['spe'] ?? '') === 'garage' ? 'selected' : '' ?>>Garage
                            </option>
                            <option value="nettoyage" <?= ($entreprise['spe'] ?? '') === 'nettoyage' ? 'selected' : '' ?>>
                                Nettoyage</option>
                            <option value="peintre" <?= ($entreprise['spe'] ?? '') === 'peintre' ? 'selected' : '' ?>>
                                Peintre</option>
                            <option value="soudeur" <?= ($entreprise['spe'] ?? '') === 'soudeur' ? 'selected' : '' ?>>
                                Soudeur(se)</option>
                            <option value="prepa" <?= ($entreprise['spe'] ?? '') === 'prepa' ? 'selected' : '' ?>>
                                Préparateur automobile</option>
                            <option value="loueur" <?= ($entreprise['spe'] ?? '') === 'loueur' ? 'selected' : '' ?>>
                                Location de véhicule</option>
                            <option value="vendeur-piece" <?= ($entreprise['spe'] ?? '') === 'vendeur-piece' ? 'selected' : '' ?>>Vendeur de pièce</option>
                            <option value="vendeur-auto" <?= ($entreprise['spe'] ?? '') === 'vendeur-auto' ? 'selected' : '' ?>>Vendeur de véhicule</option>
                            <option value="tunning" <?= ($entreprise['spe'] ?? '') === 'tunning' ? 'selected' : '' ?>>
                                Tuning</option>
                        </select>

                        <label for="description" class="block mt-6 mb-2 font-medium">Description (facultatif) :</label>
                        <textarea id="description" name="description" rows="4"
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"><?= htmlspecialchars($entreprise['description'] ?? '') ?></textarea>

                        <label for="phone_type" class="block mt-6 mb-2 font-medium">Type de téléphone :</label>
                        <select id="phone_type" name="phone_type" required
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="portable" <?= (isset($entreprise['phone_type']) && $entreprise['phone_type'] === 'portable') ? 'selected' : '' ?>>Portable</option>
                            <option value="fixe" <?= (isset($entreprise['phone_type']) && $entreprise['phone_type'] === 'fixe') ? 'selected' : '' ?>>Fixe</option>
                        </select>

                        <label for="phone_number" class="block mt-6 mb-2 font-medium">Numéro de téléphone :</label>
                        <input type="tel" id="phone_number" name="phone_number" required
                            value="<?= isset($entreprise['phone_number']) ? str_replace(' ', '', htmlspecialchars($entreprise['phone_number'])) : '' ?>"
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">

                        <label for="taux_horaire" class="block mt-6 mb-2 font-medium">Taux horaire (M1) (€) :</label>
                        <input type="number" step="0.01" min="0" id="taux_horaire" name="taux_horaire" required
                            value="<?= htmlspecialchars($entreprise['taux_horaire'] ?? '') ?>"
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                    <label for="taux_horaire2" class="block mt-6 mb-2 font-medium">Taux horaire (M2) (€) :</label>
                            <input type="number" step="0.01" min="0" id="taux_horaire2" name="taux_horaire2" required
                            value="<?= htmlspecialchars($entreprise['taux_horaire2'] ?? '') ?>"
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                    <label for="taux_horaire3" class="block mt-6 mb-2 font-medium">Taux horaire (M3) (€) :</label> 
                            <input type="number" step="0.01" min="0" id="taux_horaire3" name="taux_horaire3" required
                            value="<?= htmlspecialchars($entreprise['taux_horaire3'] ?? '') ?>"
                            class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                    </fieldset>
                </div>

                <!-- Onglet Horaires -->
                <div id="tab-horaires" class="tab-content hidden">
                    <fieldset id="horaires" class="border border-gray-300 rounded p-6 bg-white shadow-sm">
                        <legend class="text-xl font-semibold mb-4">Horaires d'ouverture</legend>
                       <?php
                $fuseaux = DateTimeZone::listIdentifiers();
                $query = $db->prepare("SELECT timezone FROM entreprises WHERE numero_pro = ?");
                $query->execute([$numero_pro]);
                $timezone_pro = $query->fetchColumn();

                $timezone_actuel = $timezone_pro ?? 'Europe/Paris';

                $fuseaux = DateTimeZone::listIdentifiers();
                ?>

                <div class="mb-4">
                    <label for="timezone" class="block mb-2 font-medium">Fuseau horaire du professionnel :</label>
                    <select id="timezone" name="timezone"
                        class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 w-full max-w-md">
                        <?php foreach ($fuseaux as $tz): ?>
                            <option value="<?= htmlspecialchars($tz) ?>" <?= $tz === $timezone_actuel ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tz) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <legend class="text-xl font-semibold mb-4">Horaires d'ouverture</legend>


                <!-- Mode horaire radio -->
                <div class="flex flex-wrap gap-6 mb-4">
                    <label class="inline-flex items-center space-x-2">
                        <input type="radio" name="mode_horaire" value="semaine" <?= (isset($mode_horaire_selected) && $mode_horaire_selected === 'semaine') ? 'checked' : '' ?> class="form-radio text-green-600" />
                        <span>Semaine (lun - ven)</span>
                    </label>

                    <label class="inline-flex items-center space-x-2">
                        <input type="radio" name="mode_horaire" value="weekend" <?= (isset($mode_horaire_selected) && $mode_horaire_selected === 'weekend') ? 'checked' : '' ?> class="form-radio text-green-600" />
                        <span>Week-end (sam - dim)</span>
                    </label>

                    <label class="inline-flex items-center space-x-2">
                        <input type="radio" name="mode_horaire" value="personnalise" <?= (isset($mode_horaire_selected) && $mode_horaire_selected === 'personnalise') ? 'checked' : '' ?>
                            class="form-radio text-green-600" />
                        <span>Personnalisé</span>
                    </label>

                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox" id="ferme_exceptionnel" name="ferme_exceptionnel"
                            <?= !empty($ferme_exceptionnel_checked) ? 'checked' : '' ?>
                            class="form-checkbox text-green-600" />
                        <span>Fermé exceptionnellement</span>
                    </label>
                </div>

                <!-- Date fermeture exceptionnelle -->
                <div id="date_fermeture_container" class="mb-6"
                    style="<?= !empty($ferme_exceptionnel_checked) ? '' : 'display:none;' ?>">
                    <label for="date_fermeture" class="block mb-2 font-medium">Date fermeture :</label>
                    <input type="date" id="date_fermeture" name="date_fermeture"
                        value="<?= htmlspecialchars($date_fermeture ?? '') ?>"
                        class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 w-48" />
                </div>
                <!-- Horaires semaine (lun-ven) -->
                <div id="horaires_semaine" class="space-y-4" style="display:none;">
                    <div>
                        <label for="heure_ouverture" class="block mb-1 font-medium">Heure d'ouverture (lun - ven)
                            :</label>
                        <input type="time" id="heure_ouverture" name="heure_ouverture"
                            value="<?= htmlspecialchars($horairesData['lundi_debut'] ?? '') ?>"
                            <?= !empty($ferme_exceptionnel_checked) ? 'disabled' : '' ?>
                            class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" />
                    </div>
                    <div>
                        <label for="heure_fermeture" class="block mb-1 font-medium">Heure de fermeture (lun - ven)
                            :</label>
                        <input type="time" id="heure_fermeture" name="heure_fermeture"
                            value="<?= htmlspecialchars($horairesData['vendredi_fin'] ?? '') ?>"
                            <?= !empty($ferme_exceptionnel_checked) ? 'disabled' : '' ?>
                            class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" />
                    </div>
                </div>

                <!-- Horaires week-end (sam - dim) -->
                <div id="horaires_weekend" class="space-y-4" style="display:none;">
                    <div>
                        <label for="heure_ouverture_weekend" class="block mb-1 font-medium">Heure d'ouverture (sam -
                            dim) :</label>
                        <input type="time" id="heure_ouverture_weekend" name="heure_ouverture_weekend"
                            value="<?= htmlspecialchars($horairesData['samedi_debut'] ?? '') ?>"
                            <?= !empty($ferme_exceptionnel_checked) ? 'disabled' : '' ?>
                            class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" />
                    </div>
                    <div>
                        <label for="heure_fermeture_weekend" class="block mb-1 font-medium">Heure de fermeture (sam -
                            dim) :</label>
                        <input type="time" id="heure_fermeture_weekend" name="heure_fermeture_weekend"
                            value="<?= htmlspecialchars($horairesData['dimanche_fin'] ?? '') ?>"
                            <?= !empty($ferme_exceptionnel_checked) ? 'disabled' : '' ?>
                            class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" />
                    </div>
                </div>

                <!-- Horaires personnalisés (lundi à dimanche) -->
                <div id="horaires_personnalises" class="space-y-4" style="display:none;">
                    <?php
                    $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
                    foreach ($jours as $jour):
                        $debut = $horairesData[$jour . '_debut'] ?? '';
                        $fin = $horairesData[$jour . '_fin'] ?? '';
                        $debut2 = $horairesData[$jour . '_debut2'] ?? '';
                        $fin2 = $horairesData[$jour . '_fin2'] ?? '';
                        ?>
                        <div>
                            <label class="block font-medium mb-1"><?= ucfirst($jour) ?></label>
                            <div class="flex items-center gap-2 mb-1">
                                <input type="time" name="<?= $jour ?>_debut" value="<?= htmlspecialchars($debut) ?>"
                                    class="border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-green-500"
                                    <?= !empty($ferme_exceptionnel_checked) ? 'disabled' : '' ?> />
                                <span>à</span>
                                <input type="time" name="<?= $jour ?>_fin" value="<?= htmlspecialchars($fin) ?>"
                                    class="border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-green-500"
                                    <?= !empty($ferme_exceptionnel_checked) ? 'disabled' : '' ?> />
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="time" name="<?= $jour ?>_debut2" value="<?= htmlspecialchars($debut2) ?>"
                                    class="border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-green-500"
                                    <?= !empty($ferme_exceptionnel_checked) ? 'disabled' : '' ?> />
                                <span>à</span>
                                <input type="time" name="<?= $jour ?>_fin2" value="<?= htmlspecialchars($fin2) ?>"
                                    class="border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-green-500"
                                    <?= !empty($ferme_exceptionnel_checked) ? 'disabled' : '' ?> />
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
       <div id="horaires_adaptes" class="mt-4 p-3 border rounded bg-gray-50 text-gray-700"></div>

                    </fieldset>
                </div>

                <!-- Onglet Adresse -->
                <div id="tab-adresse" class="tab-content hidden">
                    <fieldset class="border border-gray-300 rounded p-6 bg-white shadow-sm">
                        <legend class="text-xl font-semibold mb-4">Adresse d'activité</legend>
                        <div class="flex items-center mb-4 gap-2">
                            <input id="adresse" type="text" placeholder="Adresse professionnelle"
                                class="w-full border rounded p-2" value="<?= $entreprise['adresse'] ?>" />
                            <button type="button" onclick="geolocateUser()"
                                class="bg-gray-200 px-3 py-2 rounded hover:bg-gray-300">
                                📍 Me localiser
                            </button>
                        </div>
                        <!-- Carte -->
                        <div id="map" style="height: 400px;" class="mb-4"></div>

                        <!-- Champs cachés à envoyer en base -->
                        <input type="hidden" name="latitude" id="latitude">
                        <input type="hidden" name="longitude" id="longitude">
                        <input type="hidden" name="adresse_complete" id="adresse_complete">
                    </fieldset>
                </div>
            </div>

            <button type="submit"
                class="bg-green-600 text-white px-6 py-3 rounded font-semibold hover:bg-green-700 transition duration-300">
                Mettre à jour
            </button>
        </form>
    </main>

    <!-- JS Onglets -->
    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove("text-green-600", "border-green-600", "active");
                    b.classList.add("text-gray-600", "border-transparent");
                });
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add("hidden"));
                btn.classList.add("text-green-600", "border-green-600", "active");
                btn.classList.remove("text-gray-600", "border-transparent");
                document.getElementById(btn.dataset.tab).classList.remove("hidden");
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/intro.js/minified/intro.min.js"></script>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        const profilPro = <?php echo json_encode($profil_pro); ?>;

        function startTutorial() {


            const intro = introJs();
            intro.setOptions({
                steps: [{
                    intro: "Bienvenue dans votre espace professionnel, où vous pouvez configurer les informations relatives à votre profil."

                },
                {
                    element: '#logo',
                    intro: "Vous pouvez insérer ici le logo de votre entreprise."
                },
                {
                    element: '#des',
                    intro: "Ajoutez la spécialité de votre entreprise, votre numéro de téléphone, votre taux horaire ainsi qu'une brève description de vos services."
                },
                {
                    element: '#horaire',
                    intro: "Définissez ici vos horaires d’ouverture et de fermeture de manière personnalisée selon vos disponibilités."
                },
                {
                    element: '#adresses',
                    intro: "Renseignez votre adresse professionnelle afin d’indiquer votre localisation à vos clients."
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
                window.location.href = 'prestation';
            });
            intro.start();
        }

        // Démarrer automatiquement si profil non complété
        if (profilPro === 1) {
            ;
            startTutorial();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js"></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener("DOMContentLoaded", () => {
            const phoneInputField = document.getElementById("phone_number");
            const form = document.querySelector("form");

            const iti = window.intlTelInput(phoneInputField, {
                initialCountry: "fr",
                separateDialCode: false,
                formatOnDisplay: true,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js",
            });

            form.addEventListener("submit", () => {
                phoneInputField.value = iti.getNumber(intlTelInputUtils.numberFormat.E164);
            });
        });
    </script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        // Affichage correct des blocs selon mode sélectionné
        function toggleHoraires() {
            const mode = document.querySelector('input[name="mode_horaire"]:checked')?.value;
            const joursSemaine = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];
            const joursWeekend = ['samedi', 'dimanche'];

            // Affichage des blocs horaires
            document.getElementById('horaires_semaine').style.display = (mode === 'semaine') ? 'block' : 'none';
            const weekendBlock = document.getElementById('horaires_weekend');
            if (weekendBlock) {
                weekendBlock.style.display = (mode === 'weekend') ? 'block' : 'none';
            }
            document.getElementById('horaires_personnalises').style.display = (mode === 'personnalise') ? 'block' : 'none';

            // Réinitialiser tous les champs
            const tousLesJours = [...joursSemaine, ...joursWeekend];
            tousLesJours.forEach(jour => {
                ['debut', 'fin', 'debut2', 'fin2'].forEach(suffix => {
                    const champ = document.querySelector(`input[name="${jour}_${suffix}"]`);
                    if (champ) {
                        champ.removeAttribute('readonly');
                        champ.classList.remove('readonly');
                    }
                });
            });

            // Remplir ou désactiver selon le mode
            if (mode === 'semaine') {
                const ouverture = document.querySelector('input[name="heure_ouverture"]').value;
                const fermeture = document.querySelector('input[name="heure_fermeture"]').value;

                joursSemaine.forEach(jour => {
                    document.querySelector(`input[name="${jour}_debut"]`).value = ouverture;
                    document.querySelector(`input[name="${jour}_fin"]`).value = fermeture;
                });

                joursWeekend.forEach(jour => {
                    ['debut', 'fin', 'debut2', 'fin2'].forEach(suffix => {
                        const champ = document.querySelector(`input[name="${jour}_${suffix}"]`);
                        if (champ) {
                            champ.value = '';
                            champ.setAttribute('readonly', true);
                            champ.classList.add('readonly');
                        }
                    });
                });

            } else if (mode === 'weekend') {
                const ouverture = document.querySelector('input[name="heure_ouverture_weekend"]').value;
                const fermeture = document.querySelector('input[name="heure_fermeture_weekend"]').value;

                joursWeekend.forEach(jour => {
                    document.querySelector(`input[name="${jour}_debut"]`).value = ouverture;
                    document.querySelector(`input[name="${jour}_fin"]`).value = fermeture;
                });

                joursSemaine.forEach(jour => {
                    ['debut', 'fin', 'debut2', 'fin2'].forEach(suffix => {
                        const champ = document.querySelector(`input[name="${jour}_${suffix}"]`);
                        if (champ) {
                            champ.value = '';
                            champ.setAttribute('readonly', true);
                            champ.classList.add('readonly');
                        }
                    });
                });
            }
            // Si personnalisé, ne rien faire.
        }

        function toggleFermeExceptionnel() {
            const fermeCheckbox = document.getElementById('ferme_exceptionnel');
            const disabled = fermeCheckbox.checked;

            document.querySelectorAll('#horaires_semaine input, #horaires_weekend input, #horaires_personnalises input')
                .forEach(input => {
                    input.disabled = disabled;
                });

            document.getElementById('date_fermeture_container').style.display = disabled ? 'block' : 'none';
        }

        // Ne pas envoyer la coupure 2 en mode semaine ou weekend
        function handleFormSubmit(event) {
            const mode = document.querySelector('input[name="mode_horaire"]:checked')?.value;

            const joursSemaine = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];
            const joursWeekend = ['samedi', 'dimanche'];
            const tousLesJours = [...joursSemaine, ...joursWeekend];

            // Réactiver tous les champs et les rendre éditables
            tousLesJours.forEach(jour => {
                ['debut', 'fin', 'debut2', 'fin2'].forEach(suffix => {
                    const champ = document.querySelector(`input[name="${jour}_${suffix}"]`);
                    if (champ) {
                        champ.removeAttribute('readonly');
                        champ.classList.remove('readonly');
                        champ.setAttribute('name',
                            `${jour}_${suffix}`); // s'assurer que le name est bien là
                    }
                });
            });

            if (mode === 'semaine') {
                const ouverture = document.querySelector('input[name="heure_ouverture"]').value;
                const fermeture = document.querySelector('input[name="heure_fermeture"]').value;

                // Remplir les jours de semaine
                joursSemaine.forEach(jour => {
                    document.querySelector(`input[name="${jour}_debut"]`).value = ouverture;
                    document.querySelector(`input[name="${jour}_fin"]`).value = fermeture;
                });

                // Supprimer les noms des inputs weekend pour éviter la soumission
                joursWeekend.forEach(jour => {
                    ['debut', 'fin', 'debut2', 'fin2'].forEach(suffix => {
                        const champ = document.querySelector(`input[name="${jour}_${suffix}"]`);
                        if (champ) {
                            champ.value = '';
                            champ.setAttribute('readonly', true);
                            champ.classList.add('readonly');
                            champ.removeAttribute('name'); // <-- empêche l’envoi
                        }
                    });
                });

            } else if (mode === 'weekend') {
                const ouverture = document.querySelector('input[name="heure_ouverture_weekend"]').value;
                const fermeture = document.querySelector('input[name="heure_fermeture_weekend"]').value;

                joursWeekend.forEach(jour => {
                    document.querySelector(`input[name="${jour}_debut"]`).value = ouverture;
                    document.querySelector(`input[name="${jour}_fin"]`).value = fermeture;
                });

                // Supprimer les noms des inputs semaine pour éviter la soumission
                joursSemaine.forEach(jour => {
                    ['debut', 'fin', 'debut2', 'fin2'].forEach(suffix => {
                        const champ = document.querySelector(`input[name="${jour}_${suffix}"]`);
                        if (champ) {
                            champ.value = '';
                            champ.setAttribute('readonly', true);
                            champ.classList.add('readonly');
                            champ.removeAttribute('name'); // <-- empêche l’envoi
                        }
                    });
                });

            } else if (mode === 'personnalise') {
                // Tous les champs sont conservés, rien à modifier
            }
        }


        // Initialisation
        toggleHoraires();
        toggleFermeExceptionnel();

        // Listeners
        document.querySelectorAll('input[name="mode_horaire"]').forEach(radio => {
            radio.addEventListener('change', () => {
                toggleHoraires();
                toggleFermeExceptionnel(); // remettre à jour désactivation si nécessaire
            });
        });

        document.getElementById('ferme_exceptionnel').addEventListener('change', toggleFermeExceptionnel);

        // Gestion du submit
        const form = document.querySelector('form');
        form.addEventListener('submit', handleFormSubmit);
    </script>
    <script nonce="<?= htmlspecialchars($nonce) ?>"
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCxSYMnBapanxmvZ77sGnWdupt6yDsJc7g&libraries=places&callback=initAutocomplete&loading=async"
       async defer></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        function geolocateUser() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    const location = {
                        lat,
                        lng
                    };
                    map.setCenter(location);
                    map.setZoom(15);
                    marker.setPosition(location);

                    document.getElementById("latitude").value = lat;
                    document.getElementById("longitude").value = lng;

                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({
                        location: location
                    }, function (results, status) {
                        if (status === "OK" && results[0]) {
                            document.getElementById("adresse").value = results[0].formatted_address;
                            document.getElementById("adresse_complete").value = results[0]
                                .formatted_address;
                        } else {
                            alert("Adresse introuvable depuis votre position.");
                        }
                    });
                }, function (error) {
                    alert("Impossible d'accéder à votre position.");
                });
            } else {
                alert("La géolocalisation n’est pas supportée par votre navigateur.");
            }
        }
        let map, marker, autocomplete;

        function initAutocomplete() {
            let defaultLocation = { lat: 14.6415, lng: -61.0242 }; // par défaut : Martinique

            map = new google.maps.Map(document.getElementById("map"), {
                center: defaultLocation,
                zoom: 12,
            });

            marker = new google.maps.Marker({
                map: map,
                position: defaultLocation,
            });

            autocomplete = new google.maps.places.Autocomplete(
                document.getElementById("adresse"),
                { types: ["establishment", "geocode"] }
            );

            autocomplete.addListener("place_changed", function () {
                const place = autocomplete.getPlace();
                if (!place.geometry) return;

                const location = place.geometry.location;
                map.setCenter(location);
                map.setZoom(15);
                marker.setPosition(location);

                document.getElementById("latitude").value = location.lat();
                document.getElementById("longitude").value = location.lng();
                document.getElementById("adresse_complete").value = place.formatted_address;
            });

            marker.addListener("dragend", function () {
                const pos = marker.getPosition();
                document.getElementById("latitude").value = pos.lat();
                document.getElementById("longitude").value = pos.lng();
            });

            map.addListener("click", function (event) {
                const location = event.latLng;

                marker.setPosition(location);
                map.panTo(location);

                document.getElementById("latitude").value = location.lat();
                document.getElementById("longitude").value = location.lng();

                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ location: location }, function (results, status) {
                    if (status === "OK" && results[0]) {
                        document.getElementById("adresse").value = results[0].formatted_address;
                        document.getElementById("adresse_complete").value = results[0].formatted_address;
                    } else {
                        document.getElementById("adresse_complete").value = "";
                    }
                });
            });

            // 🔽 Ajout : géocoder l'adresse existante si présente
            const adresseExistante = document.getElementById("adresse").value.trim();
            if (adresseExistante !== "") {
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ address: adresseExistante }, function (results, status) {
                    if (status === "OK" && results[0]) {
                        const location = results[0].geometry.location;
                        map.setCenter(location);
                        map.setZoom(15);
                        marker.setPosition(location);

                        document.getElementById("latitude").value = location.lat();
                        document.getElementById("longitude").value = location.lng();
                        document.getElementById("adresse_complete").value = results[0].formatted_address;
                    }
                });
            }
        }

    </script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.querySelector("form").addEventListener("submit", function (e) {
            const adresse = document.getElementById("adresse").value;
            const lat = document.getElementById("latitude").value;
            const lng = document.getElementById("longitude").value;
            const adresse_complete = document.getElementById("adresse_complete").value;

            // Si les champs sont vides, on essaie de géocoder manuellement
            if ((!lat || !lng || !adresse_complete) && adresse.trim() !== "") {
                e.preventDefault(); // On stoppe temporairement le submit

                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({
                    address: adresse
                }, function (results, status) {
                    if (status === "OK" && results[0]) {
                        const location = results[0].geometry.location;
                        document.getElementById("latitude").value = location.lat();
                        document.getElementById("longitude").value = location.lng();
                        document.getElementById("adresse_complete").value = results[0].formatted_address;

                        // Submit le formulaire à nouveau
                        e.target.submit();
                    } else {
                       
                    }
                });
            }
        });
    </script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        const horairesPrestataire = <?= json_encode($horairesData) ?>;
        const timezonePrestataire = <?= json_encode($timezone_pro) ?>;
    </script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        // Fuseau du client détecté automatiquement par navigateur
        const timezoneClient = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

        // Fonction pour convertir heure "HH:mm" d’un fuseau à un autre (format 24h)
        function convertTimeZone(timeStr, fromTZ, toTZ) {
            if (!timeStr || !timeStr.includes(':')) return '';

            const [hours, minutes] = timeStr.split(':');
            const now = new Date();

            const dateUTC = new Date(Date.UTC(
                now.getUTCFullYear(),
                now.getUTCMonth(),
                now.getUTCDate(),
                parseInt(hours),
                parseInt(minutes)
            ));

            function getOffset(date, timeZone) {
                const dtf = new Intl.DateTimeFormat('en-US', {
                    hour12: false,
                    timeZone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                });
                const parts = dtf.formatToParts(date);
                const filled = {};
                for (const {
                    type,
                    value
                }
                    of parts) {
                    filled[type] = value;
                }
                const asString = `${filled.year}-${filled.month}-${filled.day}T${filled.hour}:${filled.minute}:${filled.second}`;
                const localDate = new Date(asString + 'Z');
                return (localDate - date) / (60 * 1000);
            }

            const offsetFrom = getOffset(dateUTC, fromTZ);
            const offsetTo = getOffset(dateUTC, toTZ);

            const totalMinutes = parseInt(hours) * 60 + parseInt(minutes);
            const newTotalMinutes = totalMinutes + (offsetTo - offsetFrom);
            const normalizedMinutes = (newTotalMinutes + 1440) % 1440;
            const newHours = Math.floor(normalizedMinutes / 60);
            const newMinutes = normalizedMinutes % 60;

            return `${String(newHours).padStart(2, '0')}:${String(newMinutes).padStart(2, '0')}`;
        }

    </script>
    <!-- <script nonce="<?= htmlspecialchars($nonce) ?>">
        // Quand un fichier est sélectionné
        document.getElementById('logoInput').addEventListener('change', function (event) {
            const file = event.target.files[0];
            const previewContainer = document.getElementById('logoPreviewContainer');
            const previewImage = document.getElementById('logoPreview');
            const modalImage = document.getElementById('modalImage');

            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImage.src = e.target.result;
                    modalImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
                previewImage.src = '#';
                modalImage.src = '#';
            }
        });

        // Ouvre la modale au clic sur l’aperçu
        document.getElementById('logoPreviewContainer').addEventListener('click', function () {
            document.getElementById('logoModal').style.display = 'flex';
        });

        // Ferme la modale au clic sur la croix
        document.getElementById('modalCloseBtn').addEventListener('click', function () {
            document.getElementById('logoModal').style.display = 'none';
        });

        // Ferme la modale si on clique en dehors de l’image dans la modale
        document.getElementById('logoModal').addEventListener('click', function (e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script> -->


</body>

</html>