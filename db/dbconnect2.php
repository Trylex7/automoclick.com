<?php
$user = 'admin_auto';
$pass = 'Sd2s4Ox3$gqWg!vr';

try {
    $db = new PDO('mysql:host=localhost;dbname=automo_db', $user, $pass);
} catch (PDOException $e) {
    print "Erreur !: " . $e->getMessage() . "<br/>";
    die;
}
?>
    