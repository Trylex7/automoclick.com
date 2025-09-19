<?php
session_start();
session_destroy();
setcookie("cookies_user", "", [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
header('Location: /');