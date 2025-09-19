<?php
session_start();
require 'db/dbconnect2.php';

if (!isset($_SESSION['id_client']) || !isset($_POST['id_pro'], $_POST['note'], $_POST['commentaire'])) {
    exit('Données manquantes.');
}

$client_id = $_SESSION['id_client'];
$pro_id = $_POST['id_pro'];
$note = (int)$_POST['note'];
$commentaire = htmlspecialchars($_POST['commentaire']);

$stmt = $db->prepare("INSERT INTO avis (client_id, professionnel_id, note, commentaire) VALUES (?, ?, ?, ?)");
$stmt->execute([$client_id, $pro_id, $note, $commentaire]);

header("Location: pro");
exit;
