<?php
require_once 'header.php';
session_start();
require 'vendor/autoload.php';
require_once 'includes/webhook.php';
require_once('db/dbconnect2.php');
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Clé secrète utilisée pour signer le token JWT
$key = "0a4fa423cf1e4533d6c394f094faf1c75921853e71ffcdf3f07ef8a73ccf4a3d";

$success = false;
$error = null;
$userEmail = null;
$userId = null;

// Vérifier si le token est présent dans l'URL
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = "Le jeton de vérification est manquant.";
} else {
    $jwt = $_GET['token'];
    
    try {
        // Décoder le token JWT avec la clé secrète et l'algorithme HS256
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        
        // Décoder et décompresser les données
        $compressedPayload = base64_decode($decoded->data);
        if ($compressedPayload === false) {
            throw new Exception("Erreur lors de la décodification base64 des données.");
        }
        
        $decompressedPayload = gzdecode($compressedPayload);
        if ($decompressedPayload === false) {
            throw new Exception("Erreur lors de la décompression des données.");
        }
        
        // Décoder les données JSON
        $payload = json_decode($decompressedPayload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erreur lors de la décodification du JSON : " . json_last_error_msg());
        }
        
        // Vérifier si l'email n'est pas déjà vérifié
        $checkStmt = $db->prepare("SELECT verif_email FROM login_pro WHERE numero_pro = :id");
        $checkStmt->execute(['id' => $payload['id']]);
        $currentStatus = $checkStmt->fetchColumn();
        
        if ($currentStatus == 1) {
            $error = "Cette adresse e-mail a déjà été vérifiée.";
        } else {
            // Mettre à jour le statut de vérification
            $stmt = $db->prepare("UPDATE login_pro SET verif_email = 1 WHERE numero_pro = :id");
            $result = $stmt->execute(['id' => $payload['id']]);
            
            if ($result) {
                $success = true;
                $userEmail = $payload['email'];
                $userId = $payload['id'];
                $_SESSION['email_verif'] = false;
            } else {
                throw new Exception("Erreur lors de la mise à jour de la base de données.");
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $success ? 'Email vérifié avec succès' : 'Erreur de vérification' ?> - Automoclick</title>
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
        
        @keyframes bounceIn {
            0%, 20%, 40%, 60%, 80% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }
        
        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .animate-slide-up {
            animation: slideInUp 0.8s ease-out;
        }
        
        .animate-bounce-in {
            animation: bounceIn 1s ease-in-out;
        }
        
        .animate-pulse-custom {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .animate-spin-slow {
            animation: spin 3s linear infinite;
        }
        
        .animate-fade-scale {
            animation: fadeInScale 0.6s ease-out;
        }
        
        /* Gradient personnalisé Automoclick */
        .bg-automoclick-gradient {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #60a5fa 100%);
        }
        
        .bg-success-gradient {
            background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
        }
        
        .bg-error-gradient {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 50%, #f87171 100%);
        }
        
        /* Effet de particules */
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
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
        }
        
        /* Hover effects */
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
                    <!-- Icône de succès animée -->
                    <div class="mx-auto w-20 h-20 bg-success-gradient rounded-full flex items-center justify-center mb-6 animate-bounce-in">
                        <i class="fas fa-check text-white text-3xl"></i>
                    </div>
                    
                    <!-- Titre -->
                    <h1 class="text-3xl font-bold text-gray-800 mb-4 animate-fade-scale">
                        Email vérifié !
                    </h1>
                    
                    <!-- Message -->
                    <div class="space-y-4 mb-8">
                        <p class="text-lg text-gray-600 animate-fade-scale" style="animation-delay: 0.2s;">
                            Félicitations ! Votre adresse e-mail a été vérifiée avec succès.
                        </p>
                        
                        <?php if ($userEmail): ?>
                            <div class="bg-green-50 border border-green-200 rounded-xl p-4 animate-fade-scale" style="animation-delay: 0.4s;">
                                <p class="text-sm text-green-800">
                                    <i class="fas fa-envelope text-green-600 mr-2"></i>
                                    <strong><?= htmlspecialchars($userEmail) ?></strong>
                                </p>
                                <?php if ($userId): ?>
                                    <p class="text-xs text-green-600 mt-1">
                                        ID: <?= htmlspecialchars($userId) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="bg-green-50 border border-green-200 rounded-xl p-4 animate-fade-scale" style="animation-delay: 0.6s;">
                            <p class="text-sm text-green-800">
                                <i class="fas fa-info-circle text-green-600 mr-2"></i>
                                Vous pouvez maintenant accéder à toutes les fonctionnalités de votre compte professionnel.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="space-y-3">
                        <a href="connexion" 
                           class="btn-hover w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 animate-fade-scale" 
                           style="animation-delay: 0.8s;">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Se connecter</span>
                        </a>
                        
                        <a href="/" 
                           class="btn-hover w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 border border-gray-200 animate-fade-scale" 
                           style="animation-delay: 1s;">
                            <i class="fas fa-home"></i>
                            <span>Retour à l'accueil</span>
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Erreur -->
                <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl p-8 text-center animate-slide-up">
                    <!-- Icône d'erreur animée -->
                    <div class="mx-auto w-20 h-20 bg-error-gradient rounded-full flex items-center justify-center mb-6 animate-bounce-in">
                        <i class="fas fa-exclamation-triangle text-white text-3xl"></i>
                    </div>
                    
                    <!-- Titre -->
                    <h1 class="text-3xl font-bold text-gray-800 mb-4 animate-fade-scale">
                        Erreur de vérification
                    </h1>
                    
                    <!-- Message d'erreur -->
                    <div class="space-y-4 mb-8">
                        <p class="text-lg text-gray-600 animate-fade-scale" style="animation-delay: 0.2s;">
                            Nous n'avons pas pu vérifier votre adresse e-mail.
                        </p>
                        
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 animate-fade-scale" style="animation-delay: 0.4s;">
                            <p class="text-sm text-red-800">
                                <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                <strong>Détails de l'erreur :</strong><br>
                                <?= htmlspecialchars($error) ?>
                            </p>
                        </div>
                        
                        <div class="bg-green-50 border border-green-200 rounded-xl p-4 animate-fade-scale" style="animation-delay: 0.6s;">
                            <p class="text-sm text-green-800">
                                <i class="fas fa-lightbulb text-green-600 mr-2"></i>
                                <strong>Que faire ?</strong><br>
                                • Vérifiez que le lien n'est pas expiré<br>
                                • Demandez un nouveau lien de vérification<br>
                                • Contactez le support si le problème persiste
                            </p>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="space-y-3">
                        <a href="/resend-verification" 
                           class="btn-hover w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 animate-fade-scale" 
                           style="animation-delay: 0.8s;">
                            <i class="fas fa-redo-alt"></i>
                            <span>Renvoyer un lien de vérification</span>
                        </a>
                        
                        <a href="/contact" 
                           class="btn-hover w-full bg-orange-500 hover:bg-orange-600 text-white font-medium py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 animate-fade-scale" 
                           style="animation-delay: 0.9s;">
                            <i class="fas fa-life-ring"></i>
                            <span>Contacter le support</span>
                        </a>
                        
                        <a href="/" 
                           class="btn-hover w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-3 px-6 rounded-xl inline-flex items-center justify-center space-x-2 border border-gray-200 animate-fade-scale" 
                           style="animation-delay: 1s;">
                            <i class="fas fa-home"></i>
                            <span>Retour à l'accueil</span>
                        </a>
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
        // Animation d'entrée progressive des éléments
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate-fade-scale');
            elements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.2}s`;
            });
        });
        
        // Effet de particules interactives au clic
        document.addEventListener('click', function(e) {
            const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'];
            
            for (let i = 0; i < 6; i++) {
                const particle = document.createElement('div');
                particle.style.position = 'fixed';
                particle.style.left = e.clientX + 'px';
                particle.style.top = e.clientY + 'px';
                particle.style.width = '4px';
                particle.style.height = '4px';
                particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                particle.style.borderRadius = '50%';
                particle.style.pointerEvents = 'none';
                particle.style.zIndex = '9999';
                particle.style.animation = `
                    particle-burst 0.6s ease-out forwards
                `;
                
                const angle = (Math.PI * 2 * i) / 6;
                const velocity = 50;
                const dx = Math.cos(angle) * velocity;
                const dy = Math.sin(angle) * velocity;
                
                particle.style.setProperty('--dx', dx + 'px');
                particle.style.setProperty('--dy', dy + 'px');
                
                document.body.appendChild(particle);
                
                setTimeout(() => {
                    if (particle.parentNode) {
                        particle.parentNode.removeChild(particle);
                    }
                }, 600);
            }
        });
        
        // Ajouter l'animation CSS pour les particules de clic
        const style = document.createElement('style');
        style.textContent = `
            @keyframes particle-burst {
                to {
                    transform: translate(var(--dx), var(--dy)) scale(0);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>