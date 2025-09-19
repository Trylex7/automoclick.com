<?php
require __DIR__ . '/vendor/autoload.php';

use Stancer;

// ===============================
// CONFIGURATION
// ===============================

    $api_secret = 'stest_5uVOgSFSOaatrwgwFYX2LjGr';
    $api_key = 'ptest_ahYgGhVjtfQnd17brCmCwqMe';
$config = Stancer\Config::init([$api_key, $api_secret]);
$config->setMode(Stancer\Config::TEST_MODE);

// ===============================
// RÉCEPTION DU FORMULAIRE
// ===============================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $iban = trim($_POST["iban"]);
    $amount = floatval($_POST["amount"]);

    if (!$iban || $amount <= 0) {
        die("❌ Erreur : données invalides.");
    }

    // ===============================
    // LOGIQUE DE PAYOUT
    // ===============================
    // ⚠️ Ici Stancer n’a pas (encore) d’API publique pour envoyer des virements
    // Tu dois soit utiliser ton compte marchand Stancer, soit une API bancaire.
    // On simule la logique avec un log + enregistrement BDD.
    // ===============================

    $payout_id = uniqid("payout_");

    // Exemple : log du payout
    $log = date("Y-m-d H:i:s") . " | Payout ID: $payout_id | Montant: $amount € | IBAN: $iban\n";
    file_put_contents(__DIR__ . "/payouts.log", $log, FILE_APPEND);

    echo "<h2 style='font-family: sans-serif; color: green;'>✅ Payout demandé avec succès !</h2>";
    echo "<p><strong>ID :</strong> $payout_id</p>";
    echo "<p><strong>Montant :</strong> $amount €</p>";
    echo "<p><strong>IBAN :</strong> $iban</p>";
} else {
    echo "Accès interdit.";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Payout Automoclick</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

  <div class="bg-white shadow-lg rounded-2xl p-8 w-full max-w-lg">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Payout vers un professionnel</h1>

    <form method="POST" class="space-y-4">
      <!-- IBAN -->
      <div>
        <label class="block text-gray-700 font-medium">IBAN du professionnel</label>
        <input type="text" name="iban" placeholder="FR76 XXXX XXXX XXXX XXXX XXXX XXX" 
               class="w-full border rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
      </div>

      <!-- Montant -->
      <div>
        <label class="block text-gray-700 font-medium">Montant (€)</label>
        <input type="number" step="0.01" name="amount" placeholder="100.00" 
               class="w-full border rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
      </div>

      <!-- Bouton -->
      <div>
        <button type="submit" 
                class="w-full bg-blue-600 text-white font-semibold py-3 rounded-lg hover:bg-blue-700 transition">
          Envoyer le payout
        </button>
      </div>
    </form>
  </div>

</body>
</html>
