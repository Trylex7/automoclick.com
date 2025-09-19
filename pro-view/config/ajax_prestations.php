<?php
session_start();
require_once '../../header.php';
require_once('../../db/dbconnect2.php');
$pro_id = $_SESSION['id_pro'];
$stmt = $db->prepare("SELECT id, nom, ref,duree, prix, tva FROM prestations WHERE numero_pro = ?");
$stmt->execute([$pro_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'id' => $row['id'],
        'nom' => $row['nom'], // ce champ doit être du texte comme "Vidange"
        'ref' => $row['ref'],
        'prix' => number_format($row['prix'], 2, '.', ''), // ou $row['prix'] / 100 si en centimes
        'duree' => $row['duree'],
        'tva' => $row['tva']
    ];
}

header('Content-Type: application/json');
echo json_encode($results);