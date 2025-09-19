<?php
require_once 'header.php';
require_once 'db/dbconnect2.php';
require_once 'includes/webhook.php';
session_start();

if (!isset($_SESSION['id_client']) && !isset($_SESSION['id_pro'])) {
  header('Location: /');
  exit();
}

// Détecter si c'est un client ou un professionnel connecté
$isClient = isset($_SESSION['id_client']);

if ($isClient) {
  $numero = $_SESSION['id_client'];
  $stmt = $db->prepare('SELECT * FROM rdvs WHERE numero_client = ?');
} else {
  $numero = $_SESSION['id_pro'];
  $stmt = $db->prepare('SELECT * FROM rdvs WHERE numero_pro = ?');
}

$stmt->execute([$numero]);
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>Mes Rendez-vous</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Important pour le responsive -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.getElementById('mobile-menu-button').addEventListener('click', function () {
      const menu = document.getElementById('mobile-menu');
      menu.classList.toggle('hidden');
    });
  </script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }

    /* Form controls style */
    input,
    select {
      transition: box-shadow 0.3s ease;
    }

    input:focus,
    select:focus {
      outline: none;
      box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.5);
      border-color: #22c55e;
    }

    @keyframes floatAnimation {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-15px);
      }
    }

    .animated-float {
      animation: floatAnimation 4s ease-in-out infinite;
      will-change: transform;
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-800">

  <?php include('includes/dropdown.php'); ?>

  <body class="bg-gray-100 min-h-screen">
    <div class="max-w-2xl mx-auto py-6 px-3 sm:px-5">
      <h1 class="text-xl sm:text-2xl font-bold text-center text-gray-800 mb-6">📅 Mes Rendez-vous</h1>

      <?php if (!empty($rdvs)): ?>
        <div class="space-y-4">
          <?php foreach ($rdvs as $rdv): ?>
            <div class="bg-white rounded-xl shadow-sm p-3 sm:p-5 hover:shadow-md transition text-sm sm:text-base">
              <div class="flex flex-col gap-1">
                <p class="text-gray-700"><span class="font-semibold">📅 Date :</span> <?= htmlspecialchars($rdv['date']) ?>
                </p>
                <p class="text-gray-700"><span class="font-semibold">⏰ Heure :</span> <?= htmlspecialchars($rdv['heure']) ?>
                </p>

                <?php
                if ($isClient) {
                  $stmtClient = $db->prepare('SELECT denomination FROM entreprises WHERE numero_pro = ?');
                  $stmtClient->execute([$rdv['numero_pro']]);
                  $client = $stmtClient->fetch(PDO::FETCH_ASSOC);
                  if ($client) {
                    echo '<p class="text-gray-700"><span class="font-semibold">👤 Pro :</span> ' . htmlspecialchars($client['denomination']) . '</p>';
                  }
                } else {
                  $stmtPro = $db->prepare('SELECT nom, prenom FROM login_user WHERE numero_client = ?');
                  $stmtPro->execute([$rdv['numero_client']]);
                  $pro = $stmtPro->fetch(PDO::FETCH_ASSOC);
                  if ($pro) {
                    echo '<p class="text-gray-700"><span class="font-semibold">👤 Client :</span> ' . htmlspecialchars($pro['prenom']) . ' ' . htmlspecialchars($pro['nom']) . '</p>';
                  }
                }
                ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center mt-10 px-2">
          <p class="text-gray-600 text-sm sm:text-base">Aucun rendez-vous trouvé.</p>
        </div>
      <?php endif; ?>
    </div>
  </body>

</html>