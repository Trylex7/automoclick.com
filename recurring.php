<?php
// script_cron_paiements.php

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db/dbconnect2.php'; // ta connexion PDO

use Stancer\Config;
use Stancer\Card;
use Stancer\Payment;

Config::init([
    'private' => 'sprod_wzX1s3orkjfXwUoHuSbXgBFC', 
]);

try {
    // 1. Récupérer les abonnements actifs (exemple de table `subscriptions`)
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE status = 'active'");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subscriptions as $sub) {
        // Récupérer les infos client & carte
        $cardId = $sub['stancer_card_id'];
        $amount = $sub['amount']; // en centimes
        $currency = $sub['currency'];
        $card = Card::retrieve($cardId);

        // Créer un paiement
        $payment = new Payment([
            'amount' => $amount,
            'currency' => $currency,
            'card' => $card,
        ]);

        $payment->send();

        // Mettre à jour la base avec le statut du paiement
        $stmtUpdate = $db->prepare("INSERT INTO transactions ( transaction_id, amount_cents, status, created_at) VALUES ( :pay_id, :amount, :status, NOW())");
        $stmtUpdate->execute([
            ':pay_id' => $payment->getId(),
            ':amount' => $amount,
            ':status' => $payment->getStatus()->value,
        ]);

        echo "Paiement " . $payment->getId() . " pour abonnement " . $sub['id'] . " effectué.\n";
    }
} catch (\Throwable $e) {
    echo "Erreur dans le cron : " . $e->getMessage() . "\n";
}
