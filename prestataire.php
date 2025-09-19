<?php
session_start();
require 'db/dbconnect2.php';

if (!isset($_SESSION['id_pro'])) {
    die("Vous devez être connecté.");
}

$numero_client = $_SESSION['id_pro'];
$jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

$horaires = [];
$errors = [];
$successMessage = '';
$mode = 'personnalise'; // valeur par défaut

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'personnalise';

    $horaires_saisie = [];

    if ($mode === 'semaine') {
        $ferme_semaine = isset($_POST['semaine_ferme']) && $_POST['semaine_ferme'] === 'on';
        $semaine_debut = $ferme_semaine ? null : ($_POST['semaine_debut'] ?? null);
        $semaine_fin = $ferme_semaine ? null : ($_POST['semaine_fin'] ?? null);

        foreach (['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'] as $jour) {
            $horaires_saisie["{$jour}_debut"] = $semaine_debut;
            $horaires_saisie["{$jour}_fin"] = $semaine_fin;
        }
        foreach (['samedi', 'dimanche'] as $jour) {
            $horaires_saisie["{$jour}_debut"] = null;
            $horaires_saisie["{$jour}_fin"] = null;
        }
    } elseif ($mode === 'weekend') {
        $ferme_weekend = isset($_POST['weekend_ferme']) && $_POST['weekend_ferme'] === 'on';
        $weekend_debut = $ferme_weekend ? null : ($_POST['weekend_debut'] ?? null);
        $weekend_fin = $ferme_weekend ? null : ($_POST['weekend_fin'] ?? null);

        foreach (['samedi', 'dimanche'] as $jour) {
            $horaires_saisie["{$jour}_debut"] = $weekend_debut;
            $horaires_saisie["{$jour}_fin"] = $weekend_fin;
        }
        foreach (['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'] as $jour) {
            $horaires_saisie["{$jour}_debut"] = null;
            $horaires_saisie["{$jour}_fin"] = null;
        }
    } else { // personnalisé
        foreach ($jours as $jour) {
            $ferme = isset($_POST["{$jour}_ferme"]) && $_POST["{$jour}_ferme"] === 'on';
            if ($ferme) {
                $horaires_saisie["{$jour}_debut"] = null;
                $horaires_saisie["{$jour}_fin"] = null;
            } else {
                $horaires_saisie["{$jour}_debut"] = $_POST["{$jour}_debut"] ?? null;
                $horaires_saisie["{$jour}_fin"] = $_POST["{$jour}_fin"] ?? null;
            }
        }
    }

    // Validation simple : si pas fermé, debut < fin
    foreach ($jours as $jour) {
        $debut = $horaires_saisie["{$jour}_debut"];
        $fin = $horaires_saisie["{$jour}_fin"];
        if (!empty($debut) && !empty($fin) && $debut >= $fin) {
            $errors[] = "Pour $jour, l'heure de début doit être avant l'heure de fin.";
        }
    }

    if (empty($errors)) {
        // Vérifier si le prestataire a déjà des horaires
        $check = $db->prepare("SELECT * FROM horaires WHERE numero_pro = ?");
        $check->execute([$numero_client]);

if ($check->rowCount() > 0) {
    // Récupérer les anciennes valeurs
    $ancienHoraires = $check->fetch(PDO::FETCH_ASSOC);

    // Fusionner : si valeur nouvelle est null (ex : pas remplie), garder ancienne
    foreach ($jours as $jour) {
        if ($horaires_saisie["{$jour}_debut"] === null) {
            $horaires_saisie["{$jour}_debut"] = $ancienHoraires["{$jour}_debut"];
        }
        if ($horaires_saisie["{$jour}_fin"] === null) {
            $horaires_saisie["{$jour}_fin"] = $ancienHoraires["{$jour}_fin"];
        }
    }

    // Puis exécuter UPDATE avec $horaires_saisie fusionné
    $sets = [];
    $params = [];
    foreach ($jours as $jour) {
        $sets[] = "{$jour}_debut = ?";
        $sets[] = "{$jour}_fin = ?";
        $params[] = $horaires_saisie["{$jour}_debut"];
        $params[] = $horaires_saisie["{$jour}_fin"];
    }
    $params[] = $numero_client;

    $sql = "UPDATE horaires SET " . implode(", ", $sets) . " WHERE numero_pro = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $successMessage = "✅ Horaires mis à jour.";
}
        $horaires = $horaires_saisie;
    } else {
        $horaires = $horaires_saisie;
    }
} else {
    $stmt = $db->prepare("SELECT * FROM horaires WHERE numero_pro = ?");
    $stmt->execute([$numero_client]);
    if ($stmt->rowCount() > 0) {
        $horaires = $stmt->fetch(PDO::FETCH_ASSOC);
    }
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
<title>Horaires Prestataire</title>
<style>
    .jour { margin-bottom: 8px; }
    #formulaire-horaires > div { margin-top: 10px; }
    .error { color: red; font-size: 0.9em; margin-top: 3px; }
    .success { color: green; font-weight: bold; margin-bottom: 15px; }
    label { display: inline-block; margin-right: 10px; }
</style>
</head>
<body>

<h2>Horaires d'ouverture</h2>

<form method="POST" id="formHoraires" novalidate>
    <label>
        <input type="radio" name="mode" value="semaine" <?= ($mode === 'semaine') ? 'checked' : '' ?>>
        Même horaire du lundi au vendredi
    </label><br>

    <label>
        <input type="radio" name="mode" value="weekend" <?= ($mode === 'weekend') ? 'checked' : '' ?>>
        Même horaire week-end
    </label><br>

    <label>
        <input type="radio" name="mode" value="personnalise" <?= ($mode === 'personnalise') ? 'checked' : '' ?>>
        Horaires personnalisés
    </label>

    <div id="formulaire-horaires"></div>

    <button type="submit" style="margin-top:15px;">Enregistrer</button>

    <div id="message">
        <?php
        if ($successMessage) {
            echo '<p class="success">' . htmlspecialchars($successMessage) . '</p>';
        }
        if (!empty($errors)) {
            foreach ($errors as $err) {
                echo '<p class="error">' . htmlspecialchars($err) . '</p>';
            }
        }
        ?>
    </div>
</form>

<script>
const jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const horaires = <?= json_encode($horaires, JSON_UNESCAPED_UNICODE) ?> || {};
let mode = '<?= $mode ?>';

function getHoraireValue(name) {
    return horaires[name] ?? '';
}

function isFerme(name) {
    // Considère fermé si début ET fin sont null ou vide
    return !horaires[`${name}_debut`] && !horaires[`${name}_fin`];
}

function champHoraire(jour) {
    const fermeChecked = isFerme(jour) ? 'checked' : '';
    const debutVal = getHoraireValue(jour + '_debut');
    const finVal = getHoraireValue(jour + '_fin');

    return `
        <div class="jour">
            <label>
                <input type="checkbox" class="ferme-checkbox" data-jour="${jour}" name="${jour}_ferme" ${fermeChecked}>
                Fermé
            </label>
            <label>${jour.charAt(0).toUpperCase() + jour.slice(1)} :</label>
            de <input type="time" name="${jour}_debut" value="${debutVal}" ${fermeChecked ? 'disabled' : ''}>
            à <input type="time" name="${jour}_fin" value="${finVal}" ${fermeChecked ? 'disabled' : ''}>
        </div>
    `;
}

function afficherHoraires() {
    const container = document.getElementById('formulaire-horaires');
    const selectedMode = document.querySelector('input[name="mode"]:checked').value;
    container.innerHTML = '';
    mode = selectedMode;

    if (selectedMode === 'semaine') {
        const fermeChecked = (horaires['semaine_ferme'] === 'on') ? 'checked' : '';
        const debut = horaires['lundi_debut'] || '';
        const fin = horaires['lundi_fin'] || '';

        container.innerHTML = `
            <div class="jour">
                <label><input type="checkbox" id="semaine_ferme" name="semaine_ferme" ${fermeChecked}> Fermé</label>
                <label>Du lundi au vendredi :</label>
                de <input type="time" name="semaine_debut" value="${debut}" ${fermeChecked ? 'disabled' : ''} required>
                à <input type="time" name="semaine_fin" value="${fin}" ${fermeChecked ? 'disabled' : ''} required>
            </div>
        `;
    } else if (selectedMode === 'weekend') {
        const fermeChecked = (horaires['weekend_ferme'] === 'on') ? 'checked' : '';
        const debut = horaires['samedi_debut'] || '';
        const fin = horaires['samedi_fin'] || '';

        container.innerHTML = `
            <div class="jour">
                <label><input type="checkbox" id="weekend_ferme" name="weekend_ferme" ${fermeChecked}> Fermé</label>
                <label>Samedi & Dimanche :</label>
                de <input type="time" name="weekend_debut" value="${debut}" ${fermeChecked ? 'disabled' : ''} required>
                à <input type="time" name="weekend_fin" value="${fin}" ${fermeChecked ? 'disabled' : ''} required>
            </div>
        `;
    } else {
        // personnalisé : afficher tous les jours
        for (const jour of jours) {
            container.innerHTML += champHoraire(jour);
        }
    }

    // Ajouter gestion des checkboxes "fermé" pour activer/désactiver inputs
    document.querySelectorAll('.ferme-checkbox').forEach(cb => {
        cb.addEventListener('change', (e) => {
            const jour = e.target.dataset.jour;
            const checked = e.target.checked;
            const inputs = e.target.closest('.jour').querySelectorAll('input[type="time"]');
            inputs.forEach(input => input.disabled = checked);
        });
    });

    // checkbox pour semaine et weekend
    const semaineFermeCheckbox = document.getElementById('semaine_ferme');
    if (semaineFermeCheckbox) {
        semaineFermeCheckbox.addEventListener('change', () => {
            const inputs = document.querySelectorAll('input[name="semaine_debut"], input[name="semaine_fin"]');
            inputs.forEach(input => input.disabled = semaineFermeCheckbox.checked);
        });
    }
    const weekendFermeCheckbox = document.getElementById('weekend_ferme');
    if (weekendFermeCheckbox) {
        weekendFermeCheckbox.addEventListener('change', () => {
            const inputs = document.querySelectorAll('input[name="weekend_debut"], input[name="weekend_fin"]');
            inputs.forEach(input => input.disabled = weekendFermeCheckbox.checked);
        });
    }
}

// Initialisation
afficherHoraires();
document.querySelectorAll('input[name="mode"]').forEach(radio => {
    radio.addEventListener('change', afficherHoraires);
});
</script>

</body>
</html>
