<?php
require_once '../header.php';
require_once '../api/traker.php';
session_start();
if (!isset($_SESSION['id_client']) && !isset($_SESSION['id_pro'])) {
  $currentUrl = urlencode($_SERVER['REQUEST_URI']);
  header("Location: connexion?success_url={$currentUrl}");
  exit;
}

require '../db/dbconnect2.php';
require_once '../includes/monitor_init.php';
require_once '../includes/webhook.php';
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

function dechiffrer($texte_chiffre, $cle)
{
  return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}

function chiffrer($texte, $cle)
{
  return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}
function addVehiculeFromAPI($user_id, $immatriculation)
{
  global $db;

  $api_url = "https://api.apiplaqueimmatriculation.com/plaque?immatriculation="
    . urlencode($immatriculation)
    . "&token=142bb4769028&pays=FR";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $api_url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  $response = curl_exec($ch);

  if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    return ['success' => false, 'message' => "Erreur cURL : $error"];
  }

  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http_code !== 200) {
    return ['success' => false, 'message' => "Erreur API (HTTP $http_code)", 'response' => $response];
  }

  $vehicule_data = json_decode($response, true);
  if (!$vehicule_data || !isset($vehicule_data['data']['marque'])) {
    return ['success' => false, 'message' => "Plaque non trouvée", 'response' => $response];
  }

  $data = $vehicule_data['data'];

  // Vérif doublon
  $check = $db->prepare("SELECT id FROM vehicule_c WHERE immatriculation = :immat AND numero_client = :user_id");
  $check->execute([':immat' => $immatriculation, ':user_id' => $user_id]);
  if ($check->fetch()) {
    return ['success' => false, 'message' => "Ce véhicule est déjà enregistré"];
  }

  // Insertion
  $insert = $db->prepare("INSERT INTO vehicule_c 
        (numero_client, immatriculation, vin, version, boite_vitesse, code_moteur, marque, modele, annee, carburant, puissance, created_at) 
        VALUES 
        (:user_id, :immat, :vin, :version, :boite_vitesse, :code_moteur,:marque, :modele, :annee, :carburant, :puissance, NOW())");

  $result = $insert->execute([
    ':user_id' => $user_id,
    ':immat' => $immatriculation,
    ':marque' => $data['marque'] ?? '',
    ':vin' => $data['vin'] ?? '',
    ':version' => $data['version'] ?? '',
    ':boite_vitesse' => $data['boite_vitesse'] ?? '',
    ':code_moteur' => $data['code_moteur'] ?? '',
    ':modele' => $data['modele'] ?? '',
    ':annee' => $data['date1erCir_fr'] ?? '',
    ':carburant' => $data['energieNGC'] ?? '',
    ':puissance' => $data['puisFiscReelCH'] ?? ''
  ]);

  if ($result) {
    $id = $db->lastInsertId();
    return ['success' => true, 'message' => "Véhicule ajouté avec succès", 'data' => ['id' => $id] + $data];
  } else {
    return ['success' => false, 'message' => "Erreur lors de l'ajout"];
  }
}

// ===== Traitement AJAX =====
if (isset($_POST['action']) && $_POST['action'] === 'addVehicule') {
  header('Content-Type: application/json');
  if (!isset($_SESSION['id_client'])) {
    echo json_encode(['success' => false, 'message' => "Utilisateur non connecté"]);
    exit;
  }

  $immat = strtoupper(trim($_POST['immatriculation'] ?? ''));
  if (empty($immat)) {
    echo json_encode(['success' => false, 'message' => "Plaque requise"]);
    exit;
  }

  $result = addVehiculeFromAPI($_SESSION['id_client'], $immat);
  echo json_encode($result);
  exit;
}


$token = $_GET['v'] ?? null;
$numero_pro = $token ? dechiffrer($token, $cle_secrete) : ($_GET['numero_pro'] ?? null);
if (!$numero_pro)
  die("Prestataire non défini.");

$stmt = $db->prepare("SELECT timezone FROM entreprises WHERE numero_pro = ?");
$stmt->execute([$numero_pro]);
$timezone_pro = $stmt->fetchColumn() ?: 'America/Martinique';
$tz = new DateTimeZone($timezone_pro);

$now = new DateTime('now', $tz);
$dateSelected = $_GET['date'] ?? $now->format('Y-m-d');
$joursFr = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];
$dateObj = DateTime::createFromFormat('Y-m-d', $dateSelected, $tz);
$jourSemaine = strtolower($joursFr[(int) $dateObj->format('N') - 1]);

$horaires = $db->prepare("SELECT * FROM horaires WHERE numero_pro = ?");
$horaires->execute([$numero_pro]);
$horairesData = $horaires->fetch(PDO::FETCH_ASSOC);

$intervalMinutes = 30;
function genererCreneaux($debut, $fin, $date, $tz, $intervalMinutes)
{
  $liste = [];
  $start = DateTime::createFromFormat('Y-m-d H:i:s', "$date $debut", $tz);
  $end = DateTime::createFromFormat('Y-m-d H:i:s', "$date $fin", $tz);
  while ($start < $end) {
    $liste[] = $start->format('H:i');
    $start->modify("+{$intervalMinutes} minutes");
  }
  return $liste;
}

$creneaux = [];
$debut1 = $horairesData[$jourSemaine . '_debut'] ?? '';
$fin1 = $horairesData[$jourSemaine . '_fin'] ?? '';
$debut2 = $horairesData[$jourSemaine . '_debut2'] ?? '';
$fin2 = $horairesData[$jourSemaine . '_fin2'] ?? '';

if ((!empty($debut1) && !empty($fin1)) || (!empty($debut2) && !empty($fin2))) {
  if (!empty($debut1) && !empty($fin1)) {
    $creneaux = array_merge($creneaux, genererCreneaux($debut1, $fin1, $dateSelected, $tz, $intervalMinutes));
  }
  if (!empty($debut2) && !empty($fin2)) {
    $creneaux = array_merge($creneaux, genererCreneaux($debut2, $fin2, $dateSelected, $tz, $intervalMinutes));
  }
} else {
  $erreur = "Fermé ce jour-là";

}

$rdvs = $db->prepare("SELECT heure, duree FROM rdvs WHERE numero_pro = ? AND date = ?");
$rdvs->execute([$numero_pro, $dateSelected]);
$rdvsPris = $rdvs->fetchAll(PDO::FETCH_ASSOC);

$dureeDemandee = isset($_GET['duree']) ? (int) $_GET['duree'] : 30;

$creneauxDisponibles = array_filter($creneaux, function ($creneau) use ($rdvsPris, $dateSelected, $tz, $dureeDemandee, $now) {
  $heureCreneau = DateTime::createFromFormat('Y-m-d H:i', "$dateSelected $creneau", $tz);
  $finCreneau = (clone $heureCreneau)->modify("+{$dureeDemandee} minutes");
  if ($dateSelected === $now->format('Y-m-d') && $finCreneau <= $now)
    return false;
  foreach ($rdvsPris as $rdv) {
    $heureRdv = DateTime::createFromFormat('Y-m-d H:i', "$dateSelected {$rdv['heure']}", $tz);
    $finRdv = (clone $heureRdv)->modify("+{$rdv['duree']} minutes");
    if ($heureCreneau < $finRdv && $finCreneau > $heureRdv)
      return false;
  }
  return true;
});

$interval = 30;
$nbCreneauxNecessaires = $dureeDemandee / $interval;
$disponibles = [];
$total = count($creneaux);

for ($i = 0; $i <= $total - $nbCreneauxNecessaires; $i++) {
  $sequence = array_slice($creneaux, $i, $nbCreneauxNecessaires);
  $estDisponible = true;
  foreach ($sequence as $heure) {
    $dt = new DateTime("$dateSelected $heure", $tz);
    if (($dateSelected === $now->format('Y-m-d') && $dt < $now)) {
      $estDisponible = false;
      break;
    }
    foreach ($sequence as $heure) {
      $dtDebut = new DateTime("$dateSelected $heure", $tz);
      $dtFin = (clone $dtDebut)->modify("+{$interval} minutes");

      if ($dateSelected === $now->format('Y-m-d') && $dtDebut < $now) {
        $estDisponible = false;
        break;
      }

      foreach ($rdvsPris as $rdv) {
        $rdvDebut = new DateTime("$dateSelected {$rdv['heure']}", $tz);
        $rdvFin = (clone $rdvDebut)->modify("+{$rdv['duree']} minutes");

        // Vérifie le chevauchement
        if ($dtDebut < $rdvFin && $dtFin > $rdvDebut) {
          $estDisponible = false;
          break 2;
        }
      }
    }
  }
  if ($estDisponible)
    $disponibles[] = $sequence[0];
}
$stmt = $db->prepare("SELECT id, marque, modele, immatriculation 
                      FROM vehicule_c
                      WHERE numero_client = ?");
$stmt->execute([$_SESSION['id_client']]);
$vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);
$datesDisponibles = [];
for ($i = 0; $i < 900; $i++) {
    $date = new DateTime("now", $tz);
    $date->modify("+$i day");
    $jourSemaineLoop = $joursFr[(int)$date->format('N') - 1];

    $debut1 = $horairesData[$jourSemaineLoop . '_debut'] ?? '';
    $fin1   = $horairesData[$jourSemaineLoop . '_fin'] ?? '';
    $debut2 = $horairesData[$jourSemaineLoop . '_debut2'] ?? '';
    $fin2   = $horairesData[$jourSemaineLoop . '_fin2'] ?? '';

    if ((!empty($debut1) && !empty($fin1)) || (!empty($debut2) && !empty($fin2))) {
        $datesDisponibles[] = $date->format('Y-m-d');
    }
}


?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>Agenda du <?= date('d/m/Y', strtotime($dateSelected)) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>

<body class="bg-gray-50 text-gray-800">
  <?php include('../includes/dropdown.php'); ?>
  <!-- Titre -->
<h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-center mb-6 sm:mb-8 text-green-700 leading-tight">
  Agenda du <?= ucfirst($jourSemaine) ?> <?= $dateObj->format('d/m/Y') ?>
</h1>

  <!-- Sélecteur de date -->
  <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row justify-center items-center gap-3 w-full">
    <label for="datePicker" class="font-medium text-gray-700 text-center sm:text-left">Choisissez une date :</label>
    <input type="text" id="datePicker" name="datePicker"
      class="border border-green-400 rounded-lg px-4 py-2 shadow-sm focus:ring-2 focus:ring-green-500 w-full max-w-xs sm:max-w-sm" />

  </div>

  <!-- Créneaux disponibles -->
  <div class="max-w-4xl mx-auto flex flex-wrap gap-2 sm:gap-3 justify-center">
    <?php if (!empty($disponibles)): ?>
      <?php foreach ($disponibles as $heure): ?>
        <div
          class="btnResa flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-sm sm:text-base font-medium shadow border bg-green-100 text-green-800 hover:bg-green-200 cursor-pointer border-green-300 transition w-full sm:w-auto justify-center"
          data-date="<?= htmlspecialchars($dateSelected) ?>" data-heure="<?= $heure ?>">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span><?= $heure ?></span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <?php if (isset($erreur)) { ?>
        <p class="text-center text-red-700 text-base sm:text-lg w-full mt-12 "><?= $erreur ?></p>
      <?php } ?>
      <p class="text-center text-gray-400 text-base sm:text-lg w-full mt-12">Aucun créneau disponible pour cette durée.
      </p>
    <?php endif; ?>
  </div>

  <!-- Modale -->
  <div id="modal"
    class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-2 sm:p-4 overflow-auto">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xs sm:max-w-md md:max-w-lg p-4 sm:p-6 relative">

      <!-- Bouton fermer -->
      <button type="button" class="btn-close-modal absolute top-4 right-4 text-gray-400 hover:text-gray-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <h3 class="text-lg sm:text-2xl font-bold text-green-700 mb-4">Réserver un créneau</h3>

      <div class="mb-4">
        <label for="vehicule_id" class="block font-medium mb-2 text-gray-700">Choisissez votre véhicule :</label>

        <?php if (empty($vehicules)): ?>
          <div id="vehicule-form" class="p-4 border rounded-lg bg-yellow-50 text-yellow-800">
            <p class="mb-2">Aucun véhicule trouvé. Ajoutez-en un :</p>
            <div class="flex space-x-2">
              <input type="text" id="immat-input" placeholder="Immatriculation (ex: AB123CD)"
                class="flex-1 border rounded px-3 py-2" />
              <button type="button" id="add-vehicule-btn"
                class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                ➕ Ajouter
              </button>
            </div>
            <p id="vehicule-message" class="mt-2 text-sm"></p>
          </div>

          <select name="vehicule_id" id="vehicule_id" class="border rounded-lg px-3 py-2 w-full hidden" required></select>

        <?php else: ?>
          <select name="vehicule_id" id="vehicule_id" class="border rounded-lg px-3 py-2 w-full" required>
            <option value="">-- Sélectionnez votre véhicule --</option>
            <?php foreach ($vehicules as $v): ?>
              <option value="<?= $v['id'] ?>">
                <?= htmlspecialchars($v['marque'] . ' ' . $v['modele'] . ' (' . $v['immatriculation'] . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>

      <form id="formResa" class="space-y-4 sm:space-y-5">
        <input type="hidden" name="date" id="resa_date">
        <input type="hidden" name="heure" id="resa_heure">
        <input type="hidden" name="id_client" value="<?= htmlspecialchars($_SESSION['id_client']) ?>">
        <input type="hidden" name="numero_pro" value="<?= htmlspecialchars($numero_pro) ?>">
        <input type="hidden" name="immatriculation" id="immatriculation">

        <div>
          <label class="block font-medium mb-2 text-gray-700">Choisissez vos prestations :</label>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 max-h-60 overflow-y-auto pr-1">
            <?php
            $stmt = $db->prepare("SELECT id, nom, prix FROM prestations WHERE numero_pro = ?");
            $stmt->execute([$numero_pro]);
            foreach ($stmt as $i => $p):
              ?>
              <label class="flex items-center gap-3 px-3 py-2 bg-white border rounded-lg text-sm">
                <input type="checkbox" name="prestation_id[]" value="<?= $p['id'] ?>"
                  class="form-checkbox h-5 w-5 text-green-500">
                <span><?= htmlspecialchars($p['nom']) ?> – <?= number_format($p['prix'], 2, ',', ' ') ?> € HT</span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-end gap-3 pt-2">
          <button type="button"
            class="btn-close-modal w-full sm:w-auto px-4 py-2 bg-gray-200 rounded-lg font-medium text-gray-700 hover:bg-gray-300">Annuler</button>
          <button type="button"
            class="btn-submit-form w-full sm:w-auto px-4 py-2 bg-green-600 rounded-lg font-medium text-white hover:bg-green-700">Réserver</button>
        </div>

        <div id="result" class="text-sm text-red-600 mt-2"></div>
      </form>
    </div>
  </div>

  <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', function () {
      const modal = document.getElementById('modal');
      const form = document.getElementById('formResa');
      const vehiculeSelect = document.getElementById('vehicule_id');
      const immatriculationInput = document.getElementById('immatriculation');
      const vehiculeError = document.getElementById('vehicule-error');

      function openModal(date, heure) {
        document.getElementById('resa_date').value = date;
        document.getElementById('resa_heure').value = heure;
        modal.classList.remove('hidden');

        // Mettre à jour immatriculation selon véhicule sélectionné par défaut
        const option = vehiculeSelect.selectedOptions[0];
        immatriculationInput.value = option ? option.dataset.immat : '';
      }
      window.openModal = openModal;

      function closeModal() {
        modal.classList.add('hidden');
      }
      window.closeModal = closeModal;

      // Mettre à jour l'immatriculation quand le client choisit un véhicule
      vehiculeSelect.addEventListener('change', function () {
        const option = vehiculeSelect.selectedOptions[0];
        immatriculationInput.value = option ? option.dataset.immat : '';
        vehiculeError.classList.add('hidden');
      });

      // Clics sur créneaux
      document.querySelectorAll('[data-date][data-heure]').forEach(btn => {
        btn.addEventListener('click', function () {
          openModal(this.dataset.date, this.dataset.heure);
        });
      });

      // Fermer modale
      document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', closeModal);
      });

      // Submit formulaire
      document.querySelector('.btn-submit-form').addEventListener('click', function () {
        if (!vehiculeSelect.value) {
          vehiculeError.classList.remove('hidden');
          vehiculeSelect.focus();
          return;
        }

        const data = new FormData(form);
        fetch('reserver.php', {
          method: 'POST',
          body: data
        })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              window.location.href = data.redirect_url;
            } else {
              document.getElementById('result').innerText = data.message;
            }
          })
          .catch(() => {
            document.getElementById('result').innerText = "Erreur de soumission.";
          });
      });

      // Changement de date
      const numeroPro = '<?= $numero_pro ?>';
      const datesDisponibles = <?= json_encode($datesDisponibles) ?>; // <-- tableau de dates

      flatpickr("#datePicker", {
        dateFormat: "Y-m-d",
        enable: datesDisponibles, // tableau correct
        defaultDate: "<?= $dateSelected ?>",
        onChange: function (selectedDates, dateStr) {
          const url = new URL(window.location.href);
          url.searchParams.set('date', dateStr);
          url.searchParams.set('numero_pro', numeroPro);
          window.location.href = url.toString();
        }
      });
    });

  </script>
  <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener("DOMContentLoaded", () => {
      const btn = document.getElementById("add-vehicule-btn");
      const input = document.getElementById("immat-input");
      const msg = document.getElementById("vehicule-message");
      const select = document.getElementById("vehicule_id");

      if (btn) {
        btn.addEventListener("click", async () => {
          const immat = input.value.trim();
          if (!immat) {
            msg.textContent = "Veuillez entrer une immatriculation.";
            msg.className = "mt-2 text-sm text-red-600";
            return;
          }

          msg.textContent = "Ajout en cours...";
          msg.className = "mt-2 text-sm text-blue-600";

          try {
            const response = await fetch("", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: "action=addVehicule&immatriculation=" + encodeURIComponent(immat)
            });

            const data = await response.json();

            if (data.success) {
              msg.textContent = data.message;
              msg.className = "mt-2 text-sm text-green-600";

              const opt = document.createElement("option");
              opt.value = data.data.id;
              opt.textContent = data.data.marque + " " + data.data.modele + " (" + immat + ")";
              opt.selected = true;

              select.appendChild(opt);
              select.classList.remove("hidden");

              document.getElementById("vehicule-form").classList.add("hidden");
            } else {
              msg.textContent = data.message;
              msg.className = "mt-2 text-sm text-red-600";
            }
          } catch (err) {
            msg.textContent = "Erreur AJAX : " + err.message;
            msg.className = "mt-2 text-sm text-red-600";
          }
        });
      }
    });
  </script>




</body>

</html>