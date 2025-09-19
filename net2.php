<?php
// emergency_logout.php - Page de déconnexion d'urgence
session_start();

// Détruire complètement la session actuelle
$_SESSION = [];

// Supprimer tous les cookies de session possibles
$cookie_names = ['PHPSESSID', session_name()];
foreach ($cookie_names as $cookie_name) {
    if (isset($_COOKIE[$cookie_name])) {
        setcookie($cookie_name, '', time() - 3600, '/');
        setcookie($cookie_name, '', time() - 3600, '/', $_SERVER['HTTP_HOST']);
        setcookie($cookie_name, '', time() - 3600, '/', '.' . $_SERVER['HTTP_HOST']);
    }
}

// Détruire la session
session_destroy();

// Forcer la suppression côté navigateur avec JavaScript
?>
<!DOCTYPE html>
<html>
<head>
    <title>Déconnexion d'urgence</title>
</head>
<body>
    <h2>🚨 Déconnexion d'urgence en cours...</h2>
    <p>Nettoyage des sessions corrompues...</p>
    
    <script>
    // Supprimer tous les cookies côté client
    document.cookie.split(";").forEach(function(c) { 
        document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
    });
    
    // Vider le localStorage et sessionStorage
    localStorage.clear();
    sessionStorage.clear();
    
    // Rediriger après 2 secondes
    setTimeout(function() {
        window.location.href = 'connexion';
    }, 2000);
    </script>
</body>
</html>