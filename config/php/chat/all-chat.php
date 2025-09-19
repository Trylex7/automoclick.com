<?php
session_start();
require '../../../db/dbconnect2.php';
header('Content-Type: application/json');
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';
// 1. Déterminer l'identité utilisateur et son numero
if (isset($_SESSION['id_client'])) {
    $numero = $_SESSION['id_client'];
    $type = 'client';
} elseif (isset($_SESSION['id_pro'])) {
    $numero = $_SESSION['id_pro'];
    $type = 'pro';
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Utilisateur non connecté.']);
    exit;
}

// 2. Récupérer l'id numérique (clé primaire)
$stmt = $db->prepare("SELECT id FROM users WHERE numero = ?");
$stmt->execute([$numero]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Utilisateur introuvable.']);
    exit;
}

$user_id = $user['id'];

try {
    // 3. Sélection des chats avec le dernier message
    if ($type === 'client') {
        $stmt = $db->prepare("
            SELECT 
                c.id AS chat_id,
                u.nom, u.prenom,
                m.message AS dernier_message,
                m.date_envoi
            FROM chats c
            JOIN users u ON u.id = c.pro_id
            LEFT JOIN (
                SELECT m1.*
                FROM messages m1
                INNER JOIN (
                    SELECT chat_id, MAX(date_envoi) AS max_date
                    FROM messages
                    GROUP BY chat_id
                ) m2 ON m1.chat_id = m2.chat_id AND m1.date_envoi = m2.max_date
            ) m ON m.chat_id = c.id
            WHERE c.client_id = ?
            ORDER BY m.date_envoi DESC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT 
                c.id AS chat_id,
                u.nom, u.prenom,
                m.message AS dernier_message,
                m.date_envoi
            FROM chats c
            JOIN users u ON u.id = c.client_id
            LEFT JOIN (
                SELECT m1.*
                FROM messages m1
                INNER JOIN (
                    SELECT chat_id, MAX(date_envoi) AS max_date
                    FROM messages
                    GROUP BY chat_id
                ) m2 ON m1.chat_id = m2.chat_id AND m1.date_envoi = m2.max_date
            ) m ON m.chat_id = c.id
            WHERE c.pro_id = ?
            ORDER BY m.date_envoi DESC
        ");
    }

    $stmt->execute([$user_id]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter un identifiant chiffré à chaque chat
    foreach ($chats as &$chat) {
        $chat['chiffre_id'] = urlencode(base64_encode(openssl_encrypt($chat['chat_id'], 'AES-128-ECB', $cle_secrete)));
    }

    echo json_encode($chats);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur : ' . $e->getMessage()]);
}
