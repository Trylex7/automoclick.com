<?php
session_start();  
// require_once 'header.php';
require_once 'db/dbconnect2.php';
if (!isset($_SESSION['id_admin'])) {
  header('Location: /');
  
}
// Récupérer tous les produits
$data = $db->prepare('SELECT * FROM produit ORDER BY type');
$data->execute();
$produits = $data->fetchAll(PDO::FETCH_ASSOC);

// Séparer par type
$produits_pro = array_filter($produits, fn($p) => $p['categorie'] === 'pro');
$produits_clients = array_filter($produits, fn($p) => $p['categorie'] === 'particulier');
$produits_articles = array_filter($produits, fn($p) => $p['type'] === 'article');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
      <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
      <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
       <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="manifest" href="img/site.webmanifest">
  <title>Boutique - Automoclick</title>
  <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
  <?php include ('includes/dropdown.php'); ?>

<main class="relative max-w-7xl mx-auto px-6 py-12 grid grid-cols-1 lg:grid-cols-4 gap-10">
  
  <!-- Notifications -->
  <div id="notification-container" class="fixed top-5 right-5 space-y-3 z-50"></div>

  <!-- PRODUITS -->
  <div class="lg:col-span-3 space-y-14">
    
    <!-- Section Pro -->
    <section>
      <h2 class="text-3xl font-bold mb-6 text-gray-800">Espace Professionnels</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
        <?php foreach ($produits_pro as $produit): ?>
          <div class="bg-white rounded-2xl shadow-md hover:shadow-2xl transition p-5 flex flex-col">
            <img src="<?= htmlspecialchars($produit['image']) ?>" 
                 alt="<?= htmlspecialchars($produit['nom']) ?>" 
                 class="w-full h-44 object-cover rounded-xl">
            <h3 class="text-lg font-semibold mt-4 text-gray-900"><?= htmlspecialchars($produit['nom']) ?></h3>
            <p class="text-green-600 text-xl font-bold mt-1"><?= number_format($produit['prix'], 2) ?> €</p>
            <button type="button" data-id="<?= $produit['id'] ?>" 
              class="ajouter-panier mt-auto bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
              Ajouter au panier
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Section Clients -->
    <section>
      <h2 class="text-3xl font-bold mb-6 text-gray-800">Espace Clients</h2>
      <p class="text-gray-600 mb-6">Accès VIP : priorité sur le chat, rendez-vous prioritaires, assistance personnalisée.</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
        <?php foreach ($produits_clients as $produit): ?>
          <?php if ($produit['type'] === 'abonnement'): ?>
            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-2xl shadow-md hover:shadow-xl transition p-6 text-center">
              <img src="<?= htmlspecialchars($produit['image']) ?>" 
                   alt="<?= htmlspecialchars($produit['nom']) ?>" 
                   class="w-32 h-32 object-cover mx-auto rounded-full border-4 border-yellow-400 shadow-md">
              <h3 class="text-xl font-bold mt-4 text-yellow-700"><?= htmlspecialchars($produit['nom']) ?></h3>
              <p class="text-yellow-600 text-lg font-bold mt-2"><?= number_format($produit['prix'], 2) ?> €/mois</p>
              <button type="button" data-id="<?= $produit['id'] ?>" 
                class="ajouter-panier bg-yellow-500 text-white px-6 py-3 rounded-full mt-4 hover:bg-yellow-600 transition font-semibold">
                Devenir VIP
              </button>
            </div>
          <?php else: ?>
            <div class="bg-white rounded-2xl shadow-md hover:shadow-xl transition p-5 flex flex-col">
              <img src="<?= htmlspecialchars($produit['image']) ?>" 
                   alt="<?= htmlspecialchars($produit['nom']) ?>" 
                   class="w-full h-44 object-cover rounded-xl">
              <h3 class="text-lg font-semibold mt-4"><?= htmlspecialchars($produit['nom']) ?></h3>
              <p class="text-green-600 text-xl font-bold"><?= number_format($produit['prix'], 2) ?> €</p>
              <button type="button" data-id="<?= $produit['id'] ?>" 
                class="ajouter-panier mt-auto bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                Ajouter au panier
              </button>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <!-- PANIER -->
<aside class="relative right-10 w-96 lg:w-[28rem] bg-white p-6 rounded-2xl shadow-2xl h-fit top-10 border border-gray-100 lg:ml-auto">
  <h2 class="text-2xl font-extrabold mb-6 pb-3 border-b-2 border-gray-200 flex items-center gap-2 text-gray-900">
    <span class="text-green-600">🛒</span> Mon Panier
  </h2>
  <div id="panier-content" class="space-y-4 text-gray-700">
    <p class="text-gray-400 text-center italic">Votre panier est vide.</p>
  </div>
</aside>







</main>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function showNotification(message, type = 'success') {
  const container = document.getElementById('notification-container');
  const notif = document.createElement('div');
  notif.className = `px-4 py-3 rounded-lg shadow text-white animate-fade-in-up 
    ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
  notif.textContent = message;
  container.appendChild(notif);
  setTimeout(() => notif.remove(), 3000);
}

// Charger panier dynamiquement
function chargerPanier() {
  $.get('panier.php', { action: 'voir' }, function(html) {
    $('#panier-content').html(html);
  });
}

$(document).ready(function() {
  chargerPanier();

  // Ajouter au panier
  $(document).on('click', '.ajouter-panier', function() {
    let id = $(this).data('id');
    $.post('panier.php', { action: 'ajouter', id }, function() {
      showNotification('✅ Produit ajouté au panier !');
      chargerPanier();
    });
  });

  // Supprimer produit
  $(document).on('click', '.delete', function() {
    let id = $(this).closest('li').data('id');
    $.post('panier.php', { action: 'supprimer', id }, function() {
      showNotification('❌ Produit supprimé', 'error');
      chargerPanier();
    });
  });

});
</script>

<style>
@keyframes fade-in-up {
  from { opacity:0; transform:translateY(10px); }
  to { opacity:1; transform:translateY(0); }
}
.animate-fade-in-up { animation: fade-in-up 0.3s ease-out; }
</style>
