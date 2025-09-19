<?php
require_once 'header.php';
session_start();
require 'vendor/autoload.php';
require_once('db/dbconnect2.php');
require_once '../includes/monitor_init.php'; 
require_once '../includes/webhook.php';
require_once '../api/traker.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Clé secrète utilisée pour signer le token JWT
$key = "0a4fa423cf1e4533d6c394f094faf1c75921853e71ffcdf3f07ef8a73ccf4a3d";

$success = false;
$error = null;
$email = '';

// Traitement du formulaire de renvoi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Veuillez saisir votre adresse e-mail.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez saisir une adresse e-mail valide.";
    } else {
        try {
            // Vérifier si l'email existe dans la base
            $checkStmt = $db->prepare("SELECT numero_pro, verif_email FROM login_pro WHERE email = :email");
            $checkStmt->execute(['email' => $email]);
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = "Cette adresse e-mail n'est pas enregistrée dans notre système.";
            } elseif ($user['verif_email'] == 1) {
                $error = "Cette adresse e-mail est déjà vérifiée.";
            } else {
                // Générer un nouveau token JWT
                $payload = [
                    'id' => $user['numero_pro'],
                    'email' => $email,
                    'timestamp' => time()
                ];
                
                // Compresser et encoder le payload
                $compressedPayload = gzencode(json_encode($payload));
                $encodedPayload = base64_encode($compressedPayload);
                
                $jwt = JWT::encode(['data' => $encodedPayload], $key, 'HS256');
                
                // Construire le lien de vérification
                $verificationLink = "https://automoclick.com/mail_confirm?token=" . urlencode($jwt);
                
                // Configuration de l'email
                $to = $email;
                $subject = "Vérification de votre compte Automoclick";
                
                $message = "
                <!DOCTYPE html>
                <html lang='fr'>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: 'Arial', sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 30px; text-align: center; }
                        .content { padding: 30px; }
                        .btn { display: inline-block; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                        .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>🚗 Automoclick</h1>
                            <p>Vérifiez votre adresse e-mail</p>
                        </div>
                        <div class='content'>
                            <h2>Bonjour,</h2>
                            <p>Vous avez demandé un nouveau lien de vérification pour votre compte Automoclick.</p>
                            <p>Cliquez sur le bouton ci-dessous pour vérifier votre adresse e-mail :</p>
                            <div style='text-align: center;'>
                                <a href='{$verificationLink}' class='btn'>Vérifier mon e-mail</a>
                            </div>
                            <p><small>Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :<br>
                            <a href='{$verificationLink}'>{$verificationLink}</a></small></p>
                            <hr style='margin: 30px 0; border: none; height: 1px; background: #e2e8f0;'>
                            <p><strong>⚠️ Important :</strong></p>
                            <ul>
                                <li>Ce lien est valable pendant 24 heures</li>
                                <li>Si vous n'avez pas demandé cette vérification, ignorez cet e-mail</li>
                                <li>Pour votre sécurité, ne partagez jamais ce lien</li>
                            </ul>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " Automoclick - Tous droits réservés</p>
                            <p>Si vous avez des questions, contactez-nous à support@automoclick.com</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Automoclick <noreply@automoclick.com>" . "\r\n";
                $headers .= "Reply-To: support@automoclick.com" . "\r\n";
                
                // Envoyer l'email
                if (mail($to, $subject, $message, $headers)) {
                    $success = true;
                } else {
                    $error = "Erreur lors de l'envoi de l'e-mail. Veuillez réessayer plus tard.";
                }
            }
            
        } catch (Exception $e) {
            $error = "Une erreur technique est survenue. Veuillez réessayer plus tard.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renvoyer l'email de vérification - Automoclick</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes bounceIn {
            0%, 20%, 40%, 60%, 80% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .animate-slide-up { animation: slideInUp 0.8s ease-out; }
        .animate-bounce-in { animation: bounceIn 1s ease-in-out; }
        .animate-fade-scale { animation: fadeInScale 0.6s ease-out; }
        
        .bg-automoclick-gradient {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #60a5fa 100%);
        }
        
        .particles {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            overflow: hidden; z-index: 1;
        }
        
        .particle {
            position: absolute; background: rgba(255, 255, 255, 0.1);
            border-radius: 50%; animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
        }
        
        .btn-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
    </style>
</head>

<body class="bg-automoclick-gradient min-h-screen relative overflow-hidden">
    <!-- Particules flottantes -->
    <div class="particles">
        <?php for ($i = 0; $i < 15; $i++): ?>
            <div class="particle" style="
                width: <?= rand(4, 12) ?>px; 
                height: <?= rand(4, 12) ?>px; 
                left: <?= rand(0, 100) ?>%; 
                animation-delay: <?= rand(0, 6) ?>s;
                animation-duration: <?= rand(4, 8) ?>s;
            "></div>
        <?php endfor; ?>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            
            <?php if ($success): ?>
                <!-- Succès -->
                <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl p-8 text-center animate-slide-up">
                    <div class="mx-auto w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mb-6 animate-bounce-in">
                        <i class="fas fa-paper-plane text-white text-3xl"></i>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-800 mb-4 animate-fade-scale">
                        E-mail envoyé !
                    </h1>
                    
                    <div class="space-y-4 mb-8">
                        <p class="text-lg text-gray-600 animate-fade-scale" style="animation-delay: 0.2s;">
                            Un nouveau lien de vérification a été envoyé à :
                        </p>
                        
                        <div class="bg-green-50 border border-green-200 rounded-xl p-4 animate-fade-scale" style="animation-delay: 0.4s;">
                            <p class="text-sm text-green-800 font-semibold">
                                <i class="fas fa-envelope text-green-600 mr-2"></i>
                                <?= htmlspecialchars($email) ?>
                            </p>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 animate-fade-scale" style="animation-delay: 0.6s;">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <strong>Que faire maintenant ?</strong><br>
                                1. Vérifiez votre boîte de réception<br>
                                2. Regardez aussi dans vos spams<br>
                                3. Cliquez sur le lien de vérification
                            </p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <button onclick="openEmail('<?= htmlspecialchars($email) ?>')" 
                               class="btn-hover w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 animate-fade-scale" 
                               style="animation-delay: 0.8s;">
                            <i class="fas fa-external-link-alt"></i>
                            <span>Ouvrir ma boîte e-mail</span>
                        </button>
                        
                        <a href="/login" 
                           class="btn-hover w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 border border-gray-200 animate-fade-scale" 
                           style="animation-delay: 1s;">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Aller à la connexion</span>
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Formulaire -->
                <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl p-8 animate-slide-up">
                    <div class="text-center mb-8">
                        <div class="mx-auto w-20 h-20 bg-blue-500 rounded-full flex items-center justify-center mb-6 animate-bounce-in">
                            <i class="fas fa-envelope text-white text-3xl"></i>
                        </div>
                        
                        <h1 class="text-3xl font-bold text-gray-800 mb-4 animate-fade-scale">
                            Renvoyer la vérification
                        </h1>
                        
                        <p class="text-gray-600 animate-fade-scale" style="animation-delay: 0.2s;">
                            Saisissez votre adresse e-mail pour recevoir un nouveau lien de vérification
                        </p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 animate-fade-scale">
                            <p class="text-sm text-red-800">
                                <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                <?= htmlspecialchars($error) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-6">
                        <div class="animate-fade-scale" style="animation-delay: 0.4s;">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope text-gray-400 mr-1"></i>
                                Adresse e-mail
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($email) ?>"
                                   required 
                                   placeholder="votre@email.com"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                        
                        <button type="submit" 
                                class="btn-hover w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 animate-fade-scale" 
                                style="animation-delay: 0.6s;">
                            <i class="fas fa-paper-plane"></i>
                            <span>Renvoyer l'e-mail</span>
                        </button>
                    </form>
                    
                    <div class="mt-8 pt-6 border-t border-gray-200 text-center animate-fade-scale" style="animation-delay: 0.8s;">
                        <p class="text-sm text-gray-500 mb-4">Vous avez déjà un compte vérifié ?</p>
                        <div class="space-y-2">
                            <a href="/login" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                Se connecter
                            </a>
                            <span class="mx-2 text-gray-300">•</span>
                            <a href="/contact" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                Support
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <script>
        function openEmail(emailAddress) {
            const domain = emailAddress.split('@')[1];
            const webmailProviders = {
                'gmail.com': 'https://mail.google.com',
                'outlook.com': 'https://outlook.live.com',
                'hotmail.com': 'https://outlook.live.com',
                'yahoo.com': 'https://mail.yahoo.com',
                'yahoo.fr': 'https://mail.yahoo.com'
            };
            
            const webmailUrl = webmailProviders[domain] || 'https://mail.google.com';
            window.open(webmailUrl, '_blank');
        }
        
        // Animation d'entrée progressive
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate-fade-scale');
            elements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.2}s`;
            });
        });
    </script>
</body>
</html>
