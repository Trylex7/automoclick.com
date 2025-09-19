<?php
define("SECRET_KEY", "70909e102fead6703222e2abd1cb74c7aa1542b4d79ff897bf2ad884a60325d5");
ini_set('session.cookie_lifetime', 0); 
ini_set('session.cookie_secure', 1);   
ini_set('session.cookie_httponly', 1); 
ini_set('session.use_strict_mode', 1); 
ini_set('session.use_only_cookies', 1); 
ini_set('session.cookie_samesite', 'Lax');
?>