<?php
require_once '../header.php';
require_once '../api/traker.php';
session_start();
if (!isset($_SESSION['id_client'])) {
    header('Location: /');
}
require '../db/dbconnect2.php';
require_once '../includes/webhook.php';
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

function dechiffrer($texte_chiffre, $cle)
{
    $decoded = base64_decode(urldecode($texte_chiffre));
    if ($decoded === false)
        return false;
    $dechiffre = openssl_decrypt($decoded, 'AES-128-ECB', $cle);
    return $dechiffre ?: false;
}

$pro_numero = isset($_GET['z']) ? dechiffrer($_GET['z'], $cle_secrete) : null;
$client_numero = isset($_GET['a']) ? dechiffrer($_GET['a'], $cle_secrete) : null;

if (!$pro_numero || !$client_numero) {
    die("Paramètres invalides ou manquants.");
}

// === 1. CLIENT ===
$stmt = $db->prepare("SELECT id FROM users WHERE numero = ?");
$stmt->execute([$client_numero]);
$client = $stmt->fetch();

if ($client) {
    $client_id = $client['id'];
} else {
    // Essai de récupérer le nom/prénom depuis login_user
    $stmt2 = $db->prepare("SELECT nom, prenom FROM login_user WHERE numero_client = ?");
    $stmt2->execute([$client_numero]);
    $client_info = $stmt2->fetch();

    $nom = $client_info['nom'] ?? 'Client';
    $prenom = $client_info['prenom'] ?? '';

    // Création dans users
    $stmt2 = $db->prepare("INSERT INTO users (nom, prenom, numero, role) VALUES (?, ?, ?, 'client')");
    $stmt2->execute([$nom, $prenom, $client_numero]);
    $client_id = $db->lastInsertId();
}

// === 2. PRO ===
$stmt = $db->prepare("SELECT id FROM users WHERE numero = ?");
$stmt->execute([$pro_numero]);
$pro = $stmt->fetch();

if ($pro) {
    $pro_id = $pro['id'];
} else {
    // Essai de récupérer la dénomination depuis entreprises
    $stmt2 = $db->prepare("SELECT denomination, email FROM entreprises WHERE numero_pro = ?");
    $stmt2->execute([$pro_numero]);
    $pro_info = $stmt2->fetch();
    $email = $pro_info['email'];
    $denomination = $pro_info['denomination'] ?? 'Professionnel';

    // Création dans users
    $stmt2 = $db->prepare("INSERT INTO users (nom, numero, role) VALUES (?, ?, 'pro')");
    $stmt2->execute([$denomination, $pro_numero]);
    $pro_id = $db->lastInsertId();
}

// === 3. Recherche ou création du chat ===
$stmt = $db->prepare("SELECT id FROM chats WHERE client_id = ? AND pro_id = ?");
$stmt->execute([$client_id, $pro_id]);
$chat = $stmt->fetch();

if ($chat) {
    $chat_id = $chat['id'];
} else {
    $stmt = $db->prepare("INSERT INTO chats (client_id, pro_id) VALUES (?, ?)");
    $stmt->execute([$client_id, $pro_id]);
    $chat_id = $db->lastInsertId();
    $to = $email;
    $subject = 'Vous avez recu un nouveau message !';
    $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau chat !</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .email-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: black;
            padding: 20px;
            border: 1px solid #dddddd;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px;
            text-align: center;
        }
        th {
            background-color: black;
            color: #ffffff;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: white;
            padding: 20px;
        }
        .logo {
            width: 150px;
            margin: 0 auto;
            display: block;
        }
        .title {
            text-align: center;
            color: white;
            margin-top: 20px;
        }
        .title_color {
            color: #58b88a;
        }
        .c_btn {
            display: inline-block;
            text-align: center;
            color: white;
            font-size: 20px;
            padding: 15px 30px;
            background-color:  #58b88a;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
        }
        .text_footer {
            text-align: center;
            color: white;
        }
        a {
            color: #58b88a;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            body { padding: 10px; }
            .email-container { width: 100%; padding: 15px; }
            .logo { width: 120px; }
            .c_btn { font-size: 18px; padding: 12px 25px; }
            th, td { padding: 10px; }
            .footer { font-size: 10px; color: white; }
        }
    </style>
</head>
<body>
    <div style="display:none;max-height:0px;overflow:hidden;color:white">Nouveau chat !</div>
    <div class="email-container">
        <table>
            <thead>
                <tr>
                    <th colspan="2">
                        <a href="https://automoclick.com">
                            <img class="logo" src="https://automoclick.com/img/logo-automoclick.png" alt="Logo Automoclick">
                        </a>
                     <div class="title">Une nouvelle discussion <span class="title_color">a été initiée</span></div>

                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="color:white">
                        Bonjour,<br><br>
                        Vous avez recu un nouveau chat, acceder au chat en cliquant sur le lien ci-dessous :<br><br>
                        <a class="c_btn" style="color:white"  href="https://automoclick.com/message">Ouvrir la discussion</a><br><br>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="footer">
                        <p>Cordialement,</p>
                        L\'équipe Automoclick<br><br>
                        <a target="_blank" style="color:#58b88a" href="https://instagram.com/automoclick">Suivez-nous sur Instagram</a><br><br>
                        <div class="text_footer">Merci de faire confiance</div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
';
    $headers = 'From: Automoclick <no-reply@automoclick.com>' . "\r\n" .
        'Reply-To: no-reply@automoclick.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion() . "\r\n" .
        'Content-Type: text/html; charset=UTF-8' . "\r\n" .
        'Content-Transfer-Encoding: 8bit';
    if (!mail($to, $subject, $message, $headers)) {
        echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
    }
}
$id_interne_user = null;

if (isset($_SESSION['id_client'])) {
    $numero_session = $_SESSION['id_client'];
} elseif (isset($_SESSION['id_pro'])) {
    $numero_session = $_SESSION['id_pro'];
}

if (!empty($numero_session)) {
    $stmt = $db->prepare("SELECT id FROM users WHERE numero = ?");
    $stmt->execute([$numero_session]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $id_interne_user = $user['id'];
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Chat Client-Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
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
    <header
        class="bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-4 shadow-md flex justify-between items-center">
        <h1 class="font-semibold text-lg sm:text-xl">Chat Client - Pro</h1>
        <span class="text-sm opacity-90">Connecté</span>
    </header>
    <div id="messages"
        class="flex flex-col gap-4 p-4 flex-grow overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent">

    </div>

    <!-- Footer input -->
    <footer class="p-3 bg-white flex items-center gap-2 border-t border-gray-200 shadow-md">
        <input id="message" type="text" placeholder="Écrivez un message..." autocomplete="off"
            class="flex-grow rounded-2xl border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder-gray-400 shadow-sm" />

        <button id="envoyerBtn"
            class="bg-green-600 text-white px-5 py-2 rounded-2xl text-sm font-medium shadow-md hover:bg-green-700 transition flex items-center gap-1">
            <span class="material-icons text-sm">send</span>
            Envoyer
        </button>
    </footer>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
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
                    document.querySelector("header h1").textContent = "Chat avec " + destinataireNom;
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
        document.getElementById("message").addEventListener("keypress", function (e) {
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