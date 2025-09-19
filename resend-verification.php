<?php
require_once 'header.php';
require_once 'api/traker.php';
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once('db/dbconnect2.php');
require 'vendor/autoload.php';
use \Firebase\JWT\JWT;

$success = false;
$error = null;
$message = null;
$email_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Erreur CSRF : requête non autorisée.';
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $numero_pro = trim($_POST['numero_pro'] ?? '');
        
        if (!$email) {
            $error = "Veuillez saisir une adresse email valide.";
        } elseif (empty($numero_pro)) {
            $error = "Veuillez saisir votre numéro professionnel.";
        } else {
            try {
                // Vérifier que l'entreprise existe et récupérer les informations
                $stmt = $db->prepare("
                    SELECT e.email, e.numero_pro, l.verif_email 
                    FROM entreprises e 
                    INNER JOIN login_pro l ON e.numero_pro = l.numero_pro 
                    WHERE e.email = ? AND e.numero_pro = ?
                ");
                $stmt->execute([$email, $numero_pro]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $error = "Aucun compte trouvé avec ces informations.";
                } elseif ($user['verif_email'] == 1) {
                    $error = "Cette adresse email est déjà vérifiée.";
                } else {
                    // Générer un nouveau JWT
                    $key = "0a4fa423cf1e4533d6c394f094faf1c75921853e71ffcdf3f07ef8a73ccf4a3d";
                    $issuedAt = time();
                    $expirationTime = $issuedAt + 600; // 10 minutes
                    
                    $data = array(
                        "id" => $user['numero_pro'],
                        "email" => $user['email'],
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
                    
                    // Préparer l'email de vérification
                    $to = $email;
                    $subject = 'Nouveau lien de vérification - Automoclick';
                    $email_message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau lien de vérification</title>
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
            background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
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
        }
        .verification-btn {
            display: inline-block;
            background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
            color: white !important;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 10px 25px -3px rgba(21, 128, 61, 0.3);
            transition: all 0.3s ease;
            border: none;
        }
        .verification-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px -3px rgba(21, 128, 61, 0.4);
        }
        .info-box {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
            border-left: 4px solid #15803d;
        }
        .info-box p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.5;
        }
        .footer {
            background: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }
        .social-links {
            margin: 20px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #15803d;
            text-decoration: none;
            font-weight: 500;
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
        @media only screen and (max-width: 600px) {
            .email-container { margin: 20px; border-radius: 16px; }
            .header { padding: 30px 20px; }
            .content { padding: 30px 20px; }
            .footer { padding: 20px; }
            .logo { width: 100px; }
            .title { font-size: 24px; }
            .verification-btn { padding: 14px 28px; font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img class="logo" src="https://automoclick.com/img/logo-automoclick.png" alt="Automoclick">
            <h1 class="title">Nouveau lien de vérification</h1>
            <p class="subtitle">Confirmez votre adresse e-mail</p>
        </div>
        
        <div class="content">
            <div class="message">
                <p>Bonjour,</p>
                <p>Vous avez demandé un nouveau lien de vérification pour votre compte Automoclick.</p>
                <p>Cliquez sur le bouton ci-dessous pour vérifier votre adresse e-mail :</p>
            </div>
            
            <a href="https://automoclick.com/mail_confirm?token=' . $escapedJwt . '" class="verification-btn">
                 Vérifier mon adresse e-mail
            </a>
            
            <div class="expires">
                 <strong>Important :</strong> Ce lien expire dans 10 minutes pour votre sécurité.
            </div>
            
            <div class="info-box">
                <p><strong>Vous n\'avez pas demandé ce lien ?</strong></p>
                <p>Si vous n\'avez pas demandé cette vérification, vous pouvez ignorer cet e-mail en toute sécurité. Votre compte reste protégé.</p>
            </div>
            
            <div class="info-box">
                <p><strong>Le lien ne fonctionne pas ?</strong></p>
                <p>Copiez et collez cette URL dans votre navigateur :</p>
                <p style="word-break: break-all; font-family: monospace; font-size: 12px; color: #15803d;">
                    https://automoclick.com/mail_confirm?token=' . $escapedJwt . '
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>L\'équipe Automoclick</strong></p>
            <div class="social-links">
                <a href="https://instagram.com/automoclick" target="_blank"> Instagram</a>
                <a href="https://automoclick.com/contact" target="_blank"> Support</a>
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

                    // Envoi de l'email
                    if (mail($to, $subject, $email_message, $headers)) {
                        $success = true;
                        $email_sent = true;
                        $message = "Un nouveau lien de vérification a été envoyé à votre adresse e-mail.";
                    } else {
                        $error = "Erreur lors de l'envoi de l'e-mail. Veuillez réessayer plus tard.";
                    }
                }
                
            } catch (Exception $e) {
                $error = "Une erreur s'est produite : " . $e->getMessage();
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
    <title>Renvoyer le lien de vérification - Automoclick</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Animations personnalisées */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .7;
            }
        }
        
        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                transform: translate3d(0, -8px, 0);
            }
            70% {
                transform: translate3d(0, -4px, 0);
            }
            90% {
                transform: translate3d(0, -2px, 0);
            }
        }
        
        .animate-slide-up {
            animation: slideInUp 0.8s ease-out;
        }
        
        .animate-fade-scale {
            animation: fadeInScale 0.6s ease-out;
        }
        
        .animate-pulse-custom {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .animate-bounce-gentle {
            animation: bounce 2s infinite;
        }
        
        /* Gradient personnalisé */
        .bg-automoclick-gradient {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 50%, #22c55e 100%);
        }
        
        /* Effets hover */
        .btn-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .form-input {
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            transform: translateY(-2px);
        }
        
        /* Particules */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }
        
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0;
            }
            10%, 90% {
                opacity: 1;
            }
            50% {
                transform: translateY(-100px) rotate(180deg);
            }
        }
    </style>
</head>

<body class="bg-automoclick-gradient min-h-screen relative overflow-hidden">
    <!-- Particules flottantes -->
    <div class="particles">
        <?php for ($i = 0; $i < 12; $i++): ?>
            <div class="particle" style="
                width: <?= rand(6, 16) ?>px; 
                height: <?= rand(6, 16) ?>px; 
                left: <?= rand(0, 100) ?>%; 
                animation-delay: <?= rand(0, 8) ?>s;
                animation-duration: <?= rand(6, 12) ?>s;
            "></div>
        <?php endfor; ?>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            
            <?php if ($success && $email_sent): ?>
                <!-- Succès - Email envoyé -->
                <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl p-8 text-center animate-slide-up">
                    <!-- Icône de succès -->
                    <div class="mx-auto w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mb-6 animate-bounce-gentle">
                        <i class="fas fa-paper-plane text-white text-3xl"></i>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-800 mb-4 animate-fade-scale">
                        Email envoyé !
                    </h1>
                    
                    <div class="space-y-4 mb-8">
                        <p class="text-lg text-gray-600 animate-fade-scale" style="animation-delay: 0.2s;">
                            <?= htmlspecialchars($message) ?>
                        </p>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 animate-fade-scale" style="animation-delay: 0.4s;">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-clock text-blue-600 mr-2"></i>
                                <strong>Le lien expire dans 10 minutes</strong> pour votre sécurité.
                            </p>
                        </div>
                        
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 animate-fade-scale" style="animation-delay: 0.6s;">
                            <p class="text-sm text-amber-800">
                                <i class="fas fa-info-circle text-amber-600 mr-2"></i>
                                Vérifiez également votre dossier <strong>spam/courrier indésirable</strong>.
                            </p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <button onclick="location.reload()" 
                                class="btn-hover w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 animate-fade-scale" 
                                style="animation-delay: 0.8s;">
                            <i class="fas fa-redo-alt"></i>
                            <span>Renvoyer un autre lien</span>
                        </button>
                        
                        <a href="/" 
                           class="btn-hover w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 border border-gray-200 animate-fade-scale" 
                           style="animation-delay: 1s;">
                            <i class="fas fa-home"></i>
                            <span>Retour à l'accueil</span>
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Formulaire de demande -->
                <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl p-8 animate-slide-up">
                    <!-- Icône principale -->
                    <div class="mx-auto w-20 h-20 bg-gradient-to-br from-green-600 to-green-700 rounded-full flex items-center justify-center mb-6 animate-pulse-custom">
                        <i class="fas fa-envelope-open-text text-white text-3xl"></i>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-800 mb-4 text-center animate-fade-scale">
                        Nouveau lien de vérification
                    </h1>
                    
                    <p class="text-center text-gray-600 mb-8 animate-fade-scale" style="animation-delay: 0.2s;">
                        Saisissez vos informations pour recevoir un nouveau lien de vérification par e-mail.
                    </p>
                    
                    <?php if ($error): ?>
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl animate-fade-scale" style="animation-delay: 0.3s;">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                                <p class="text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-6 animate-fade-scale" style="animation-delay: 0.4s;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope text-gray-400 mr-2"></i>
                                Adresse e-mail
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   required 
                                   placeholder="votre@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="form-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="numero_pro" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-id-card text-gray-400 mr-2"></i>
                                Numéro professionnel
                            </label>
                            <input type="text" 
                                   id="numero_pro" 
                                   name="numero_pro" 
                                   required 
                                   placeholder="PR-20240101-ABC123"
                                   value="<?= htmlspecialchars($_POST['numero_pro'] ?? '') ?>"
                                   class="form-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <strong>Rappel :</strong> Ces informations doivent correspondre exactement à celles de votre inscription.
                            </p>
                        </div>
                        
                        <button type="submit" 
                                class="btn-hover w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2">
                            <i class="fas fa-paper-plane"></i>
                            <span>Envoyer le lien de vérification</span>
                        </button>
                    </form>
                    
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="flex justify-center space-x-6 text-sm">
                            <a href="/login" class="text-green-600 hover:text-green-800 font-medium">
                                <i class="fas fa-sign-in-alt mr-1"></i>
                                Se connecter
                            </a>
                            <a href="/contact" class="text-gray-600 hover:text-gray-800 font-medium">
                                <i class="fas fa-life-ring mr-1"></i>
                                Support
                            </a>
                            <a href="/" class="text-gray-600 hover:text-gray-800 font-medium">
                                <i class="fas fa-home mr-1"></i>
                                Accueil
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <!-- Logo en bas à droite -->
    <div class="fixed bottom-6 right-6 animate-pulse-custom">
        <div class="bg-white/10 backdrop-blur-sm rounded-full p-3">
            <img src="/img/logo-automoclick.png" alt="Automoclick" class="w-8 h-8 opacity-70">
        </div>
    </div>
    
    <script>
        // Animation d'entrée progressive
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate-fade-scale');
            elements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
        });
        
        // Validation en temps réel
        const emailInput = document.getElementById('email');
        const numeroInput = document.getElementById('numero_pro');
        
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                if (this.validity.valid) {
                    this.classList.add('border-green-300');
                    this.classList.remove('border-red-300');
                } else {
                    this.classList.add('border-red-300');
                    this.classList.remove('border-green-300');
                }
            });
        }
        
        if (numeroInput) {
            numeroInput.addEventListener('input', function() {
                const pattern = /^PR-\d{8}-[A-F0-9]{6}$/;
                if (pattern.test(this.value)) {
                    this.classList.add('border-green-300');
                    this.classList.remove('border-red-300');
                } else {
                    this.classList.add('border-red-300');
                    this.classList.remove('border-green-300');
                }
            });
        }
    </script>
</body>
</html>