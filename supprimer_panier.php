<?php
session_start();

if (isset($_POST['id'])) {
  $id = (int) $_POST['id'];
  unset($_SESSION['panier'][$id]);
}

header('Location: panier.php');
exit;
?>