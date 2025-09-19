<?php
$user = 'admin_auto';
$pass = 'Sd2s4Ox3$gqWg!vr';

try {
        $db = new PDO('mysql:host=localhost;dbname=automo_db', $user, $pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
}

// Durée d’expiration : 3 minutes (180 secondes)
$expiration_limit = date('Y-m-d H:i:s', time() - 180); // Format DATETIME

try {
        // Supprimer les RDV expirés (en attente de paiement et trop vieux)
        $stmt = $db->prepare("
    DELETE FROM rdvs 
    WHERE etat = 'en_attente_paiement' 
    AND transaction_rdv < (NOW() - INTERVAL 8 MINUTE)
");
        $stmt->execute();

        echo "RDV expirés supprimés : " . $stmt->rowCount();
} catch (Exception $e) {
        echo "Erreur : " . $e->getMessage();
}
