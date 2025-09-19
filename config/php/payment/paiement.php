<?php
session_start();
require_once '../../../db/dbconnect2.php';
require_once '../../../header.php';
require_once '../../../vendor/autoload.php'; // Pour Dompdf et Stancer

use Stancer\Config;
use Stancer\Card;
use Stancer\Payment;

// Clé secrète pour le chiffrement/déchiffrement
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

// Fonctions de chiffrement et déchiffrement
function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}

function dechiffrer($texte_chiffre, $cle)
{
    return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}

// Vérifie que le paramètre 'h' est présent
if (!isset($_GET['x'])) {
    die("Paramètre manquant.");
}

$token = $_GET['x'];

$all_data = dechiffrer($token, $cle_secrete);
$data = json_decode($all_data, true);

if (!is_array($data)) {
    die("Données du token invalides.");
}

// Vérifier si c'est bien une liste d'articles
if (isset($data[0])) {
    $articles = $data; // plusieurs articles
} else {
    $articles = [$data]; // 1 seul article (compatibilité)
}

foreach ($articles as $article) {
    $nom_article = $article['nom'];
    $type = $article['type'];
    $duree_abonnement = $article['duree_abonnement'];
    $categorie = $article['categorie'];
    $numero_pro = $article['numero_pro'];
    $numero_client = $article['numero_client'];
    $prix = $article['prix'];
    $total = $article['prix_total'];

    // tu peux stocker ça ou l'afficher
}
// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Génération d’un numéro unique pour facture
function genererNumero10Chiffres()
{
    return random_int(1000000000, 9999999999);
}
$numero_f = genererNumero10Chiffres();

// Traitement du formulaire de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Erreur CSRF : requête non autorisée.');
    }

    $cvc = $_POST['cvc'] ?? null;
    $expire_m = $_POST['expire_m'] ?? null;
    $expire_y = $_POST['expire_y'] ?? null;
    $card_number = $_POST['card'] ?? null;
    $cardholder_name = $_POST['name'] ?? 'Jean Dupont';

    if (!$card_number || !$expire_m || !$expire_y || !$cvc) {
        exit('Veuillez remplir tous les champs.');
    }

    $amount = (int) ($total * 100); // montant en centimes

    // Configuration Stancer
    $api_secret = 'stest_5uVOgSFSOaatrwgwFYX2LjGr';
    $api_key = 'ptest_ahYgGhVjtfQnd17brCmCwqMe';
    Config::init([$api_key, $api_secret])->setMode(Config::TEST_MODE);

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
        $cardId = $card->getId();

        // Enregistrement de la transaction
        $stmt = $db->prepare("INSERT INTO transactions (
            transaction_id, numero_pro, numero_client, client_firstname, client_lastname, client_email,
            amount_cents, currency, transaction_date, status, card_last4, card_brand
        ) VALUES (
            :transaction_id, :numero_pro, :numero_client, :client_firstname, :client_lastname, :client_email,
            :amount_cents, :currency, :transaction_date, :status, :card_last4, :card_brand
        )");

        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':numero_pro' => $numero_pro ?? null,
            ':numero_client' => $numero_client ?? null,
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
            foreach ($articles as $article) {
                $nom_article = $article['nom'];
                $type = $article['type'];
                $duree_abonnement = $article['duree_abonnement'];
                $categorie = $article['categorie'];
                $numero_pro = $article['numero_pro'];
                $numero_client = $article['numero_client'];
                $prix = $article['prix'];
                $total = $article['prix_total'];
                if ($type === 'abonnement') {
                    $insert = $db->prepare('INSERT INTO subscriptions (numero, stancer_card_id, amount, currency, fact, status)
                 VALUES (:numero, :stancer_card_id, :amount, :currency, :fact, :status)');
                    $insert->execute([
                        ':numero' => $numero_pro ?? $numero_client ?? null,
                        ':stancer_card_id' => $cardId,
                        ':amount' => $amount,
                        ':currency' => $currency,
                        ':fact' => '1 month',
                        ':status' => 'active',
                    ]);
                }


            }
            header('Location: thanks');
            exit;
        } else {
            header('Location: paiement_refuse.php');
            exit;
        }
    } catch (Exception $e) {
        echo 'Erreur lors du paiement : ' . $e->getMessage();
    }
}
?>



<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Paiement</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="bg-white shadow-xl rounded-2xl p-8 max-w-md w-full">

        <form method="POST" class="space-y-5">
            <div class="text-center">
                <h1 class="text-2xl font-bold mb-2">Total à payer</h1>
                <p class="text-lg text-gray-700"><?= number_format($total, 2, ',', ' ') ?> €</p>
            </div>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700">Numéro de carte</label>
                <input type="text" name="card" placeholder="0000 0000 0000 0000"
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    required>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mois d'expiration</label>
                    <input id="expire_m" type="text" name="expire_m" maxvalue="12" maxlength="2" placeholder="MM"
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Année d'expiration</label>
                    <input type="text" name="expire_y" maxlength="2" placeholder="YY"
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">CVC</label>
                <input type="text" name="cvc" maxlength="3" placeholder="123"
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
    <script>
        document.getElementById('expire_m').addEventListener('input', function () {
            let value = this.value.replace(/\D/g, ''); // enlève les lettres
            if (value.length === 2 && parseInt(value, 10) > 12) {
                this.value = '12';
            } else {
                this.value = value;
            }
        });
    </script>

</body>

</html>