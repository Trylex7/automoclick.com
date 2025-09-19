<?php
session_start();
require '../../../db/dbconnect2.php';

// Clé secrète à définir (même que pour chiffrer les URLs)
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

// Fonctions de chiffrement / déchiffrement
function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}

function dechiffrer($texte_chiffre, $cle)
{
    return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}

// Vérification du paramètre GET
if (!isset($_GET['p'])) {
    http_response_code(400);
    die("Chat ID manquant.");
}

$chat_id_dechiffre = dechiffrer($_GET['p'], $cle_secrete);

if (!$chat_id_dechiffre || !is_numeric($chat_id_dechiffre)) {
    http_response_code(400);
    die("ID de chat invalide.");
}

$chat_id = (int)$chat_id_dechiffre;

// Vérification que le chat existe
$stmt = $db->prepare("SELECT * FROM chats WHERE id = ?");
$stmt->execute([$chat_id]);
$chat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chat) {
    http_response_code(404);
    die("Chat introuvable.");
}

// Récupération de l'ID de l'utilisateur connecté
$id_interne_user = null;

if (isset($_SESSION['id_client'])) {
    $stmt2 = $db->prepare("SELECT id FROM users WHERE numero = ?");
    $stmt2->execute([$_SESSION['id_client']]);
    $user = $stmt2->fetch(PDO::FETCH_ASSOC);
    $id_interne_user = $user['id'] ?? null;
} elseif (isset($_SESSION['id_pro'])) {
    $stmt2 = $db->prepare("SELECT id FROM users WHERE numero = ?");
    $stmt2->execute([$_SESSION['id_pro']]);
    $user = $stmt2->fetch(PDO::FETCH_ASSOC);
    $id_interne_user = $user['id'] ?? null;
}

if (!$id_interne_user) {
    http_response_code(403);
    die("Utilisateur non connecté ou introuvable.");
}

// Vérifie si l'utilisateur est bien lié à ce chat
if ($id_interne_user != $chat['client_id'] && $id_interne_user != $chat['pro_id']) {
    http_response_code(403);
    die("Accès interdit à ce chat.");
}

// ✅ À partir d'ici, tu peux afficher les messages ou la conversation
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Chat Client-Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        #messages::-webkit-scrollbar {
            width: 6px;
        }

        #messages::-webkit-scrollbar-thumb {
            background-color: #9ca3af;
            border-radius: 3px;
        }
    </style>
</head>

<body class="flex flex-col h-screen bg-gray-100 font-sans">

    <!-- Header -->
   <header class="fixed top-0 left-0 right-0 bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-4 shadow-md flex justify-between items-center z-50">
        <h1 class="font-semibold text-lg sm:text-xl">Chat Client - Pro</h1>
    </header>
    <div id="messages" class="flex flex-col gap-4 p-4 pt-20 pb-24 flex-grow overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent">
    <!-- Messages ici -->
</div>

<footer class="fixed bottom-0 left-0 right-0 p-3 bg-white flex items-center gap-2 border-t border-gray-200 shadow-md">
    <input id="message" 
           type="text" 
           placeholder="Écrivez un message..." 
           autocomplete="off"
           class="flex-grow rounded-2xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder-gray-400 shadow-sm" />
    
    <button id="envoyerBtn"
        class="bg-green-600 text-white px-5 py-2 rounded-2xl text-sm font-medium shadow-md hover:bg-green-700 transition flex items-center gap-1">
        <span class="material-icons text-sm">send</span>
        Envoyer
    </button>
</footer>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        const messageInput = document.getElementById("message");

messageInput.addEventListener('focus', () => {
    setTimeout(() => {
        $('#messages').scrollTop($('#messages')[0].scrollHeight);
    }, 300); // Attend que le clavier s’affiche
});

messageInput.addEventListener('blur', () => {
    setTimeout(() => {
        $('#messages').scrollTop($('#messages')[0].scrollHeight);
    }, 300);
});
        const chat_id = <?= json_encode($chat_id) ?>;
        const user_id = <?= json_encode($id_interne_user) ?>;

        function escapeHtml(text) {
            return text.replace(/[&<>"'`=\/]/g, s => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
                "'": '&#39;', '`': '&#96;', '=': '&#61;', '/': '&#47;'
            })[s]);
        }

    function chargerMessages() {
    $.post("envoie-chat", { chat_id }, function (data) {
        if (data.error) return alert(data.error);

        let html = "";
        let destinataireNom = "";

        data.forEach(msg => {
            const isCurrentUser = msg.auteur_id == user_id;

            // 👇 On récupère le premier nom qui n'est pas celui de l'utilisateur
            if (!isCurrentUser && !destinataireNom) {
                destinataireNom = `${msg.prenom ?? ''} ${msg.nom ?? ''}`.trim() || "Destinataire";
            }

            const bubbleClass = isCurrentUser ?
                "bg-green-400 text-white rounded-bl-3xl rounded-tl-3xl rounded-tr-xl rounded-br-xl max-w-[70%] p-4 shadow-md" :
                "bg-gray-200 text-gray-900 rounded-br-3xl rounded-tr-3xl rounded-tl-xl rounded-bl-xl max-w-[70%] p-4 shadow-md";

            const containerClass = isCurrentUser ? "flex justify-end" : "flex justify-start";

            const name = `${msg.prenom ?? ''} ${msg.nom ?? ''}`.trim() || "Utilisateur";
            const time = msg.date_envoi ?
                new Date(msg.date_envoi).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

            const vu = msg.lu == 1;
            const checkIcon = isCurrentUser ? `
                <span class="material-icons text-sm ml-2 ${vu ? 'text-blue-500' : 'text-black-400'} align-middle">
                    done_all
                </span>` : '';

            html += `
                <div class="${containerClass}">
                    <div class="${bubbleClass}">
                        <div class="font-semibold mb-1 text-sm">${escapeHtml(name)}</div>
                        <div class="whitespace-pre-wrap break-words text-base leading-relaxed">${escapeHtml(msg.message)}</div>
                        <div class="text-xs text-black-100/80 text-right mt-2 flex items-center justify-end">
                            ${time}${checkIcon}
                        </div>
                    </div>
                </div>
            `;
        });

        // 👇 Mettre à jour le titre du header
        if (destinataireNom) {
            document.querySelector("header h1").textContent =  destinataireNom;
        }

        const wasScrolledToBottom = $('#messages')[0].scrollHeight - $('#messages').scrollTop() <= $('#messages').outerHeight() + 50;

        $('#messages').html(html);
        if (wasScrolledToBottom) {
            $('#messages').scrollTop($('#messages')[0].scrollHeight);
        }
    }, 'json').fail(() => alert("Erreur lors du chargement des messages."));
}

        function envoyerMessage() {
            const message = $('#message').val().trim();
            if (!message) return;

            $.post("envoie-chat", { chat_id, message }, function (response) {
                if (response.success) {
                    $('#message').val('');
                    chargerMessages();
                } else {
                    alert(response.error || 'Erreur lors de l’envoi');
                }
            }, 'json').fail(() => alert("Erreur lors de l’envoi du message."));
        }

        document.getElementById("envoyerBtn").addEventListener("click", envoyerMessage);
        document.getElementById("message").addEventListener("keypress", function(e) {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                envoyerMessage();
            }
        });

        setInterval(chargerMessages, 2000);
        chargerMessages();
    </script>
</body>


</html>