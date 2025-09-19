<?php
require_once '../../db/dbconnect2.php';

// Forcer un dossier de sessions autorisé
$sessionPath = '/tmp'; // autorisé par open_basedir
if (!is_dir($sessionPath)) mkdir($sessionPath, 0700, true);

session_save_path($sessionPath);
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_to_kick = $_POST['session_id'] ?? null;

    if ($session_to_kick) {
        // Vérifier si c'est la session actuelle AVANT de faire quoi que ce soit
        $is_current_session = (session_id() === $session_to_kick || ($_SESSION['session_id'] ?? '') === $session_to_kick);
        
        // Supprime la session dans la base
        $stmt = $db->prepare("DELETE FROM sessions_pro WHERE session_id = ?");
        $stmt->execute([$session_to_kick]);

        // Supprime le fichier de session côté serveur
        $session_file = $sessionPath . "/sess_$session_to_kick";
        if (is_file($session_file)) {
            @unlink($session_file); // supprime silencieusement si problème
        }

        // Si c'est la session actuelle, on détruit la session en cours
        if ($is_current_session) {
            $_SESSION = [];

            // SEULEMENT ici on supprime les cookies (pour la session actuelle)
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }

            session_destroy();
            header("Location: connexion");
            exit;
        }

        // Si c'était une autre session, on reste sur la page SANS toucher aux cookies
        header("Location: setting#appareils");
        exit;
    } else {
        echo "Aucune session sélectionnée.";
    }
} else {
    echo "Méthode non autorisée.";
}