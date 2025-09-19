    <?php
    require_once 'header.php';
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $_SESSION['url'] = $_SERVER['REQUEST_URI'];
    require_once('db/dbconnect2.php');
    require_once('db/dbconnect.php');
    require 'vendor/autoload.php';
    require_once 'api/traker.php';
    use \Firebase\JWT\JWT;
    function genererNumeroClient()
    {
        $prefix = 'CL';
        $date = date('Ymd');
        $unique = strtoupper(bin2hex(random_bytes(3)));
        return $prefix . '-' . $date . '-' . $unique;
    }

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


    // S'il y a une session alors on ne retourne plus sur cette page
    if (isset($_SESSION['id_client'])) {
        header('Location: connexion');
        exit;
    }

    // Si la variable "$_POST" contient des informations alors on les traite
    if (!empty($_POST)) {
        $valid = TRUE;
        $error = '';

        // On se place sur le bon formulaire grâce au "name" de la balise "input"
        if (isset($_POST['inscription'])) {
            // Vérification CSRF
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                die('Erreur CSRF : requête non autorisée.');
            }

            // Protection anti-bot
            if (!empty($_POST['website'])) {
                die("Bot détecté 🚫");
            }

            // Récupération et nettoyage des données
            $nom = isset($_POST['nom']) ? htmlentities(trim($_POST['nom'])) : '';
            $prenom = isset($_POST['prenom']) ? htmlentities(trim($_POST['prenom'])) : '';
            $email = isset($_POST['email']) ? htmlentities(strtolower(trim($_POST['email']))) : '';
            $mdp = isset($_POST['mdp']) ? trim($_POST['mdp']) : '';
            $password_confirm = isset($_POST['password_confirm']) ? trim($_POST['password_confirm']) : '';

            // Vérification du nom
            if (empty($nom)) {
                $valid = FALSE;
                $error = "Le nom ne peut pas être vide";
            }

            // Vérification du prénom
            if (empty($prenom)) {
                $valid = FALSE;
                $error = "Le prénom ne peut pas être vide";
            }

            // Vérification de l'email
            if (empty($email)) {
                $valid = FALSE;
                $error = "L'email ne peut pas être vide";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid = FALSE;
                $error = "L'email n'est pas valide";
            } else {
                // Vérification que l'email est disponible
                try {
                    $req_email = $connection->query(
                        "SELECT email FROM login_user WHERE email = ?",
                        array($email)
                    );

                    $email_exists = $req_email->fetch();

                    // Correction de l'erreur : vérifier si le résultat existe ET n'est pas vide
                    if ($email_exists && !empty($email_exists['email'])) {
                        $valid = FALSE;
                        $error = "Cet email existe déjà";
                    }
                } catch (Exception $e) {
                    $valid = FALSE;
                    $error = "Erreur lors de la vérification de l'email";
                }
            }

            // Vérification du mot de passe
            if (empty($mdp)) {
                $valid = FALSE;
                $error = "Le mot de passe ne peut pas être vide";
            } elseif (strlen($mdp) < 6) {
                $valid = FALSE;
                $error = "Le mot de passe doit contenir au moins 6 caractères";
            }
            if ($mdp !== $password_confirm) {
                $valid = FALSE;
        $error = "Les mots de passe ne correspondent pas.";
            }

            // Vérification reCAPTCHA Enterprise

            // Si tout est valide, on procède à l'inscription
            if ($valid == TRUE) {
                try {
                    $numero_client = genererNumeroClient();
                    $mdp_hash = password_hash($mdp, PASSWORD_ARGON2ID);
                    $ip = getIp();

                    // Insertion dans la base de données
                    $connection->insert(
                        "INSERT INTO login_user (nom, prenom, email, numero_client, mdp, ip, user_c) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        array($nom, $prenom, $email, $numero_client, $mdp_hash, $ip)
                    );

                    // Récupération des données utilisateur
                    $sth = $db->prepare('SELECT * FROM login_user WHERE email = ?');
                    $sth->execute(array($email));
                    $data = $sth->fetch();

                    if ($data) {
                        // Création de la session
                        $_SESSION['id_client'] = $data['numero_client'];
                        $_SESSION['nom'] = $data['nom'];
                        $_SESSION['prenom'] = $data['prenom'];
                        $_SESSION['user_c'] = $data['user_c'];
                        $_SESSION['email'] = $email;
                        $_SESSION['ip'] = getIp();

                        // Génération du JWT pour la vérification email
                        $key = "0a4fa423cf1e4533d6c394f094faf1c75921853e71ffcdf3f07ef8a73ccf4a3d";
                        $issuedAt = time();
                        $expirationTime = $issuedAt + 600;
                        $jwt_data = array(
                            "id" => $_SESSION['id_client'],
                            "email" => $_SESSION['email'],
                        );
                        $binaryData = json_encode($jwt_data);
                        $compressedPayload = gzencode($binaryData);
                        $payload = array(
                            "iat" => $issuedAt,
                            "exp" => $expirationTime,
                            "data" => base64_encode($compressedPayload)
                        );

                        $jwt = JWT::encode($payload, $key, 'HS256');
                        $escapedJwt = urlencode($jwt);

                        // Envoi de l'email de vérification
                        $to = $_SESSION['email'];
                        $subject = 'Bienvenue sur Automoclick';
                        $message = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue chez Automoclick</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; 
            margin: 0; 
            padding: 0; 
            background: #f4f4f4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .email-container { 
            width: 100%; 
            max-width: 600px; 
            margin: 0 auto; 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .header {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .logo { 
            width: 120px; 
            height: auto;
            margin: 0 auto 20px; 
            display: block;
        }
        .title { 
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin: 10px 0 0;
            font-weight: 400;
        }
        .content {
            padding: 40px 30px;
            text-align: center;
        }
        .message {
            font-size: 16px;
            line-height: 1.6;
            color: #374151;
            margin-bottom: 30px;
            text-align: left;
        }
        .cta-btn {
            display: inline-block;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white !important;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 10px 25px -3px rgba(5, 150, 105, 0.3);
            transition: all 0.3s ease;
            border: none;
        }
        .cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px -3px rgba(5, 150, 105, 0.4);
        }
        .credentials {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 25px;
            margin: 25px 0;
            border: 2px solid #e2e8f0;
        }
        .credential-item {
            margin: 15px 0;
        }
        .credential-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .credential-value {
            font-size: 18px;
            font-weight: 700;
            color: #059669;
            font-family: \'Courier New\', monospace;
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 12px;
            margin: 20px 0;
            font-size: 14px;
            color: #92400e;
        }
        .info-box {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
            border-left: 4px solid #059669;
            text-align: left;
        }
        .info-box p {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.5;
        }
        .info-box p:last-child { margin-bottom: 0; }
        .footer {
            background: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .social-links {
            margin: 20px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #059669;
            text-decoration: none;
            font-weight: 500;
        }
        @media only screen and (max-width: 600px) {
            .email-container { margin: 20px; border-radius: 16px; }
            .header { padding: 30px 20px; }
            .content { padding: 30px 20px; }
            .footer { padding: 20px; }
            .logo { width: 100px; }
            .title { font-size: 24px; }
            .cta-btn { padding: 14px 28px; font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img class="logo" src="https://automoclick.com/img/logo-automoclick.png" alt="Automoclick">
            <h1 class="title">Bienvenue sur Automoclick</h1>
            <p class="subtitle">Votre compte a été créé avec succès</p>
        </div>
        
        <div class="content">
            <div class="message">
                <p><strong>Bonjour et bienvenue !</strong></p>
                <p>Nous sommes ravis de vous accueillir dans la communauté Automoclick. Votre compte a été créé avec succès.</p>
            
            <div class="info-box">
                <p><strong>🎯 Prochaines étapes :</strong></p>
                <p>1. Connectez-vous à votre espace personnel</p>
                <p>2. Découvrez nos fonctionnalités</p>
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
</html>';

                        $headers = 'From: Automoclick <no-reply@automoclick.com>' . "\r\n" .
                            'Reply-To: no-reply@automoclick.com' . "\r\n" .
                            'X-Mailer: PHP/' . phpversion() . "\r\n" .
                            'Content-Type: text/html; charset=UTF-8' . "\r\n" .
                            'Content-Transfer-Encoding: 8bit';
                        header('Location: /');
                        if (mail($to, $subject, $message, $headers)) {
                            $success = "Inscription réussie ! Vérifiez votre email pour activer votre compte.";
                        } else {
                            $success = "Inscription réussie ! Cependant, l'email de vérification n'a pas pu être envoyé.";
                        }
                    } else {
                        $error = "Erreur lors de la création du compte";
                    }
                } catch (Exception $e) {
                    $error = "Erreur lors de l'inscription : " . $e->getMessage();
                }
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
        <link rel="manifest" href="img/site.webmanifest">
        <title>Inscription</title>
        <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
        <script src="index.js"></script>
        <script src="https://kit.fontawesome.com/aad32a1fda.js" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">

        <link rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    </head>

    <body class="min-h-screen flex items-center justify-center">
        <form id="form" method="POST" class="bg-white p-6 md:p-10 rounded-xl shadow-lg w-full max-w-md">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <h2 class="text-2xl font-semibold text-green-600 mb-4 text-center">Inscription</h2>
            <hr class="border-green-300 mb-6">
            <div style="display:none;">
                <label for="website">Ne pas remplir :</label>
                <input type="text" name="website" id="website">
            </div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" id="email" name="email" value="<?php if (isset($email))
                echo $email; ?>" required
                class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-400 mb-4">

            <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
            <input type="text" id="nom" name="nom" value="<?php if (isset($nom))
                echo $nom; ?>" required
                class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-400 mb-4">

            <label for="prenom" class="block text-sm font-medium text-gray-700 mb-1">Prénom</label>
            <input type="text" id="prenom" name="prenom" value="<?php if (isset($prenom))
                echo $prenom; ?>" required
                class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-400 mb-4">

            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Créez votre mot de passe</label>
            <input type="password" id="password" name="mdp" value="<?php if (isset($mdp))
                echo $mdp; ?>" required
                class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-400 mb-4">
                 <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirmer votre mot de passe</label>
            <input type="password" id="password_confirm" name="password_confirm" value="<?php if (isset($password_confirm))
                echo $mdp; ?>" required
                class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-400 mb-4">


            <?php if (isset($error)) { ?>
                <div class="text-red-500 text-sm mb-4"><?= $error ?></div>
            <?php } ?>

            <input type="submit" name="inscription" value="S'inscrire"
                class="g-recaptcha w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md transition duration-200">

            <p class="text-sm text-center text-gray-600 mt-4">Vous avez déjà un compte ?
                <a href="connexion" class="text-green-600 hover:underline">Connectez-vous</a>
            </p>
               <div id="password-criteria" class="text-gray-600 text-sm space-y-1 mt-2">
            <p id="length" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>8 à 16 caractères</p>
            <p id="uppercase" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>Au moins une majuscule</p>
            <p id="lowercase" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>Au moins une minuscule</p>
            <p id="number" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>Au moins un chiffre</p>
            <p id="special" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>Au moins un caractère spécial (!@#$%^&*)</p>
        </div>
        </form>
 


<script nonce="<?= htmlspecialchars($nonce) ?>">
const passwordInput = document.getElementById('password');
const criteria = {
    length: document.getElementById('length'),
    uppercase: document.getElementById('uppercase'),
    lowercase: document.getElementById('lowercase'),
    number: document.getElementById('number'),
    special: document.getElementById('special')
};

passwordInput.addEventListener('input', () => {
    const value = passwordInput.value;

    // Fonction pour mettre à jour l'icône
    const updateIcon = (element, condition) => {
        const icon = element.querySelector('span');
        if(condition){
            icon.textContent = 'check';
            icon.classList.remove('text-red-600');
            icon.classList.add('text-green-600');
        } else {
            icon.textContent = 'close';
            icon.classList.remove('text-green-600');
            icon.classList.add('text-red-600');
        }
    }

    // Patterns pour validation
    updateIcon(criteria.length, value.length >= 8 && value.length <= 16);
    updateIcon(criteria.uppercase, /[A-Z]/.test(value));
    updateIcon(criteria.lowercase, /[a-z]/.test(value));
    updateIcon(criteria.number, /\d/.test(value));
    updateIcon(criteria.special, /[!@#$%^&*]/.test(value));
});
</script>

</body>
</html>


    </body>

    </html>