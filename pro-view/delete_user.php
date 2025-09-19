<?php 
session_start();
require_once '../db/dbconnect2.php';
require_once '../includes/webhook.php';
if (!isset($_SESSION['id_pro'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /connexion?success_url={$currentUrl}");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']); // Sécurisation

    try {
        $stmt_pro = $db->prepare("SELECT * FROM user_pro WHERE id = ?");
        $stmt_pro->execute([$user_id]);
        $pro = $stmt_pro->fetch(PDO::FETCH_ASSOC);
        $delete_log = $db->prepare("DELETE FROM login_pro WHERE mdp = ?");
        $delete_log->execute([$pro['password']]);
        $stmt = $db->prepare("DELETE FROM user_pro WHERE id = ?");
        $success = $stmt->execute([$user_id]);

        if ($success) {
            $_SESSION['success_message'] = "Utilisateur supprimé avec succès.";
        } else {
            $_SESSION['error_message'] = "Erreur lors de la suppression de l'utilisateur.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur SQL : " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Requête invalide.";
}

// Redirection vers la page précédente (ou liste des utilisateurs)
header("Location: setting"); 
exit;
