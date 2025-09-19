<?php
require_once '../header.php';
require_once '../includes/webhook.php';
require_once '../api/traker.php';
session_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
  <link rel="manifest" href="img/site.webmanifest">
  <title>Mes discussions</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
</head>

<body class="bg-gray-100 min-h-screen">
  <?php include('../includes/dropdown.php'); ?>
  <div class="max-w-2xl mx-auto py-6 px-3 sm:px-5">
    <h2 class="text-2xl font-semibold mb-6 text-gray-800">Mes discussions</h2>

    <div id="liste-chats" class="w-full max-w-md bg-white rounded-lg shadow-md overflow-hidden"></div>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
      function chargerChats() {
        $.getJSON('config/php/chat/all-chat.php', function (data) {
          if (data.error) {
            $('#liste-chats').html('<p class="p-4 text-center text-red-600">' + data.error + '</p>');
            return;
          }
          if (data.length === 0) {
            $('#liste-chats').html('<p class="p-4 text-center text-gray-500">Aucun chat disponible.</p>');
            return;
          }

          let html = '';
          data.forEach(chat => {
            const message = chat.dernier_message ? chat.dernier_message : 'Aucun message';
            const date = chat.date_envoi ? new Date(chat.date_envoi) : null;
            const heure = date ? date.toLocaleTimeString('fr-FR', {
              hour: '2-digit',
              minute: '2-digit'
            }) : '';

            html += `
          <a href="v-chat?p=${chat.chiffre_id}" class="flex items-center px-4 py-3 border-b border-gray-200 hover:bg-gray-50 transition duration-200">
              <div class="flex-shrink-0">
                <div class="h-12 w-12 rounded-full bg-indigo-500 flex items-center justify-center text-white font-bold text-lg uppercase">${chat.prenom.charAt(0)}${chat.nom.charAt(0)}</div>
              </div>
              <div class="ml-4 flex-1 min-w-0">
                <p class="text-gray-900 font-semibold truncate">${chat.prenom} ${chat.nom}</p>
                <p class="text-gray-600 truncate mt-1">${message}</p>
              </div>
              <div class="ml-4 text-gray-400 text-xs whitespace-nowrap">${heure}</div>
            </a>`;
          });

          $('#liste-chats').html(html);
        });
      }

      $(document).ready(function () {
        chargerChats();
      });
    </script>

</body>

</html>