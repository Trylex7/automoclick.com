<?php
require_once 'config/config.php';
require_once 'db/dbconnect2.php';
require_once 'header.php';
require_once 'includes/webhook.php';
require_once 'api/traker.php';
session_start();
if (isset($_SESSION['id_pro'])){
  header('Location: dashbord');
}

if (!defined('SECRET_KEY')) {
    define('SECRET_KEY', '70909e102fead6703222e2abd1cb74c7aa1542b4d79ff897bf2ad884a60325d5');
}

if (isset($_COOKIE['cookies_user']) && !empty($_COOKIE['cookies_user'])) {
    
    // Vérification format cookie
    if (strpos($_COOKIE['cookies_user'], '|') !== false) {
        
        // CORRECTION : Séparer correctement base64|signature
        $cookie_parts = explode('|', $_COOKIE['cookies_user'], 2);
        
        if (count($cookie_parts) === 2) {
            $encodedPayload = $cookie_parts[0];
            $signature = $cookie_parts[1];
            
            if ($encodedPayload && $signature) {
                $payload = base64_decode($encodedPayload);
                
                // Vérification signature
                $checkSignature = hash('sha256', $payload . SECRET_KEY);
                
                if (hash_equals($checkSignature, $signature)) {
                    $parts = explode('|', $payload);
                    
                    // CORRECTION : Vérification du type de compte
                    if (count($parts) >= 6 && $parts[0] === 'pro') {
                        // Compte professionnel
                        $_SESSION['role'] = $parts[0];           // pro
                        $_SESSION['id_pro'] = $parts[1];         // numero_pro
                        $_SESSION['name_company'] = $parts[2];   // denomination
                        $_SESSION['siret'] = $parts[3];         // siret
                        $_SESSION['adresse'] = $parts[4];       // adresse
                        $_SESSION['siren'] = $parts[5];         // siren
                    }
                    elseif (count($parts) >= 3 && $parts[0] === 'client') {
                        // Compte particulier
                        $_SESSION['role'] = $parts[0];      // particulier
                        $_SESSION['id_client'] = $parts[1];   // id
                        $_SESSION['nom'] = $parts[2];
                        $_SESSION['prenom'] = $parts[3];      // nom
                    }
                    else {
                        // Format invalide
                        setcookie("cookies_user", "", [
                            'expires' => time() - 3600,
                            'path' => '/',
                            'secure' => true,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }
                } else {
                    setcookie("cookies_user", "", [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
            }
        }
    } else {
        setcookie("cookies_user", "", [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta property="og:url" content="https://automoclick.com" />
  <meta property="og:type" content="website" />
  <meta name="robots" content="index, follow">
  <meta name="description" content="Trouvez et réservez facilement un professionnel de l'automobile autour de vous.">
  <meta name="keywords"
    content="automobile, garage, mécanique, nettoyage auto, carrosserie, contrôle technique, location voiture, Martinique, Guadeloupe, Réunion, France, Automoclick, automoclick.com">
  <meta name="author" content="Automoclick">
  <link rel="android-chrome-192x192" sizes="192x192" href="img/android-chrome-192x192.png">
  <link rel="android-chrome-512x512" sizes="512x512" href="img/android-chrome-512x512.png">
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
  <link rel="icon" type="image/x-icon" href="img/favicon.ico">
  <link rel="manifest" href="img/site.webmanifest">

  <title>Automoclick - Trouvez un professionnel auto en toute simplicité</title>
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7793920217648796"
     crossorigin="anonymous"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- <script src="index.js"></script> -->
   <script src="config.js"></script>
  <script nonce="<?= htmlspecialchars($nonce) ?>">
    function chargerNombreMessagesNonLus() {
      $.post("check-message", {}, function (response) {
        const badge = $('#message-badge');
        if (response.nb_non_lus > 0) {
          badge.text(response.nb_non_lus > 99 ? '99+' : response.nb_non_lus);
          badge.removeClass('hidden');
        } else {
          badge.addClass('hidden');
        }
      }, 'json').fail(() => {
        console.warn("Erreur lors du chargement des messages non lus");
      });
    }
    setInterval(chargerNombreMessagesNonLus, 12000);
    chargerNombreMessagesNonLus();

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

    #loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(255, 255, 255);
      /* fond semi-transparent */
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;

    }

    .spinner {
      width: 60px;
      height: 60px;
      border: 6px solid #ccc;
      border-top: 6px solid #0a8d48;
      /* couleur principale */
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    #overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease;
      z-index: 999;
    }

    #overlay.active {
      opacity: 1;
      visibility: visible;
    }

    #message-badge {
      background-color: #ef4444;
      /* Tailwind rouge-500 */
      color: white;
      font-size: 6px;
      font-weight: bold;
      min-width: 16px;
      height: 16px;
      padding: 0 4px;
      border-radius: 9999px;
      display: flex;
      align-items: center;
      justify-content: center;
      position: absolute;
      top: -4px;
      right: -6px;
      line-height: 1;
      z-index: 10;
      box-shadow: 0 0 0 2px white;
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-800">
  <div id="loader">
    <div class="spinner"></div>
  </div>

  <?php include ('includes/dropdown.php'); ?>
  <div id="overlay" class="overlay"></div>
  <section class="relative bg-cover bg-center bg-no-repeat pt-20 pb-16 " style="background-image: url('img/city.png');">
    <div class="absolute inset-0 bg-white bg-opacity-60"></div>

    <div class="relative max-w-7xl mx-auto px-6 lg:px-8 flex flex-col-reverse lg:flex-row items-center gap-12">
      <div class="w-full lg:w-1/2">
        <div class="max-w-lg mx-auto">
          <h1 class="text-4xl font-extrabold leading-tight text-gray-900 mb-6">
            Automoclick, trouvez un professionnel auto en toute simplicité
          </h1>
          <p class="text-gray-600 mb-8 leading-relaxed">
            Automoclick vous connecte avec les meilleurs experts de votre région.
            Réservez en ligne, payez en toute sécurité, et suivez vos rendez-vous simplement.
          </p>

          <form action="view-pro" method="POST"
            class="bg-gray-100 rounded-md p-8 flex flex-col sm:flex-row flex-wrap gap-4 shadow-md w-max mx-auto sm:mx-0">

            <input type="text" name="keywords" placeholder="Rechercher par mots-clés (ex: vidange, pneus)"
              class="w-full sm:w-96 rounded-md border border-gray-300 px-4 py-3 text-gray-800 placeholder-gray-500 focus:border-green-500 focus:ring-1 focus:ring-green-500" />

            <select name="specialisation"
              class="w-full sm:w-96 rounded-md border border-gray-300 px-8 py-8 sm:py-3 text-gray-800 focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">Sélectionnez la spécialisation</option>
              <option value="mecanique">Mécanique</option>
              <option value="depanneur">Dépanneur</option>
              <option value="carrosserie">Carrossier(e)</option>
              <option value="controle">Contrôleur(se) technique</option>
              <option value="electro">Électromécanicien(ne)</option>
              <option value="garage">Garage</option>
              <option value="nettoyage">Nettoyage</option>
              <option value="peintre">Peintre</option>
              <option value="soudeur">Soudeur(se)</option>
              <option value="prepa">Préparateur automobile</option>
              <option value="vendeur-auto">Vendeur de véhicule</option>
              <option value="loueur">Location de véhicule</option>
              <option value="tunning">Tunning</option>
            </select>

            <select name="pays"
              class="w-full sm:w-60 rounded-md border border-gray-300 px-8 py-8 sm:py-3 text-gray-800 focus:border-green-500 focus:ring-1 focus:ring-green-500">
              <option value="">Sélectionnez un pays</option>
              <option value="fr">France</option>
              <option value="mq">Martinique</option>
              <option value="gp">Guadeloupe</option>
              <option value="gf">Guyane</option>
              <option value="rn">Réunion</option>
              <option value="yt">Mayotte</option>
            </select>

            <button type="submit"
              class="bg-green-600 hover:bg-green-700 text-white rounded-md px-6 py-3 font-semibold transition">
              Rechercher
            </button>
          </form>
        </div>
      </div>
      <div class="w-full lg:w-1/2 flex justify-center items-center lg:-mt-52">
        <img src="img/avatar3.png" alt="Voiture et professionnel auto"
          class="w-full max-w-xs lg:max-w-sm max-h-64 object-contain" />
      </div>
    </div>
  </section>
  <section
    class="bg-green-600 py-14 text-white text-center rounded-tl-[80px] rounded-br-[80px] max-w-7xl mx-auto my-20 px-6">
    <h2 class="text-3xl md:text-4xl font-extrabold mb-4">À vos marques, prêts, clickez ?</h2>
    <p class="text-lg mb-8 max-w-2xl mx-auto">Recherchez parmi des centaines d’experts qualifiés près de chez vous.</p>
    <a href="pro"
      class="inline-block bg-white text-green-600 font-semibold rounded-lg px-10 py-4 text-lg hover:bg-gray-100 transition">Commencer
      votre recherche</a>
  </section>
  <section id="prestataires" class="bg-gray-50 py-16">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 text-center">
      <h2 class="text-3xl font-bold text-gray-900 mb-12">Pourquoi choisir Automoclick ?</h2>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-10 max-w-4xl mx-auto">
        <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-xl transition cursor-default">
          <svg class="mx-auto mb-5 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" width="48" height="48" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round">
            <path d="M9 12l2 2l4-4" />
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" />
          </svg>
          <h3 class="text-xl font-semibold mb-2">Professionnels Vérifiés</h3>
          <p class="text-gray-600">Tous nos prestataires sont contrôlés et évalués pour garantir qualité et confiance.
          </p>
        </div>
        <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-xl transition cursor-default">
          <svg class="mx-auto mb-5 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" width="48" height="48" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" />
            <path d="M12 6v6l4 2" />
          </svg>
          <h3 class="text-xl font-semibold mb-2">Réservation Rapide</h3>
          <p class="text-gray-600">Réservez votre prestation en quelques clics, sans prise de tête ni attente.</p>
        </div>
        <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-xl transition cursor-default">
          <svg class="mx-auto mb-5 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" width="48" height="48" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round">
            <path d="M12 1v22" />
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
          </svg>

          <h3 class="text-xl font-semibold mb-2">Paiement Sécurisé</h3>
          <p class="text-gray-600">Effectuez vos paiements directement sur la plateforme en toute sécurité.</p>
        </div>
      </div>
    </div>
  </section>
  <section class="max-w-5xl mx-auto p-6 bg-white shadow-md rounded-lg my-8">
  <h1 class="text-3xl font-bold text-gray-800 mb-6">FAQ (Foire Aux Questions)</h1>

  <!-- Question 1 -->
  <div class="mb-4">
    <button class="w-full text-left p-4 bg-gray-100 rounded-md focus:outline-none flex justify-between items-center faq-toggle">
      <span class="font-semibold text-gray-700">1. Qu’est-ce qu’Automoclick ?</span>
      <span class="text-gray-500">+</span>
    </button>
    <div class="faq-content hidden p-4 text-gray-600 bg-gray-50 rounded-b-md">
      Automoclick est une plateforme en ligne qui vous permet de trouver des professionnels de l’entretien automobile, de réserver des prestations et de gérer vos rendez-vous facilement.
    </div>
  </div>

  <!-- Question 2 -->
  <div class="mb-4">
    <button class="w-full text-left p-4 bg-gray-100 rounded-md focus:outline-none flex justify-between items-center faq-toggle">
      <span class="font-semibold text-gray-700">2. Comment réserver une prestation ?</span>
      <span class="text-gray-500">+</span>
    </button>
    <div class="faq-content hidden p-4 text-gray-600 bg-gray-50 rounded-b-md">
      Sélectionnez le professionnel, choisissez la prestation et le créneau horaire souhaité, puis validez votre réservation en ligne.
    </div>
  </div>

  <!-- Question 3 -->
  <div class="mb-4">
    <button class="w-full text-left p-4 bg-gray-100 rounded-md focus:outline-none flex justify-between items-center faq-toggle">
      <span class="font-semibold text-gray-700">3. Quels moyens de paiement sont acceptés ?</span>
      <span class="text-gray-500">+</span>
    </button>
    <div class="faq-content hidden p-4 text-gray-600 bg-gray-50 rounded-b-md">
       Vous pouvez régler par carte bancaire sécurisée au moment de la réservation.
    </div>
  </div>

  <!-- Question 4 -->
  <div class="mb-4">
    <button class="w-full text-left p-4 bg-gray-100 rounded-md focus:outline-none flex justify-between items-center faq-toggle">
      <span class="font-semibold text-gray-700">4. Puis-je annuler ou modifier ma réservation ?</span>
      <span class="text-gray-500">+</span>
    </button>
    <div class="faq-content hidden p-4 text-gray-600 bg-gray-50 rounded-b-md">
      Les annulations ou modifications dépendent des conditions du professionnel. En général, il est possible de modifier votre rendez-vous avant 24 heures de l’horaire prévu.
    </div>
  </div>

  <!-- Question 5 -->
  <div class="mb-4">
    <button class="w-full text-left p-4 bg-gray-100 rounded-md focus:outline-none flex justify-between items-center faq-toggle">
      <span class="font-semibold text-gray-700">5. Comment créer un compte Automoclick ?</span>
      <span class="text-gray-500">+</span>
    </button>
    <div class="faq-content hidden p-4 text-gray-600 bg-gray-50 rounded-b-md">
      Cliquez sur « S’inscrire » depuis la page d’accueil, remplissez vos informations et confirmez votre email pour activer votre compte.
    </div>
  </div>

  <!-- Question 6 -->
  <div class="mb-4">
    <button class="w-full text-left p-4 bg-gray-100 rounded-md focus:outline-none flex justify-between items-center faq-toggle">
      <span class="font-semibold text-gray-700">6. Comment contacter le support ?</span>
      <span class="text-gray-500">+</span>
    </button>
    <div class="faq-content hidden p-4 text-gray-600 bg-gray-50 rounded-b-md">
      Vous pouvez nous contacter par email à <a href="mailto:support@automoclick.com" class="text-blue-600 underline">support@automoclick.com</a>.
    </div>
  </div>
</section>
  <!-- Assistant Jérémy - Automoclick -->
  <div id="jeremy-assistant" class="fixed bottom-16 right-6 z-50 flex flex-col items-end space-y-2 max-w-full">

    <!-- Bouton flottant -->
    <button id="open-chatbot"
      class="bg-green-600 hover:bg-green-700 text-white rounded-full shadow-lg p-4 flex items-center space-x-2 animate-bounce max-w-[200px]">
      <img src="img/avatar.png" alt="Jeremy Avatar" class="w-8 h-8 rounded-full flex-shrink-0">
      <span class="hidden sm:inline truncate">Parler à Jérémy</span>
    </button>

    <!-- Fenêtre de chat -->
    <div id="chatbot-window"
      class="hidden mt-2 w-80 max-w-full bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col animate-fadeInUp">

      <div class="bg-green-600 text-white p-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <img src="img/avatar.png" alt="Jeremy Avatar" class="w-10 h-10 rounded-full">
          <h2 class="text-lg font-semibold select-none">Jérémy</h2>
        </div>
        <button id="close-chatbot" aria-label="Fermer la fenêtre de chat"
          class="text-white hover:text-gray-200 font-bold text-xl leading-none">&times;</button>
      </div>

      <div id="chat-messages" class="p-4 h-96 overflow-y-auto space-y-3 text-sm bg-gray-50 flex-grow">
        <div class="text-gray-600">Bonjour, je suis <strong>Jérémy</strong>, ton assistant virtuel !</div>
      </div>

      <div class="p-4 bg-white border-t flex items-center space-x-2">
        <input id="chat-input" type="text" placeholder="Pose ta question..."
          class="flex-1 p-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-green-600" />
        <button id="send-chat"
          class="bg-green-600 hover:bg-green-700 text-white p-3 rounded-xl flex items-center justify-center"
          aria-label="Envoyer">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </button>
      </div>
    </div>
  </div>

  <style>
    @keyframes fadeInUp {
      0% {
        opacity: 0;
        transform: translateY(20px);
      }

      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-fadeInUp {
      animation: fadeInUp 0.5s ease-out;
    }
  </style>

  <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', () => {
      const openBtn = document.getElementById('open-chatbot');
      const closeBtn = document.getElementById('close-chatbot');
      const chatbot = document.getElementById('chatbot-window');
      const input = document.getElementById('chat-input');
      const sendBtn = document.getElementById('send-chat');
      const messages = document.getElementById('chat-messages');

      openBtn.addEventListener('click', () => {
        openBtn.classList.add('hidden');
        chatbot.classList.remove('hidden');
        input.focus();
      });

      closeBtn.addEventListener('click', () => {
        chatbot.classList.add('hidden');
        openBtn.classList.remove('hidden');
      });

      function appendMessage(text, from = 'user') {
        const msg = document.createElement('div');
        msg.className = from === 'user' ? 'text-right text-gray-800' : 'text-left text-green-800';
        msg.innerText = text;
        messages.appendChild(msg);
        messages.scrollTop = messages.scrollHeight;
      }

      // Fonction pour afficher le loader "Jérémy réfléchit..."
      function appendLoading() {
        const loader = document.createElement('div');
        loader.className = 'text-left text-green-800';
        loader.id = 'loading-message';
        loader.innerText = 'Jérémy réfléchit';
        let dots = 0;

        const interval = setInterval(() => {
          dots = (dots + 1) % 4;
          loader.innerText = 'Jérémy réfléchit' + '.'.repeat(dots);
          messages.scrollTop = messages.scrollHeight;
        }, 500);

        messages.appendChild(loader);
        messages.scrollTop = messages.scrollHeight;

        return () => {
          clearInterval(interval);
          loader.remove();
        };
      }

      function sendMessage() {
        const text = input.value.trim();
        if (!text) return;
        appendMessage(text, 'user');
        input.value = '';

        // Affiche le loader et récupère la fonction pour le retirer
        const removeLoading = appendLoading();

        fetch('/chatbot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: text })
        })
          .then(res => res.json())
          .then(data => {
            removeLoading();
            appendMessage(data.response, 'bot');
          })
          .catch(err => {
            removeLoading();
            appendMessage("Désolé, une erreur est survenue...", 'bot');
            console.error(err);
          });
      }

      sendBtn.addEventListener('click', sendMessage);
      input.addEventListener('keydown', e => {
        if (e.key === 'Enter') sendMessage();
      });
    });
const toggles = document.querySelectorAll('.faq-toggle');
  toggles.forEach(btn => {
    btn.addEventListener('click', () => {
      const content = btn.nextElementSibling;
      content.classList.toggle('hidden');
      btn.querySelector('span:last-child').textContent = content.classList.contains('hidden') ? '+' : '−';
    });
  });
  </script>


<?php include('includes/footer.php'); ?>

  <script nonce="<?= htmlspecialchars($nonce) ?>">
    // Toggle mobile menu
    // const btn = document.getElementById('mobile-menu-button');
    // const menu = document.getElementById('mobile-menu');

    // btn.addEventListener('click', () => {
    //   menu.classList.toggle('hidden');
    // });

    window.addEventListener("load", function () {
      $('#loader').fadeOut(2000);
    });
  </script>
</body>

</html>