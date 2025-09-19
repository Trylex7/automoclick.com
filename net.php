<?php
// cleanup_sessions.php - À exécuter une seule fois pour nettoyer
require_once 'db/dbconnect2.php';

echo "🧹 Nettoyage des sessions corrompues...\n\n";

try {
    // 1. Vider toutes les sessions en base
    $stmt = $db->prepare("DELETE FROM sessions_pro");
    $deleted_db = $stmt->execute();
    $count_db = $stmt->rowCount();
    echo "✅ Sessions en base supprimées : $count_db\n";

    // 2. Nettoyer les fichiers de session sur le serveur
    $sessionPath = '/tmp';
    $files = glob($sessionPath . '/sess_*');
    $count_files = 0;
    
    if ($files) {
        foreach ($files as $file) {
            if (unlink($file)) {
                $count_files++;
            }
        }
    }
    echo "✅ Fichiers de session supprimés : $count_files\n";

    // 3. Forcer l'expiration des cookies côté navigateur
    if (isset($_COOKIE)) {
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'PHPSESSID') !== false || strpos($name, 'session') !== false) {
                setcookie($name, '', time() - 3600, '/');
                echo "✅ Cookie '$name' expiré\n";
            }
        }
    }

    echo "\n🎉 Nettoyage terminé ! Tous les utilisateurs devront se reconnecter.\n";
    echo "📝 Vous pouvez maintenant supprimer ce fichier cleanup_sessions.php\n";

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
?>