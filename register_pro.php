<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'header.php';
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once 'db/dbconnect.php';
require_once 'db/dbconnect2.php';
require 'vendor/autoload.php';
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
require_once 'api/traker.php';
function genererNumeroClient()
{
    $prefix = 'PR';
    $date = date('Ymd');
    $unique = strtoupper(bin2hex(random_bytes(3)));
    return $prefix . '-' . $date . '-' . $unique;
}

function getIp()
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getSpecialisationFromAPE($codeAPE)
{
    $mapAPEtoSpecialisation = [
        '45.20A' => 'mecanique',
        '45.20B' => 'mecanique',
        '45.20C' => 'nettoyage',
        '45.20D' => 'peintre',
        '45.20E' => 'carrosserie',
        '33.14Z' => 'electro',
        '25.62B' => 'soudeur',
        '71.12B' => 'controle',
        '45.20Z' => 'garage'
    ];
    return $mapAPEtoSpecialisation[$codeAPE] ?? 'inconnue';
}

function getCountryFromPostalCode($codePostal)
{
    $code = (int) preg_replace('/\D/', '', $codePostal);

    if ($code >= 97000 && $code < 98000) {
        if (strpos($codePostal, '971') === 0)
            return 'gp';
        if (strpos($codePostal, '972') === 0)
            return 'mq';
        if (strpos($codePostal, '973') === 0)
            return 'gf';
        if (strpos($codePostal, '974') === 0)
            return 're';
        if (strpos($codePostal, '976') === 0)
            return 'yt';
        return 'fr';
    } elseif ($code >= 1000 && $code <= 99999) {
        return 'fr';
    } else {
        return 'inconnu';
    }
}

function genererMdpSolide(int $longueur = 12): string
{
    $majuscules = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $minuscules = 'abcdefghijklmnopqrstuvwxyz';
    $chiffres = '0123456789';
    $speciaux = '!@#$%^&*()-_=+[]{}<>?';

    $smdp = $majuscules[random_int(0, strlen($majuscules) - 1)] .
        $minuscules[random_int(0, strlen($minuscules) - 1)] .
        $chiffres[random_int(0, strlen($chiffres) - 1)] .
        $speciaux[random_int(0, strlen($speciaux) - 1)];

    $tous = $majuscules . $minuscules . $chiffres . $speciaux;
    for ($i = strlen($smdp); $i < $longueur; $i++) {
        $smdp .= $tous[random_int(0, strlen($tous) - 1)];
    }
    return str_shuffle($smdp);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Erreur CSRF : requête non autorisée.');
    }

    $valid = TRUE;
    $errors = [];

    // Récupération et validation des données
    $siret = trim($_POST['siret'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $denomination = htmlspecialchars(trim($_POST['denomination'] ?? ''));
    $activite = htmlspecialchars(trim($_POST['activite'] ?? ''));
    $adresse = htmlspecialchars(trim($_POST['adresse'] ?? ''));
    $code_postal = htmlspecialchars(trim($_POST['code_postal'] ?? ''));
    $commune = htmlspecialchars(trim($_POST['commune'] ?? ''));
    $date_creation = $_POST['date_creation'] ?? '';
    $etat_administratif = htmlspecialchars(trim($_POST['etat_administratif'] ?? ''));
    $forme_juridique = htmlspecialchars(trim($_POST['forme_juridique'] ?? ''));
    $siren = trim($_POST['siren'] ?? '');

    // Validation des champs requis
    if (empty($siret)) {
        $valid = false;
        $errors[] = "Le SIRET est requis.";
    }

    if (!$email) {
        $valid = false;
        $errors[] = "L'email est invalide.";
    }

    if (empty($denomination)) {
        $valid = false;
        $errors[] = "La dénomination est requise.";
    }

    // Vérification unicité SIRET
    if (!empty($siret)) {
        $req_siret = $db->prepare("SELECT siret FROM entreprises WHERE siret = ?");
        $req_siret->execute([$siret]);
        if ($req_siret->fetch()) {
            $valid = false;
            $errors[] = "Ce SIRET existe déjà.";
        }
    }

    // Vérification unicité email
    if ($email) {
        $req_email = $db->prepare("SELECT email FROM entreprises WHERE email = ?");
        $req_email->execute([$email]);
        if ($req_email->fetch()) {
            $valid = false;
            $errors[] = "Cet email existe déjà.";
        }
    }



    if ($valid === TRUE) {
        $numero_pro = genererNumeroClient();
        $mdp = genererMdpSolide();
        $hashpass = password_hash($mdp, PASSWORD_ARGON2ID);
        $pays = getCountryFromPostalCode($code_postal);
        $specialisation = getSpecialisationFromAPE($activite);


        try {
            // Insertion entreprise
            $stmt = $db->prepare("
                INSERT INTO entreprises (siret, forme_juridique, email, denomination, activite, pays, spe, adresse, code_postal, commune, date_creation, etat_administratif, siren, numero_pro, date_creation_account)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $result1 = $stmt->execute([$siret, $forme_juridique, $email, $denomination, $activite, $pays, $specialisation, $adresse, $code_postal, $commune, $date_creation, $etat_administratif, $siren, $numero_pro]);

            if (!$result1) {
                throw new Exception("Erreur insertion entreprise: " . implode(", ", $stmt->errorInfo()));
            }



            // Insertion login
            $verif_email = 0;
            $q_email = $db->prepare('INSERT INTO login_pro (mdp, verif_email, numero_pro, role) VALUES (?, ?, ?, "admin")');
            $result2 = $q_email->execute([$hashpass, $verif_email, $numero_pro]);

            if (!$result2) {
                throw new Exception("Erreur insertion login: " . implode(", ", $q_email->errorInfo()));
            }


            // Récupération des données pour session
            $valid_email = $db->prepare('SELECT * FROM entreprises WHERE email = ?');
            $valid_email->execute([$email]);
            $data_valid = $valid_email->fetch();

            if ($data_valid) {
                $_SESSION['numero_pro'] = $data_valid['numero_pro'];
                $_SESSION['email'] = $data_valid['email'];
                $_SESSION['email_verif'] = true;
            }

            // Génération JWT
            $key = "0a4fa423cf1e4533d6c394f094faf1c75921853e71ffcdf3f07ef8a73ccf4a3d";
            $issuedAt = time();
            $expirationTime = $issuedAt + 600;

            $data = array(
                "id" => $_SESSION['numero_pro'],
                "email" => $_SESSION['email'],
            );

            $binaryData = json_encode($data);
            $compressedPayload = gzencode($binaryData);

            $payload = array(
                "iat" => $issuedAt,
                "exp" => $expirationTime,
                "data" => base64_encode($compressedPayload)
            );

            $jwt = JWT::encode($payload, $key, 'HS256');
            $escapedJwt = urlencode($jwt);

            // Préparation des emails
            $to = $email;

            // Email 1: Vérification
            $subject = 'Vérifier votre email';
            $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifier votre adresse mail</title>
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
        .verification-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 16px;
            padding: 25px;
            margin: 25px 0;
            border: 2px solid #f59e0b;
            text-align: center;
        }
        .verification-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .verification-text {
            font-size: 16px;
            color: #92400e;
            font-weight: 600;
            margin-bottom: 20px;
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
            <h1 class="title">Vérifiez votre adresse email</h1>
            <p class="subtitle">Une dernière étape pour activer votre compte</p>
        </div>
        
        <div class="content">
            <div class="message">
                <p><strong>Bonjour et félicitations !</strong></p>
                <p>Votre compte professionnel Automoclick a été créé avec succès. Pour des raisons de sécurité, nous devons vérifier votre adresse email.</p>
                <p>Cliquez simplement sur le bouton ci-dessous pour confirmer votre adresse :</p>
            </div>
            
            <div class="verification-box">
                <div class="verification-icon"></div>
                <div class="verification-text">Vérification requise</div>
                <a href="https://automoclick.com/mail_confirm?token=' . $escapedJwt . '" class="cta-btn">
                    Vérifier mon email
                </a>
            </div>
            
            <div class="info-box">
                <p><strong> Pourquoi cette vérification ?</strong></p>
                <p>• Sécuriser votre compte professionnel</p>
                <p>• Vous assurer de recevoir nos communications importantes</p>
                <p>• Activer toutes les fonctionnalités de votre espace</p>
                <p>• Respecter les standards de sécurité</p>
            </div>
            
            <div style="background: #e0f2fe; border-radius: 8px; padding: 15px; margin: 20px 0; font-size: 14px; color: #0277bd;">
                 <strong>Astuce :</strong> Ce lien de vérification expire dans 10 minutes pour votre sécurité.
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
                Si vous n\'arrivez pas à cliquer sur le bouton, copiez ce lien dans votre navigateur :<br>
                <span style="color: #059669; word-break: break-all;">https://automoclick.com/mail_confirm?token=' . $escapedJwt . '</span>
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


            $subject2 = 'Bienvenue sur Automoclick';
            $message2 = '
<!DOCTYPE html>
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
                <p>Nous sommes ravis de vous accueillir dans la communauté Automoclick. Votre compte professionnel a été créé avec succès.</p>
                <p>Voici vos identifiants de connexion :</p>
            </div>
            
            <div class="credentials">
                <div class="credential-item">
                    <div class="credential-label">Identifiant professionnel</div>
                    <div class="credential-value">' . $numero_pro . '</div>
                </div>
                <div class="credential-item">
                    <div class="credential-label">Mot de passe temporaire</div>
                    <div class="credential-value">' . $mdp . '</div>
                </div>
            </div>
            
            <a href="https://automoclick.com/connexion" class="cta-btn">
                Accéder à mon espace
            </a>
            
            <div class="warning">
                ⚠️ <strong>Important :</strong> Pour des raisons de sécurité, nous vous recommandons de modifier votre mot de passe dès votre première connexion.
            </div>
            
            <div class="info-box">
                <p><strong>🎯 Prochaines étapes :</strong></p>
                <p>1. Connectez-vous à votre espace personnel</p>
                <p>2. Modifiez votre mot de passe</p>
                <p>3. Complétez votre profil</p>
                <p>4. Découvrez nos fonctionnalités</p>
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
            $headers2 = $headers;

            if (!mail($to, $subject, $message, $headers)) {
                echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
            }
            if (!mail($to, $subject2, $message2, $headers2)) {
                echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
            }
            header('Location: success');
            exit;

        } catch (PDOException $e) {
            $message = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
    <title>Automoclick | Inscription Professionnel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="jq-siret.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>

<body class="bg-gray-100 font-sans text-gray-800">
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-2xl">
            <h1 class="text-3xl font-bold text-center text-green-600 mb-4">Rejoignez Automoclick</h1>
            <p class="text-center text-gray-600 mb-6">
                Développez votre activité en ligne, recevez des rendez-vous qualifiés et facilitez la gestion de votre
                entreprise. L'inscription est simple et rapide !
            </p>

            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    Inscription réussie ! Vérifiez vos emails.
                </div>
            <?php endif; ?>

            <div class="mb-6 text-center">
                <label for="siret" class="block text-lg font-medium mb-2">Entrez votre numéro SIRET :</label>
                <input
                    class="form-pro-siret border border-gray-300 rounded px-4 py-2 w-60 mx-auto block focus:outline-none focus:ring-2 focus:ring-green-500"
                    type="text" id="siret" placeholder="Ex : 12345678901234" maxlength="14" />
            </div>

 <form class="form-pro space-y-4" method="POST" id="formEntreprise" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="siret" id="form_siret" />
    <input type="hidden" name="siren" id="siren" />
    <div style="display:none;">
        <label for="website">Ne pas remplir :</label>
        <input type="text" name="website" id="website">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="email" name="email" placeholder="Votre email" required
            class="border px-3 py-2 rounded w-full" />
        <input type="text" name="denomination" id="denomination" placeholder="Votre denomination" required
            class="border px-3 py-2 rounded w-full bg-gray-100" />
        <input type="text" name="adresse" id="adresse" readonly
            class="border px-3 py-2 rounded w-full bg-gray-100" />
        <input type="text" name="code_postal" id="code_postal" readonly
            class="border px-3 py-2 rounded w-full bg-gray-100" />
        <input type="text" name="commune" id="commune" readonly
            class="border px-3 py-2 rounded w-full bg-gray-100" />
        <input type="text" name="forme_juridique" id="forme_juridique" readonly
            class="border px-3 py-2 rounded w-full bg-gray-100" />
    </div>

    <input type="hidden" name="activite" id="activite" />
    <input type="hidden" name="date_creation" id="date_creation" />
    <input type="hidden" name="etat_administratif" id="etat_administratif" />

    <!-- Bloc CGU -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-start gap-3">
            <label class="flex items-center gap-3 cursor-pointer select-none">
                <input id="conditions-checkbox" name="conditions" type="checkbox"  class="peer sr-only" />
                <span
                    class="flex h-6 w-6 items-center justify-center rounded-md border-2 border-gray-300 peer-checked:bg-green-600 peer-checked:border-green-600 transition">
                    <svg class="hidden peer-checked:block h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>

                <div class="text-sm">
                    <span class="font-medium text-gray-800">J'accepte les 
                        <a href="cgu"
                            class="text-green-600 font-semibold hover:underline">conditions d'utilisation</a>
                    </span>
                    <div class="text-xs text-gray-500">Vous devez accepter les CGU pour continuer.</div>
                </div>
            </label>
        </div>
        <p id="cgu-error" class="mt-3 text-sm text-red-600 hidden">Vous devez accepter les conditions d'utilisation.</p>
    </div>

    <!-- Bouton d’envoi -->
    <div class="text-center">
        <input type="submit" value="Enregistrer mon entreprise"
            class="bg-green-600 text-white font-semibold px-6 py-2 rounded hover:bg-green-700 transition" />
    </div>
</form>
<script nonce="<?= htmlspecialchars($nonce) ?>">
document.getElementById('formEntreprise').addEventListener('submit', function (e) {
    const checkbox = document.getElementById('conditions-checkbox');
    const errorMsg = document.getElementById('cgu-error');

    if (!checkbox.checked) {
        e.preventDefault(); // empêche l'envoi
        errorMsg.classList.remove('hidden'); // affiche le message
        checkbox.focus();
    } else {
        errorMsg.classList.add('hidden'); // cache le message si ok
    }
});

// Quand on coche/décoche → on cache le message d’erreur si coché
document.getElementById('conditions-checkbox').addEventListener('change', function () {
    const errorMsg = document.getElementById('cgu-error');
    if (this.checked) {
        errorMsg.classList.add('hidden');
    }
});
</script>



            <?php if (isset($errors) && is_array($errors) && !empty($errors)): ?>
                <div class="mt-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
<?php include('includes/footer.php'); ?>

</html>