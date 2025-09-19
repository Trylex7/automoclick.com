<?php
header('Content-Type: application/json');
session_start();
require_once('../db/dbconnect2.php');


$data = json_decode(file_get_contents('php://input'), true);
$id_pro = $data['id_pro'] ?? null;

if ($id_pro) {
    $ip = $_SERVER['REMOTE_ADDR'];

    // Vérifier si l'IP a déjà vu ce profil aujourd'hui
    $stmt = $db->prepare("SELECT COUNT(*) FROM vues_professionnels WHERE numero_pro = ? AND ip_address = ? AND date = CURDATE()");
    $stmt->execute([$id_pro, $ip]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $stmt = $db->prepare("INSERT INTO vues_professionnels (numero_pro, ip_address, date) VALUES (?, ?, CURDATE())");
        $stmt->execute([$id_pro, $ip]);
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'ID pro manquant']);
}
