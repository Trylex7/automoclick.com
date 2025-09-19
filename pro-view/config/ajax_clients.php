<?php
session_start();
$numero_pro = $_SESSION['id_pro'];
require_once '../../header.php';
require_once('../../db/dbconnect2.php');
$q = $_POST['q'] ?? '';
$stmt = $db->prepare("    SELECT id_client, nom, email, numero_client
    FROM login_user
    WHERE (nom LIKE ? OR email LIKE ?)
      AND numero_client IN (
          SELECT numero_client FROM rdvs WHERE numero_pro = ?
          UNION
          SELECT numero_client FROM devis WHERE pro_id = ?
      )
    LIMIT 1");
$stmt->execute(["%$q%", "%$q%", $numero_pro, $numero_pro]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => !!$c, 'client' => $c]);
