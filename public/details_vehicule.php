<?php
require_once '../header.php';
require '../vendor/autoload.php';
require_once '../api/traker.php';
require_once '../includes/webhook.php';
use Dompdf\Dompdf;
use Stancer\Config;
use Stancer\Card;
use Stancer\Payment;
session_start();
if (!isset($_SESSION['id_client'])) {
  header('Location: /');
  exit;
}

require '../db/dbconnect2.php';

function genererChaineAleatoire($longueur = 50)
{
  $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $resultat = '';
  for ($i = 0; $i < $longueur; $i++) {
    $resultat .= $caracteres[random_int(0, strlen($caracteres) - 1)];
  }
  return $resultat;
}

// Vérifier le token
if (empty($_GET['j'])) {
  die("Lien invalide.");
}
$token = $_GET['j'];

// Récupérer les informations du véhicule
$stmt = $db->prepare("SELECT * FROM prestations_vehicule WHERE token = ?");
$stmt->execute([$token]);
$vehicule = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vehicule) {
  die("Véhicule introuvable.");
}

// Récupérer les photos du véhicule
$stmt = $db->prepare("SELECT * FROM photos_prestations_vehicule WHERE id_prestation = ?");
$stmt->execute([$vehicule['id']]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le numéro_pro de l'entreprise liée au véhicule
$numero_pro = $vehicule['numero_pro'] ?? null;
if (!$numero_pro) {
  die("Entreprise non définie pour ce véhicule.");
}

$stmt = $db->prepare("SELECT timezone FROM entreprises WHERE numero_pro = ?");
$stmt->execute([$numero_pro]);
$timezone_pro = $stmt->fetchColumn() ?: 'America/Martinique';
$tz = new DateTimeZone($timezone_pro);

$now = new DateTime('now', $tz);
$dateSelected = $_GET['date'] ?? $now->format('Y-m-d');
$joursFr = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];
$jourSemaine = strtolower($joursFr[(int) date('N', strtotime($dateSelected)) - 1]);

$horaires = $db->prepare("SELECT * FROM horaires WHERE numero_pro = ?");
$horaires->execute([$numero_pro]);
$horairesData = $horaires->fetch(PDO::FETCH_ASSOC);

$intervalMinutes = 30;

function genererCreneaux($debut, $fin, $date, $tz, $intervalMinutes)
{
  $liste = [];
  $debut_trim = substr($debut, 0, 5);
  $fin_trim = substr($fin, 0, 5);

  $start = DateTime::createFromFormat('Y-m-d H:i', "$date $debut_trim", $tz);
  $end = DateTime::createFromFormat('Y-m-d H:i', "$date $fin_trim", $tz);
  if (!$start || !$end) {
    return [];
  }
  while ($start < $end) {
    $liste[] = $start->format('H:i');
    $start->modify("+{$intervalMinutes} minutes");
  }
  return $liste;
}

function verifierJourOuvert($date, $horairesData, $joursFr)
{
  $jour = strtolower($joursFr[(int) date('N', strtotime($date)) - 1]);
  $debut1 = $horairesData[$jour . '_debut'] ?? '';
  $fin1 = $horairesData[$jour . '_fin'] ?? '';
  $debut2 = $horairesData[$jour . '_debut2'] ?? '';
  $fin2 = $horairesData[$jour . '_fin2'] ?? '';

  return (!empty($debut1) && !empty($fin1)) || (!empty($debut2) && !empty($fin2));
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
  echo '<script src="https://cdn.tailwindcss.com"></script>
  <div class="bg-red-100 text-red-700 px-4 py-2 rounded-lg font-semibold text-center shadow-md">
          Fermé ce jour-là
        </div>';
  exit;
}

$error = '';
$success = '';

// Traitement du paiement et réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
  $dateDepart = $_POST['date_depart'] ?? '';
  $heureDepart = $_POST['creneau'] ?? '';
  $dateRetour = $_POST['date_retour'] ?? '';
  $card_number = $_POST['numero_carte'] ?? '';
  $expire_m = $_POST['expiration_mois'] ?? '';
  $expire_y = $_POST['expiration_annee'] ?? '';
  $cvc = $_POST['cvc'] ?? '';
  $cardholder_name = $_POST['cardname'] ?? '';
      $taxes = 500;
      $dtDepart = DateTime::createFromFormat('Y-m-d H:i', "$dateDepart $heureDepart", $tz);
      $dtRetour = DateTime::createFromFormat('Y-m-d H:i', "$dateRetour $heureDepart", $tz);
      $interval = $dtDepart->diff($dtRetour);
      $dureeJours = $interval->days;
      $prix_unitaire = $vehicule['prix_j'] ?? $vehicule['prix'] ?? 0;
      $prix_total = $dureeJours * $prix_unitaire + ($taxes/ 100);
  // Validation des données de paiement (simulation)
  if (empty($card_number) || empty($expire_m) || empty($expire_y) || empty($cvc) || empty($cardholder_name)) {
    $error = "Veuillez remplir toutes les informations de paiement.";
  } elseif (strlen($card_number) < 16) {
    $error = "Numéro de carte invalide.";
  } elseif (strlen($cvc) < 3) {
    $error = "Code CVC invalide.";
  } else {
     
    $amount = (int) ($prix_total * 100);
    $api_secret = 'sprod_wzX1s3orkjfXwUoHuSbXgBFC';
    $api_key = 'pprod_fZgZcC0HE7nPWDM0kceHGLmi';
    $config = Stancer\Config::init([$api_key, $api_secret]);
    $config->setMode(Stancer\Config::TEST_MODE);


    $card = new Card([
        'number' => preg_replace('/\D/', '', $card_number),
        'exp_month' => (int) $expire_m,
        'exp_year' => (int) ('20' . $expire_y),
        'cvc' => $cvc,
        'name' => $cardholder_name,
    ]);

    $payment = new Payment([
        'amount' => $amount,
        'currency' => 'EUR',
        'card' => $card,
    ]);

    try {
        $payment->send();

        $status = $payment->getStatus()->value;
        $transactionId = $payment->getId();
        $currency = $payment->getCurrency()->value;
        $dateStr = date('Y-m-d H:i:s');

        $lastname = $card->getName() ?? 'Non défini';
        $cardLast4 = $card->getLast4();
        $cardBrand = $card->getBrand();

        // Enregistrer la transaction une seule fois
        $stmt = $db->prepare("INSERT INTO transactions (
            transaction_id, numero_pro, numero_client, client_firstname, client_lastname, client_email,
            amount_cents, currency, transaction_date, status, card_last4, card_brand
        ) VALUES (
            :transaction_id, :numero_pro, :numero_client, :client_firstname, :client_lastname, :client_email,
            :amount_cents, :currency, :transaction_date, :status, :card_last4, :card_brand
        )");

        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':numero_pro' => $numero_pro,
            ':numero_client' => $_SESSION['id_client'],
            ':client_firstname' => null,
            ':client_lastname' => $lastname,
            ':client_email' => null,
            ':amount_cents' => $amount,
            ':currency' => $currency,
            ':transaction_date' => $dateStr,
            ':status' => $status,
            ':card_last4' => $cardLast4,
            ':card_brand' => $cardBrand,
        ]);
        if ($status === 'authorized' || $status === 'captured') {

      // Insérer la réservation après paiement validé
      $insert = $db->prepare("INSERT INTO locations (numero_pro, id_client, date_depart, heure_depart, date_recuperation, plaque, marque, modele, annee, kilometrage, date_mise_en_circulation, prix_total, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmee')");
      $insert->execute([
        $numero_pro,
        $_SESSION['id_client'],
        $dateDepart,
        $heureDepart,
        $dateRetour,
        $vehicule['immatriculation'],
        $vehicule['marque'],
        $vehicule['model'],
        $vehicule['model_annee'],
        $vehicule['kilometrage'],
        $vehicule['date_circulation'],
        $prix_total
      ]);

      $success = "Paiement validé et réservation confirmée du " . date('d/m/Y', strtotime($dateDepart)) . " au " . date('d/m/Y', strtotime($dateRetour)) . " à " . $heureDepart . ". Durée : $dureeJours jour(s). Prix : " . number_format($prix_total, 2) . " €";
      }
      
    
     else {
      $error = "Le paiement a échoué. Veuillez réessayer.";
    }
  } catch (Exception $e) {
        echo 'Erreur lors du paiement';
      }
}
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Détails du véhicule - <?= htmlspecialchars($vehicule['marque']) ?> -
    <?= htmlspecialchars($vehicule['model']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
  </style>
</head>
<?php include('../includes/dropdown.php'); ?>

<body class="bg-gray-100 min-h-screen">

  <div class="max-w-6xl mx-auto py-10 px-4">
    <div class="bg-white shadow-xl rounded-2xl p-6">

      <h1 class="text-3xl font-bold text-gray-900 mb-2">
        <?= htmlspecialchars($vehicule['marque']) ?> - <?= htmlspecialchars($vehicule['model']) ?>
      </h1>
      <p class="text-gray-500 mb-6"><?= htmlspecialchars($vehicule['model_annee'] ?? '-') ?></p>

      <?php if ($photos): ?>
        <div class="flex justify-center mb-6">
          <div class="relative w-full max-w-md">
            <div class="overflow-hidden rounded-xl shadow-lg relative">
              <div id="carousel" class="flex transition-transform duration-500">
                <?php foreach ($photos as $photo): ?>
                  <img src="<?= htmlspecialchars($photo['chemin']) ?>" alt="Photo véhicule"
                    class="w-full h-64 md:h-80 object-cover flex-shrink-0" />
                <?php endforeach; ?>
              </div>
              <button id="prevBtn"
                class="absolute top-1/2 left-3 -translate-y-1/2 bg-black/60 text-white p-2 rounded-full hover:bg-black transition">❮</button>
              <button id="nextBtn"
                class="absolute top-1/2 right-3 -translate-y-1/2 bg-black/60 text-white p-2 rounded-full hover:bg-black transition">❯</button>
            </div>
          </div>
        </div>
      <?php else: ?>
        <p class="text-gray-500 mb-6">Aucune photo disponible.</p>
      <?php endif; ?>

      <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-8 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-3">
          <span class="material-symbols-outlined text-green-600 mr-2">description</span>
          Description du véhicule
        </h2>
        <p class="text-gray-700 leading-relaxed">
          <?= nl2br(htmlspecialchars($vehicule['description'] ?? 'Aucune description disponible.')) ?></p>
      </div>

      <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($error) ?>
        </div>
      <?php elseif ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
          <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 shadow-sm mb-6" id="reservationForm">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
          <span class="material-symbols-outlined text-green-600 mr-2">event</span>
          Réservation du véhicule
        </h2>

        <!-- Date de départ -->
        <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row justify-center items-center gap-3 w-full max-w-md mx-auto">
          <label for="date_depart" class="font-medium text-gray-700 text-center sm:text-left">Date de départ :</label>
          <input type="date" id="date_depart" name="date_depart" value="<?= htmlspecialchars($dateSelected) ?>"
            min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
            class="border border-green-400 rounded-lg px-4 py-2 shadow-sm focus:ring-2 focus:ring-green-500 w-full max-w-xs sm:max-w-sm" />
        </div>

        <!-- Créneaux disponibles -->
        <div class="mt-4 mb-6">
          <p class="font-semibold mb-2">Heure de départ :</p>
          <?php if (!empty($creneaux)): ?>
            <div class="flex flex-wrap gap-2 justify-center">
              <?php foreach ($creneaux as $heure): ?>
                <button type="button"
                  class="creneau-btn px-4 py-2 rounded border border-green-500 text-green-700 hover:bg-green-500 hover:text-white transition"
                  data-heure="<?= htmlspecialchars($heure) ?>"><?= htmlspecialchars($heure) ?></button>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-gray-500">Aucun créneau disponible pour cette date.</p>
          <?php endif; ?>
        </div>

        <!-- Date de retour -->
        <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row justify-center items-center gap-3 w-full max-w-md mx-auto">
          <label for="date_retour" class="font-medium text-gray-700 text-center sm:text-left">Date de retour :</label>
          <input type="date" id="date_retour" name="date_retour"
            min="<?= date('Y-m-d', strtotime($dateSelected . ' +1 day')) ?>"
            max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
            class="border border-green-400 rounded-lg px-4 py-2 shadow-sm focus:ring-2 focus:ring-green-500 w-full max-w-xs sm:max-w-sm" />
        </div>

        <input type="hidden" id="creneauInput" />

        <button type="button" id="openPaymentModal"
          class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg shadow-md transition-colors"
          disabled>
          Procéder au paiement
        </button>
      </div>
    </div>
  </div>

  <!-- Modal de paiement -->
  <div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
      <h3 class="text-xl font-bold mb-4">Finaliser le paiement</h3>
      
      <div id="reservationSummary" class="bg-gray-50 p-4 rounded mb-4">
        <!-- Résumé de réservation sera injecté ici -->
      </div>

      <form method="POST" id="paymentForm">
        <input type="hidden" name="process_payment" value="1">
        <input type="hidden" name="date_depart" id="modal_date_depart">
        <input type="hidden" name="creneau" id="modal_creneau">
        <input type="hidden" name="date_retour" id="modal_date_retour">

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de carte</label>
          <input type="text" name="numero_carte" maxlength="19" placeholder="1234 5678 9012 3456"
            class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-green-500" required>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mois</label>
            <select name="expiration_mois" class="w-full border border-gray-300 rounded px-3 py-2" required>
              <option value="">MM</option>
              <?php for($i = 1; $i <= 12; $i++): ?>
                <option value="<?= sprintf('%02d', $i) ?>"><?= sprintf('%02d', $i) ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Année</label>
            <select name="expiration_annee" class="w-full border border-gray-300 rounded px-3 py-2" required>
              <option value="">AA</option>
              <?php for($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                <option value="<?= substr($i, -2) ?>"><?= substr($i, -2) ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">CVC</label>
            <input type="text" name="cvc" maxlength="4" placeholder="123"
              class="w-full border border-gray-300 rounded px-3 py-2" required>
          </div>
        </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom du titulaire</label>
                <input type="text" name="cardname" placeholder="Jean Dupont"
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

        <div class="flex gap-4">
          <button type="button" id="cancelPayment"
            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded">
            Annuler
          </button>
          <button type="submit"
            class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded">
            Payer maintenant
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const creneauButtons = document.querySelectorAll('.creneau-btn');
    const creneauInput = document.getElementById('creneauInput');
    const openPaymentBtn = document.getElementById('openPaymentModal');
    const paymentModal = document.getElementById('paymentModal');
    const cancelPaymentBtn = document.getElementById('cancelPayment');

    function clearSelection() {
      creneauButtons.forEach(c => c.classList.remove('bg-green-600', 'text-white'));
      openPaymentBtn.disabled = true;
      creneauInput.value = '';
    }

    function checkFormValidity() {
      const dateDepart = document.getElementById('date_depart').value;
      const dateRetour = document.getElementById('date_retour').value;
      const creneau = creneauInput.value;

      if (dateDepart && dateRetour && creneau) {
        openPaymentBtn.disabled = false;
      } else {
        openPaymentBtn.disabled = true;
      }
    }

    creneauButtons.forEach(c => {
      c.addEventListener('click', () => {
        clearSelection();
        c.classList.add('bg-green-600', 'text-white');
        creneauInput.value = c.getAttribute('data-heure');
        checkFormValidity();
      });
    });

    document.getElementById('date_depart').addEventListener('change', function () {
      const date = this.value;
      const url = new URL(window.location.href);
      url.searchParams.set('date', date);
      window.location.href = url.toString();
    });

    document.getElementById('date_retour').addEventListener('change', checkFormValidity);

    // Ouvrir le modal de paiement
    openPaymentBtn.addEventListener('click', () => {
      const dateDepart = document.getElementById('date_depart').value;
      const dateRetour = document.getElementById('date_retour').value;
      const creneau = creneauInput.value;
      
      // Calculer durée et prix
      const dtDepart = new Date(dateDepart);
      const dtRetour = new Date(dateRetour);
      const dureeJours = Math.ceil((dtRetour - dtDepart) / (1000 * 60 * 60 * 24));
      const prixJour = <?= $vehicule['prix_j'] ?? $vehicule['prix'] ?? 0 ?>;
      const prix = dureeJours * prixJour;
      const taxes = 5;
      const prixTotal = prix + taxes;

      // Remplir le résumé
      document.getElementById('reservationSummary').innerHTML = `
        <h4 class="font-semibold mb-2">Résumé de la réservation</h4>
        <p><strong>Véhicule:</strong> <?= htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['model']) ?></p>
        <p><strong>Du:</strong> ${new Date(dateDepart).toLocaleDateString('fr-FR')} à ${creneau}</p>
        <p><strong>Au:</strong> ${new Date(dateRetour).toLocaleDateString('fr-FR')} à ${creneau}</p>
        <p><strong>Durée:</strong> ${dureeJours} jour(s)</p>
        <p><strong>Prix total:</strong> ${prixTotal.toFixed(2)} €</p>
      `;

      // Remplir les champs cachés
      document.getElementById('modal_date_depart').value = dateDepart;
      document.getElementById('modal_creneau').value = creneau;
      document.getElementById('modal_date_retour').value = dateRetour;

      paymentModal.classList.remove('hidden');
    });

    // Fermer le modal
    cancelPaymentBtn.addEventListener('click', () => {
      paymentModal.classList.add('hidden');
    });

    // Fermer modal si click à l'extérieur
    paymentModal.addEventListener('click', (e) => {
      if (e.target === paymentModal) {
        paymentModal.classList.add('hidden');
      }
    });

    // Formatage numéro de carte
    document.querySelector('input[name="numero_carte"]').addEventListener('input', function(e) {
      let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
      let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
      if (formattedValue.length > 19) formattedValue = formattedValue.substr(0, 19);
      e.target.value = formattedValue;
    });
  </script>

  <script>
    // Carousel JS (inchangé)
    const carousel = document.getElementById("carousel");
    const prevBtn = document.getElementById("prevBtn");
    const nextBtn = document.getElementById("nextBtn");
    let index = 0;
    const total = carousel.children.length;
    function updateCarousel() {
      carousel.style.transform = `translateX(-${index * 100}%)`;
    }
    prevBtn.addEventListener("click", () => {
      index = (index > 0) ? index - 1 : total - 1;
      updateCarousel();
    });
    nextBtn.addEventListener("click", () => {
      index = (index < total - 1) ? index + 1 : 0;
      updateCarousel();
    });
  </script>

</body>

</html>
