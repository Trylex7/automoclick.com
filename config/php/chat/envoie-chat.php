<?php
session_start();
require '../../../db/dbconnect2.php';
header('Content-Type: application/json');

$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

function dechiffrer($texte_chiffre, $cle)
{
    $decoded = base64_decode(urldecode($texte_chiffre));
    return $decoded ? openssl_decrypt($decoded, 'AES-128-ECB', $cle) : false;
}

// Récupération et déchiffrement du chat_id
$chat_id = null;
if (!empty($_POST['p'])) {
    $chat_id = dechiffrer($_POST['p'], $cle_secrete);
} elseif (!empty($_GET['p'])) {
    $chat_id = dechiffrer($_GET['p'], $cle_secrete);
} elseif (!empty($_POST['chat_id'])) {
    $chat_id = (int)$_POST['chat_id'];
}

$message = trim($_POST['message'] ?? '');

if ($chat_id) {
    // Chargement du chat
    $stmt = $db->prepare("SELECT client_id, pro_id FROM chats WHERE id = ?");
    $stmt->execute([$chat_id]);
    $chat = $stmt->fetch();

    if (!$chat) {
        echo json_encode(['error' => 'Chat introuvable']);
        exit;
    }

    $client_id = $chat['client_id'];
    $pro_id = $chat['pro_id'];

} else {
    // Récupération des numéros chiffrés
    $numero_pro = $_POST['pro_id'] ?? (isset($_GET['pro']) ? dechiffrer($_GET['pro'], $cle_secrete) : null);
    $numero_client = $_POST['client_id'] ?? (isset($_GET['client']) ? dechiffrer($_GET['client'], $cle_secrete) : null);

    if (!$numero_pro || !$numero_client) {
        echo json_encode(['error' => 'Identifiants manquants ou invalides']);
        exit;
    }

    // Données pro
    $stmt = $db->prepare("SELECT denomination FROM entreprises WHERE numero_pro = ?");
    $stmt->execute([$numero_pro]);
    $pro_data = $stmt->fetch();
    if (!$pro_data) {
        echo json_encode(['error' => 'Professionnel introuvable']);
        exit;
    }

    // Données client
    $stmt = $db->prepare("SELECT nom, prenom FROM login_user WHERE numero_client = ?");
    $stmt->execute([$numero_client]);
    $client_data = $stmt->fetch();
    if (!$client_data) {
        echo json_encode(['error' => 'Client introuvable']);
        exit;
    }

    // Users : pro
    $stmt = $db->prepare("SELECT id FROM users WHERE numero = ?");
    $stmt->execute([$numero_pro]);
    $pro = $stmt->fetch();
    $pro_id = $pro['id'] ?? null;

    if (!$pro_id) {
        $stmt = $db->prepare("INSERT INTO users (nom, role, numero) VALUES (?, 'pro', ?)");
        $stmt->execute([$pro_data['denomination'], $numero_pro]);
        $pro_id = $db->lastInsertId();
    }

    // Users : client
    $stmt = $db->prepare("SELECT id FROM users WHERE numero = ?");
    $stmt->execute([$numero_client]);
    $client = $stmt->fetch();
    $client_id = $client['id'] ?? null;

    if (!$client_id) {
        $stmt = $db->prepare("INSERT INTO users (nom, prenom, role, numero) VALUES (?, ?, 'client', ?)");
        $stmt->execute([$client_data['nom'], $client_data['prenom'], $numero_client]);
        $client_id = $db->lastInsertId();
    }

    // Création ou récupération du chat
    $stmt = $db->prepare("SELECT id FROM chats WHERE client_id = ? AND pro_id = ?");
    $stmt->execute([$client_id, $pro_id]);
    $chat = $stmt->fetch();
    $chat_id = $chat['id'] ?? null;

    if (!$chat_id) {
        $stmt = $db->prepare("INSERT INTO chats (client_id, pro_id) VALUES (?, ?)");
        $stmt->execute([$client_id, $pro_id]);
        $chat_id = $db->lastInsertId();
    }
}

// Authentification session
$numero_session = $_SESSION['id_client'] ?? $_SESSION['id_pro'] ?? null;

if (!$numero_session) {
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Récupération de l'auteur
$stmt = $db->prepare("SELECT id FROM users WHERE numero = ?");
$stmt->execute([$numero_session]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'Utilisateur non trouvé']);
    exit;
}

$auteur_id = $user['id'];

// Vérification d'appartenance au chat
if ($auteur_id != $client_id && $auteur_id != $pro_id) {
    echo json_encode(['error' => 'Accès refusé à cette conversation']);
    exit;
}

// Insertion d’un message
if (!empty($message)) {
    if (strlen($message) > 1000) {
        echo json_encode(['error' => 'Message trop long']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO messages (chat_id, auteur_id, message, date_envoi, lu) VALUES (?, ?, ?, NOW(), 0)");
    $stmt->execute([$chat_id, $auteur_id, $message]);

    echo json_encode(['success' => true]);
    exit;
}

// Marquer les messages comme lus
$update = $db->prepare("UPDATE messages SET lu = 1 WHERE chat_id = ? AND auteur_id != ? AND lu = 0");
$update->execute([$chat_id, $auteur_id]);

// Retourner les messages
$stmt = $db->prepare("
    SELECT m.*, u.nom, u.prenom 
    FROM messages m 
    JOIN users u ON m.auteur_id = u.id 
    WHERE chat_id = ? 
    ORDER BY m.date_envoi ASC
");
$stmt->execute([$chat_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($messages);
exit;
