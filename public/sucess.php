<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte créé avec succès - AutomoClick</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .success-animation {
            animation: successPulse 2s ease-in-out;
        }
        @keyframes successPulse {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        .slide-in {
            animation: slideIn 0.8s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class=" min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">


        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 slide-in" style="animation-delay: 0.2s;">
            <!-- Success Icon -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4 success-animation">
                    <i class="fas fa-check-circle text-4xl text-green-500"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Compte créé avec succès !</h2>
                <p class="text-gray-600">Bienvenue dans la communauté AutomoClick</p>
            </div>

            <!-- Information Cards -->
            <div class="space-y-4 mb-6">
                <!-- Email Verification Card -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 slide-in" style="animation-delay: 0.4s;">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-envelope-open text-blue-500 text-xl mt-1"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-blue-800 mb-1">Vérification par email requise</h3>
                            <p class="text-sm text-blue-700">
                                Un email de vérification a été envoyé à votre adresse. 
                                Cliquez sur le lien pour activer votre compte.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Credentials Card -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 slide-in" style="animation-delay: 0.6s;">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-key text-green-500 text-xl mt-1"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-green-800 mb-1">Identifiants envoyés</h3>
                            <p class="text-sm text-green-700">
                                Vos identifiants de connexion et mot de passe temporaire 
                                ont été envoyés par email.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Security Card -->
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 slide-in" style="animation-delay: 0.8s;">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shield-alt text-amber-500 text-xl mt-1"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-amber-800 mb-1">Sécurité de votre compte</h3>
                            <p class="text-sm text-amber-700">
                                Nous vous recommandons de changer votre mot de passe 
                                après votre première connexion.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Steps -->
            <div class="mb-6 slide-in" style="animation-delay: 1s;">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Prochaines étapes :</h3>
                <div class="space-y-2">
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold">1</div>
                        <span class="text-sm text-gray-600">Vérifiez votre boîte email</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold">2</div>
                        <span class="text-sm text-gray-600">Cliquez sur le lien de vérification</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold">3</div>
                        <span class="text-sm text-gray-600">Connectez-vous avec vos identifiants</span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3 slide-in" style="animation-delay: 1.2s;">
                <button onclick="checkEmail()" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                    <i class="fas fa-envelope"></i>
                    <span>Ouvrir ma boîte email</span>
                </button>
                
                <div class="flex space-x-3">
                    <a href="/connexion" 
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors duration-200 text-sm text-center">
                        Se connecter
                    </a>
                </div>
            </div>

            <!-- Help Section -->
            <div class="mt-6 pt-4 border-t border-gray-200 text-center slide-in" style="animation-delay: 1.4s;">
                <p class="text-xs text-gray-500 mb-2">Vous ne recevez pas l'email ?</p>
                <div class="space-x-4">
                    <a href="#" onclick="showHelp()" class="text-xs text-blue-600 hover:text-blue-800">Centre d'aide</a>
                    <a href="mailto:support@automoclick.com" class="text-xs text-blue-600 hover:text-blue-800">Contacter le support</a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 slide-in" style="animation-delay: 1.6s;">
            <p class="text-white text-sm opacity-90">
                © 2024 AutomoClick. Tous droits réservés.
            </p>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast" class="fixed top-4 right-4 bg-white rounded-lg shadow-lg p-4 transform translate-x-full transition-transform duration-300 z-50">
        <div class="flex items-center space-x-3">
            <i class="fas fa-info-circle text-blue-500"></i>
            <span class="text-sm text-gray-700" id="toastMessage"></span>
        </div>
    </div>

    <script>
        // Function to detect email provider and open webmail
        function checkEmail() {
            // Get user email from URL params or session (you may need to pass this)
            const userEmail = new URLSearchParams(window.location.search).get('email') || '';
            const domain = userEmail.split('@')[1];
            
            let webmailUrl = 'https://mail.google.com'; // Default to Gmail
            
            if (domain) {
                const webmailProviders = {
                    'gmail.com': 'https://mail.google.com',
                    'outlook.com': 'https://outlook.live.com',
                    'hotmail.com': 'https://outlook.live.com',
                    'yahoo.com': 'https://mail.yahoo.com',
                    'yahoo.fr': 'https://mail.yahoo.com'
                };
                
                webmailUrl = webmailProviders[domain] || 'https://mail.google.com';
            }
            
            window.open(webmailUrl, '_blank');
        }
        
        
        // Function to show help modal/page
        function showHelp() {
            showToast("Redirection vers le centre d'aide...");
            setTimeout(() => {
                window.open('/aide', '_blank');
            }, 1000);
        }
        
        // Toast notification function
        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            toast.classList.remove('translate-x-full');
            
            setTimeout(() => {
                toast.classList.add('translate-x-full');
            }, 3000);
        }
        
        // Auto-hide animations after page load
        window.addEventListener('load', () => {
            // Add any additional loading effects here
        });
    </script>
</body>
</html>
