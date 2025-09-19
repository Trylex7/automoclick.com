<?php
header('Content-Type: application/json');
require '../db/dbconnect2.php';
require_once '../includes/webhook.php';


$immat = $_GET['immat'] ?? '';

if (!$immat) {
    echo json_encode(['error' => 'Immatriculation manquante']);
    exit;
}

$sql = "SELECT * FROM vehicule_c WHERE immatriculation = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$immat]);
$vehicule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicule) {
    echo json_encode(['error' => 'Véhicule non trouvé']);
    exit;
}

echo json_encode($vehicule);
