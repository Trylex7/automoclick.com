<?php
require_once 'header.php';
require_once 'includes/webhook.php';
session_start();
require_once 'db/dbconnect2.php';
require __DIR__ . '/vendor/autoload.php';

use Stancer\Config;
use Stancer\Card;
use Stancer\Payment;

// Récupération des sessions
$numero_pro = $_SESSION['id_pro'] ?? null;

// Vérifie le token "n" dans l'URL
if (!isset($_GET['n'])) {
    die('Token manquant');
}

$decoded = json_decode(base64_decode(urldecode($_GET['n'])), true);
if (!$decoded || !isset($decoded['nom'], $decoded['prix'], $decoded['type'], $decoded['session-id'])) {
    die('Token invalide');
}
// Données de paiement
$nom = htmlspecialchars($decoded['nom']);
$prix = floatval($decoded['prix']);
$amount = intval($prix * 100); // Montant en centimes
$type = htmlspecialchars($decoded['type']);
$session_id = $decoded['session-id'];
if (
    !isset($_SESSION['session_paiement']) ||
    $_SESSION['session_paiement']['id'] !== $session_id
) {
    $_SESSION['session_paiement'] = [
        'id' => $session_id,
        'created_at' => time(),
        'data' => [
            'nom' => $nom,
            'prix' => $prix,
            'type' => $type
        ]
    ];
}
if (isset($_SESSION['session_paiement'])) {
    $now = time();
    $createdAt = $_SESSION['session_paiement']['created_at'];

    if ($now - $createdAt > 120) {
        unset($_SESSION['session_paiement']);
        echo "<p style='color:red'>⏰ Session de paiement expirée. Veuillez recommencer.</p>";
        exit;
    }

    // Accès OK → Tu peux continuer ici
    $nom = $_SESSION['session_paiement']['data']['nom'];
    $prix = $_SESSION['session_paiement']['data']['prix'];
    $type = $_SESSION['session_paiement']['data']['type'];
    $session_id = $_SESSION['session_paiement']['id'];
} else {
    echo "<p style='color:red'>❌ Aucune session de paiement active.</p>";
    exit;
}


// Configuration Stancer
Config::init([
    'private' => 'stest_5uVOgSFSOaatrwgwFYX2LjGr',
]);

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $number_card = $_POST['card'] ?? null;
    $month = $_POST['month'] ?? null;
    $years = $_POST['years'] ?? null;
    $cvc = $_POST['cvc'] ?? null;
    $name_card = $_POST['name'] ?? 'Client';

    // Validation basique
    if (!$number_card || !$month || !$years || !$cvc) {
        echo "<p style='color:red'>Tous les champs sont obligatoires.</p>";
    } else {
        try {
            $card = new Card([
                'number' => preg_replace('/\s+/', '', $number_card),
                'exp_month' => (int)$month,
                'exp_year' => (int)('20' . $years),
                'cvc' => $cvc,
                'name' => $name_card,
            ]);

            $payment = new Payment([
                'amount' => $amount,
                'currency' => 'EUR',
                'card' => $card,
            ]);

            $payment->send();

            $status = $payment->getStatus()->value;
            $transactionId = $payment->getId();
            $currency = $payment->getCurrency()->value;
            $dateStr = date('Y-m-d H:i:s');

            $cardLast4 = $card->getLast4();
            $cardBrand = $card->getBrand();

            // Insertion en BDD
            $stmt = $db->prepare("INSERT INTO transactions (
                transaction_id, numero_pro, client_firstname, client_lastname, client_email,
                amount_cents, type, currency, transaction_date, status, card_last4, card_brand
            ) VALUES (
                :transaction_id, :numero_pro, :client_firstname, :client_lastname, :client_email,
                :amount_cents, :type, :currency, :transaction_date, :status, :card_last4, :card_brand
            )");

            $stmt->execute([
                ':transaction_id' => $transactionId,
                ':numero_pro' => $numero_pro,
                ':client_firstname' => null,
                ':client_lastname' => $name_card,
                ':client_email' => null,
                ':amount_cents' => $amount,
                ':type' => 'subscription',
                ':currency' => $currency,
                ':transaction_date' => $dateStr,
                ':status' => $status,
                ':card_last4' => $cardLast4,
                ':card_brand' => $cardBrand,
            ]);
            // Vérifie si un abonnement actif existe déjà pour ce professionn
            if ($status === 'authorized' || $status === 'captured') {
                $statut = "active";

                // Vérifier si un abonnement actif existe déjà
                $check = $db->prepare('SELECT id FROM pro_abonnement WHERE numero_pro = ? AND statut = ?');
                $check->execute([$numero_pro, 'active']);
                $existingId = $check->fetchColumn();

                if ($existingId) {
                    // UPDATE abonnement existant
                    $update = $db->prepare('UPDATE pro_abonnement SET nom_abonnement = ?, prix = ?, type = ?, date_create = NOW() WHERE id = ?');
                    $update->execute([$nom, $prix, $type, $existingId]);
                } else {
                    // INSERT nouvel abonnement
                    $insert = $db->prepare('INSERT INTO pro_abonnement (nom_abonnement, prix, numero_pro, statut, date_create, type) VALUES (?, ?, ?, ?, NOW(), ?)');
                    $insert->execute([$nom, $prix, $numero_pro, $statut, $type]);
                }

                unset($_SESSION['session_paiement']);
                header('Location: dashbord');
            } else {
                unset($_SESSION['session_paiement']);
                header('Location: paiement-refuser.php');
            }
        } catch (\Throwable $e) {
            unset($_SESSION['session_paiement']);
            echo "<p style='color:red'><strong>❌ Erreur lors du paiement :</strong><br>
            " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
        <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
    <title>Paiement</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="bg-white shadow-xl rounded-2xl p-8 max-w-md w-full">
        <p class="text-center text-gray-600 text-lg mb-4">
            Montant : <strong><?= number_format($prix, 2, ',', ' ') ?> €</strong>
        </p>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700">Numéro de carte</label>
                <input type="text" name="card" placeholder="0000 0000 0000 0000"
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    required>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mois d'expiration</label>
                    <input type="text" name="month" maxlength="2" placeholder="MM"
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Année d'expiration</label>
                    <input type="text" name="years" maxlength="2" placeholder="YY"
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">CVC</label>
                <input type="text" name="cvc" maxlength="4" placeholder="123"
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nom du titulaire</label>
                <input type="text" name="name" placeholder="Jean Dupont"
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="pt-4">
                <input type="submit" value="Payer"
                    class="w-full bg-green-600 text-white font-semibold py-2 rounded-lg hover:bg-green-700 transition">
            </div>
        </form>
    </div>

</body>

</html>