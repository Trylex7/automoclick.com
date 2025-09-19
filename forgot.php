<?php
session_start();
require_once 'db/dbconnect2.php'; // connexion PDO $db
require_once 'api/traker.php';
// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_request'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide.";
    } else {
        $input = trim($_POST['identifiant'] ?? '');

        if ($input === "") {
            $error = "Veuillez saisir votre identifiant ou votre adresse e-mail.";
        } else {
            // Chercher dans les deux tables (pro & particulier)
            $stmt = $db->prepare("
    SELECT email FROM login_user WHERE email   = :input
    UNION
    SELECT email FROM entreprises WHERE email = :input OR numero_pro = :input
    LIMIT 1
");
            $stmt->execute(['input' => $input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Générer tokens sécurisés
                $selector = bin2hex(random_bytes(8));
                $token = random_bytes(32);
                $url = "https://automoclick.com/reset.php?selector=" . $selector . "&validator=" . bin2hex($token);

                $expires = date("U") + 3600;

                // Supprimer anciens tokens
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$user['email']]);

                // Enregistrer
                $db->prepare("INSERT INTO password_resets (email, selector, token, expires) VALUES (?, ?, ?, ?)")
                    ->execute([$user['email'], $selector, password_hash($token, PASSWORD_DEFAULT), $expires]);

                // Envoyer mail
                $to = $user['email'];
                $subject = "Réinitialisation de mot de passe";
                $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
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
        .expires {
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
            <h1 class="title">Réinitialisation de mot de passe</h1>
            <p class="subtitle">Créez un nouveau mot de passe sécurisé</p>
        </div>
        
        <div class="content">
            <div class="message">
                <p>Bonjour,</p>
                <p>Vous avez demandé la réinitialisation de votre mot de passe sur Automoclick.</p>
                <p>Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe :</p>
            </div>
            
            <a href="'.$url.'" class="cta-btn">
                 Réinitialiser mon mot de passe
            </a>
            
            <div class="expires">
                 <strong>Important :</strong> Ce lien expire dans 15 minutes pour votre sécurité.
            </div>
            
            <div class="info-box">
                <p><strong> Conseils de sécurité :</strong></p>
                <p>• Utilisez au moins 8 caractères</p>
                <p>• Mélangez majuscules, minuscules et chiffres</p>
                <p>• Ajoutez des caractères spéciaux</p>
                <p>• N\'utilisez pas d\'informations personnelles</p>
            </div>
            
            <div class="info-box">
                <p><strong>🚨 Vous \'avez pas demandé cette réinitialisation ?</strong></p>
                <p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail. Votre mot de passe actuel reste inchangé et votre compte est sécurisé.</p>
            </div>
            
            <div class="info-box">
                <p><strong>Le lien ne fonctionne pas ?</strong></p>
                <p>Copiez et collez cette URL dans votre navigateur :</p>
                <p style="word-break: break-all; font-family: monospace; font-size: 12px; color: #059669;">
                    '. $url . '
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>L\'équipe Automoclick</strong></p>
            <div class="social-links">
                <a href="https://instagram.com/automoclick" target="_blank"> Instagram</a>
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
                    'Reply-To: support@automoclick.com' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion() . "\r\n" .
                    'Content-Type: text/html; charset=UTF-8' . "\r\n" .
                    'Content-Transfer-Encoding: 8bit';
                     if (!mail($to, $subject, $message, $headers)) {
                    echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
                }
                $success = "Si ce compte existe, un lien de réinitialisation a été envoyé.";
            } else {
                // On reste vague pour éviter l’énumération
                $success = "Si ce compte existe, un lien de réinitialisation a été envoyé.";
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
    <title>Mot de passe oublié - Automoclick</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center p-4">
    <form method="POST" class="bg-white w-full max-w-md rounded-2xl shadow-lg p-6 space-y-6 border border-green-100">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-green-700">Mot de passe oublié</h1>
            <hr class="mt-2 border-green-300" />
        </div>

        <div>
            <label for="id" class="block text-sm font-medium text-gray-700">
                Identifiant
            </label>
            <input id="id" type="text" name="identifiant"
                placeholder="Votre identifiant (professionnel) ou adresse e-mail (particulier)"
                class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                required />
        </div>

        <?php if ($error): ?>
            <div class="text-sm text-red-600 font-medium text-center"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="text-sm text-green-600 font-medium text-center"><?= $success ?></div>
        <?php endif; ?>

        <button type="submit" name="reset_request"
            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
            Envoyer le lien
        </button>

        <div class="text-sm text-center">
            <a href="connexion" class="text-green-600 hover:underline">Retour à la connexion</a>
        </div>
    </form>
</body>
</html>