<?php
session_start();
require_once('../../../db/dbconnect2.php');
header('Content-Type: application/json');

if (isset($_SESSION['id_client'])) {
    $numero = $_SESSION['id_client'];
    $type = 'client';
} elseif (isset($_SESSION['id_pro'])) {
    $numero = $_SESSION['id_pro'];
    $type = 'pro';
} else {
    echo json_encode(['nb_non_lus' => 0]);
    exit;
}

$stmt = $db->prepare("SELECT id FROM users WHERE numero = ?");
$stmt->execute([$numero]);
$user_id = $stmt->fetchColumn();

if (!$user_id) {
    echo json_encode(['nb_non_lus' => 0]);
    exit;
}

if ($type === 'client') {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM messages m
        JOIN chats c ON m.chat_id = c.id
        WHERE c.client_id = :id
        AND m.auteur_id = c.pro_id
        AND m.lu = 0
    ");
} else {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM messages m
        JOIN chats c ON m.chat_id = c.id
        WHERE c.pro_id = :id
        AND m.auteur_id = c.client_id
        AND m.lu = 0
    ");
}

$stmt->execute(['id' => $user_id]);
$count = $stmt->fetchColumn();

echo json_encode([
    'nb_non_lus' => (int)$count
]);
