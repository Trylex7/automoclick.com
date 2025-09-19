<?php
session_start();

// Chemin par défaut des sessions PHP
$sessionPath = session_save_path() ?: sys_get_temp_dir();

// Supprime tous les fichiers de session
foreach (glob("$sessionPath/sess_*") as $file) {
    unlink($file);
}

echo "Toutes les sessions ont été vidées. Les utilisateurs devront se reconnecter.";
?>
