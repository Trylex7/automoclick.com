<?php
require 'vendor/autoload.php';

use Stancer\Payment;
use Stancer\Auth;
use Stancer\Config;
use Stancer\Card;

// --- CONFIGURATION ---
$api_secret = 'stest_5uVOgSFSOaatrwgwFYX2LjGr';
$config = Config::init([$api_secret]);
$config->setMode(Config::TEST_MODE);

$returnUrl = 'https://automoclick.com/test-pay.php'; // même fichier pour le retour

// --- SI REDIRECTION APRÈS 3D SECURE ---
if (isset($_GET['payment_id'])) {
    $paymentId = $_GET['payment_id'];
    try {
        $payment = new Payment($paymentId);
        $payment->fetch();

        if ($payment->auth->status === 'success') {
            echo '<h2>Paiement réussi ! ✅</h2>';
            echo 'Montant : ' . number_format($payment->amount / 100, 2) . ' ' . strtoupper($payment->currency);
        } else {
            echo '<h2>Paiement échoué ❌</h2>';
        }
    } catch (Exception $e) {
        echo 'Erreur : ' . $e->getMessage();
    }
    exit;
}

// --- CRÉATION DU PAIEMENT ---
try {
    $amount = 1000; // 10€
    $currency = 'eur';
    $description = 'Test paiement 3D Secure';
    $customerEmail = 'client@example.com';

    // --- CARTE DE TEST ---
    $card = new Card([
        'number' => '4000000000003220', // carte de test 3D Secure réussie
        'exp_month' => 12,
        'exp_year' => date('Y') + 8,
        'cvc' => '123',
        'holder' => 'Test User'
    ]);
    $card->send(); // envoie la carte à Stancer et récupère l'id

    // --- AUTH 3D SECURE ---
    $auth = new Auth([
        'status' => 'request',
        'return_url' => $returnUrl
    ]);

    // --- PAIEMENT ---
    $payment = new Payment([
        'amount' => $amount,
        'currency' => $currency,
        'description' => $description,
        'customer' => ['email' => $customerEmail],
        'card' => $card,   // ATTENTION : il faut la carte ici
        'auth' => $auth
    ]);
    $payment->send();

    // --- REDIRECTION 3D SECURE ---
    header('Location: ' . $payment->auth->redirect_url);
    exit;

} catch (Exception $e) {
    echo '<h2>Erreur lors de la création du paiement :</h2>';
    echo $e->getMessage();
}
?>
