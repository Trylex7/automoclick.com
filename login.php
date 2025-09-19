<?php
define('SECRET_KEY', '70909e102fead6703222e2abd1cb74c7aa1542b4d79ff897bf2ad884a60325d5');
require_once 'vendor/autoload.php';
require_once 'header.php';
require_once 'includes/webhook.php';
require_once 'api/traker.php';
session_start();
if (!empty($_SESSION['id_client']) || !empty($_SESSION['id_pro'])) {
    header('Location: /');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;

$user_agent = $_SERVER['HTTP_USER_AGENT'];

$dd = new DeviceDetector($user_agent);
$dd->parse();

if ($dd->isBot()) {
    $device = "Bot: " . $dd->getBot()['name'];
} else {
    $device = $dd->getDeviceName();
    $brand = $dd->getBrandName();
    $model = $dd->getModel();
}

require_once('db/dbconnect.php');
require_once('db/dbconnect2.php');

if (isset($_POST['connexion'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Erreur CSRF : requête non autorisée.');
    }

    if (!empty($_POST['identifiant']) && !empty($_POST['mdp'])) {
        $identifiant = $_POST['identifiant'];
        $mdp = $_POST['mdp'];

        $sth = $db->prepare("
            SELECT 
                id_client AS id, 
                nom, 
                prenom, 
                email, 
                mdp, 
                'user' AS type,
                NULL AS numero_pro
            FROM login_user 
            WHERE email = ?

            UNION

            SELECT 
                id, 
                NULL AS nom, 
                NULL AS prenom, 
                numero_pro AS email, 
                mdp, 
                'pro' AS type,
                numero_pro
            FROM login_pro 
            WHERE numero_pro = ?
        ");

        $sth->execute([$identifiant, $identifiant]);
        $users = $sth->fetchAll(PDO::FETCH_ASSOC);

        $loggedIn = false;
        function getIp()
        {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            return $ip;
        }
        function get_browser_name($user_agent)
        {
            if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/'))
                return 'Opera';
            elseif (strpos($user_agent, 'Edge'))
                return 'Edge';
            elseif (strpos($user_agent, 'Chrome'))
                return 'Chrome';
            elseif (strpos($user_agent, 'Safari'))
                return 'Safari';
            elseif (strpos($user_agent, 'Firefox'))
                return 'Firefox';
            elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7'))
                return 'Internet Explorer';

            return 'Other';
        }
        foreach ($users as $data) {
            if (password_verify($mdp, $data['mdp'])) {
                if ($data['type'] === 'user') {
                    $recup_data_c = $db->prepare('SELECT * FROM login_user WHERE email = ?');
                    $recup_data_c->execute([$data['email']]);
                    $data_c = $recup_data_c->fetch();
                    $_SESSION['session_id'] = session_id();
                    $_SESSION['id_client'] = $data_c['numero_client'];
                    $_SESSION['nom'] = htmlspecialchars($data_c['nom']);
                    $_SESSION['prenom'] = htmlspecialchars($data_c['prenom']);
                    $_SESSION['email'] = htmlspecialchars($data_c['email']);
                    $_SESSION['role'] = "client";
                    $payload = $_SESSION['role'] . '|' . $_SESSION['id_client'] . '|' . $_SESSION['nom'] . '|' . $_SESSION['prenom'];
                    $signature = hash('sha256', $payload . SECRET_KEY);
                    $donnees_user = base64_encode($payload);
                    $cookie_value = $donnees_user . '|' . $signature;
                } elseif ($data['type'] === 'pro') {
                    $recup_data_pro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
                    $recup_data_pro->execute([$data['numero_pro']]);
                    $data_pro = $recup_data_pro->fetch();
                    $recup_data_log = $db->prepare('SELECT * FROM login_pro WHERE numero_pro = ?');
                    $recup_data_log->execute([$data['numero_pro']]);
                    $users_pro = $recup_data_log->fetchAll();
                    $data_log = null;


                    foreach ($users_pro as $user) {
                        if (password_verify($mdp, $user['mdp'])) {
                            $data_log = $user;
                            break;
                        }
                    }
                    if ($data_log) {
                        $_SESSION['role'] = "pro";
                        $_SESSION['role2'] = $data_log['role'];
                        $_SESSION['session_id'] = session_id();
                        $_SESSION['id_pro'] = $data_pro['numero_pro'];
                        $_SESSION['email'] = htmlspecialchars($data_pro['email']);
                        $_SESSION['name_company'] = $data_pro['denomination'];
                        $_SESSION['siret'] = $data_pro['siret'];
                        $_SESSION['adresse'] = $data_pro['adresse'];
                        $_SESSION['siren'] = $data_pro['siren'];
                    }
                    $payload = implode('|', [
                        $_SESSION['role'],
                        $_SESSION['id_pro'],
                        $_SESSION['name_company'],
                        $_SESSION['siret'],
                        $_SESSION['adresse'],
                        $_SESSION['siren']
                    ]);
                    $signature = hash('sha256', $payload . SECRET_KEY);
                    $donnees_user = base64_encode($payload);
                    $cookie_value = $donnees_user . '|' . $signature;
                    $session_id = session_id();
                    $device_info = $_SERVER['HTTP_USER_AGENT'];
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $last_activity = date('Y-m-d H:i:s');

                    $stmt = $db->prepare("INSERT INTO sessions_pro (numero_pro, session_id, device_info, ip_address, last_activity) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['id_pro'], $session_id, $device_info, $ip_address, $last_activity]);
                }
                session_regenerate_id(true);
                setcookie("cookies_user", $cookie_value, [
                    'expires' => time() + 60 * 60 * 24 * 30, // 30 jours
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);



                $ip = getIp();
                $apiKey = 'B95348A5DFAB8F110F0E97133523300F';

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.ip2location.io/?' . http_build_query([
                    'ip' => $ip,
                    'key' => $apiKey,
                    'format' => 'json',
                ]));
                curl_setopt($ch, CURLOPT_FAILONERROR, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $response = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($response, true);
                $region = (isset($data['region_name']) && isset($data['city_name'])) ? $data['region_name'] . ', ' . $data['city_name'] : 'Ville inconnue';
                $to = $_SESSION['email'];
                $subject = 'Nouvelle connexion';
                $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle activité détectée</title>
       <style>
        /* RESET */
        body, table, td, p {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }
        img {
            border: 0;
            display: block;
            max-width: 100%;
            height: auto;
        }

        /* CONTAINER */
        .email-container { 
            width: 100%; 
            max-width: 600px; 
            margin: 0 auto; 
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
        }

        /* HEADER */
        .header {
            background: #059669;
            padding: 30px 20px;
            text-align: center;
            color: #ffffff;
        }
        .logo { 
            width: 120px; 
            margin: 0 auto 15px; 
            display: block;
        }
        .title { 
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        .subtitle {
            font-size: 14px;
            margin-top: 8px;
            color: #d1fae5;
        }

        /* CONTENT */
        .content {
            padding: 20px;
            text-align: left;
            font-size: 15px;
            color: #374151;
        }
        .message {
            margin-bottom: 20px;
        }

        /* CTA BUTTON */
        .cta-btn {
            display: inline-block;
            background: #10b981;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
        }

        /* ACTIVITY BOX */
        .activity-box {
            background: #f0fdf4;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #059669;
        }
        .activity-box p {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #166534;
        }
        .activity-box p:first-child {
            font-weight: bold;
            color: #059669;
        }

        /* WARNING */
        .warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 12px;
            margin: 15px 0;
            font-size: 14px;
            color: #92400e;
        }

        /* INFO BOX */
        .info-box {
            background: #f9fafb;
            border-left: 4px solid #059669;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #6b7280;
        }

        /* FOOTER */
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #6b7280;
        }
        .footer p {
            margin: 6px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: #059669;
            text-decoration: none;
            font-weight: 500;
        }

        /* MOBILE */
        @media only screen and (max-width: 600px) {
            .email-container { 
                width: 100% !important; 
                border-radius: 0 !important; 
            }
            .header { padding: 20px !important; }
            .title { font-size: 20px !important; }
            .content { padding: 15px !important; font-size: 14px !important; }
            .activity-box, .info-box, .warning {
                font-size: 13px !important;
                padding: 12px !important;
            }
            .footer { padding: 15px !important; font-size: 12px !important; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img class="logo" src="https://automoclick.com/img/logo-automoclick.png" alt="Automoclick">
            <h1 class="title">Nouvelle activité détectée</h1>
            <p class="subtitle">Restez informé de votre activité</p>
        </div>
        
        <div class="content">
            <div class="message">
                <p>Bonjour,</p>
                <p>Nous vous informons qu\'une nouvelle activité a été détectée sur votre compte Automoclick :</p>
                
                <div class="activity-box">
                    <p>Connexion depuis un nouvel appareil</p>
                    <p><strong>Appareil :</strong> ' . htmlspecialchars($brand . ' ' . $model) . '</p>
                    <p><strong>Navigateur :</strong> ' . htmlspecialchars(get_browser_name($user_agent)) . '</p>
                    <p><strong>Localisation :</strong> ' . htmlspecialchars($region) . '</p>
                    <p><strong>Adresse IP :</strong> ' . htmlspecialchars($ip) . '</p>
                    <p><strong>Date :</strong> Le ' . date('d/m/Y H:i') . '</p>
                </div>
                
                <p>Si c\'était vous, aucune action n\'est requise. Si vous ne reconnaissez pas cette activité, sécurisez immédiatement votre compte.</p>
            </div>           
            <div class="info-box">
                <p><strong> Sécurité de votre compte :</strong></p>
                <p>• Vérifiez régulièrement \'activité de votre compte</p>
                <p>• Utilisez un mot de passe unique et complexe</p>
                <p>• Déconnectez-vous sur les appareils partagés</p>
                <p>• Contactez le support en cas de doute</p>
            </div>
            
            <div class="warning">
                🚨 <strong>Activité suspecte ?</strong> Contactez immédiatement notre équipe de sécurité au support@automoclick.com
            </div>
        </div>
        
        <div class="footer">
            <p><strong>L\'équipe Automoclick</strong></p>
            <div class="social-links">
                <a href="https://instagram.com/automoclick" target="_blank">Instagram</a>
                <a href="https://automoclick.com/contact" target="_blank">Support</a>
            </div>
            <p>© ' . date('Y') . ' Automoclick - Tous droits réservés</p>
            <p style="margin-top: 10px; font-size: 12px;">
                Cet e-mail a été envoyé automatiquement, merci de ne pas y répondre.
            </p>
        </div>
    </div>
</body>
</html>
';
                $headers = 'From: Automoclick <no-reply@automoclick.com>' . "\r\n" .
                    'Reply-To: no-reply@automoclick.com' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion() . "\r\n" .
                    'Content-Type: text/html; charset=UTF-8' . "\r\n" .
                    'Content-Transfer-Encoding: 8bit';
                $loggedIn = true;

                if (!mail($to, $subject, $message, $headers)) {
                    echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
                }
                $redirect = '/';
                if (isset($_GET['success_url'])) {
                    $redirect = urldecode($_GET['success_url']);
                }

                header("Location: $redirect");
                exit;
            } else {
                $erreur_connection = "Votre identifiant ou votre mot de passe est incorrect";
            }
        }

    } else {
        $erreur_connection = "Votre identifiant n'existe pas !";
    }
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Connectez-vous pour accéder à votre espace personnel et gérer vos services" />
    <title>Connexion - Automoclick</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function view() {
            document.getElementById("mdp").type = "text";
            document.getElementById("view").style.display = "none";
            document.getElementById("hide").style.display = "inline";
        }
        function hide() {
            document.getElementById("mdp").type = "password";
            document.getElementById("view").style.display = "inline";
            document.getElementById("hide").style.display = "none";
        }
    </script>

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0..1,-50..200" />
</head>

<body class="min-h-screen flex items-center justify-center p-4">
    <form method="POST" class="bg-white w-full max-w-md rounded-2xl shadow-lg p-6 space-y-6 border border-green-100">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-green-700">Connexion</h1>
            <hr class="mt-2 border-green-300" />
        </div>

        <div>
            <label for="id" class="block text-sm font-medium text-gray-700">Identifiant</label>
            <input id="id" type="text" name="identifiant" placeholder="Identifiant" value="<?php if (isset($identifiant))
                echo $identifiant; ?>"
                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                required />
        </div>

        <div class="relative">
            <label for="mdp" class="block text-sm font-medium text-gray-700">Mot de passe</label>
            <input id="mdp" type="password" name="mdp" placeholder="Mot de passe" value="<?php if (isset($mdp))
                echo $mdp; ?>"
                class="mt-1 w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                required />

            <span id="view" class="material-symbols-outlined absolute top-[42px] right-3 text-gray-400 cursor-pointer">
                visibility
            </span>

            <span id="hide"
                class="material-symbols-outlined absolute top-[42px] right-3 text-gray-400 cursor-pointer hidden">
                visibility_off
            </span>
        </div>

        <div class="flex items-center space-x-2">
            <input type="checkbox" class="accent-green-600" checked />
            <label class="text-sm text-gray-600">Se souvenir de moi</label>
        </div>

        <?php if (isset($erreur_connection)): ?>
            <div class="text-sm text-red-600 font-medium text-center">
                <?= $erreur_connection ?>
            </div>
        <?php endif; ?>

        <button type="submit" name="connexion"
            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
            Se connecter
        </button>

        <div class="text-sm text-center">
            <a href="forgot" class="text-green-600 hover:underline">Mot de passe oublié ?</a>
        </div>

        <div class="text-sm text-center">
            Pas encore de compte ? <a href="inscription-particulier"
                class="text-green-600 hover:underline">Inscrivez-vous</a>
        </div>

        <div class="text-sm text-center">
            Vous êtes un professionnel ? <a href="inscription-pro" class="text-green-600 hover:underline">Inscrivez-vous
                ici</a>
        </div>
    </form>

</body>
<script nonce="<?= htmlspecialchars($nonce) ?>">
    const mdpInput = document.getElementById("mdp");
    const viewIcon = document.getElementById("view");
    const hideIcon = document.getElementById("hide");

    viewIcon.addEventListener("click", () => {
        mdpInput.type = "text";
        viewIcon.classList.add("hidden");
        hideIcon.classList.remove("hidden");
    });

    hideIcon.addEventListener("click", () => {
        mdpInput.type = "password";
        hideIcon.classList.add("hidden");
        viewIcon.classList.remove("hidden");
    });
</script>

</html>