<?php
// templates/maintenance.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($this->config['title']); ?> - AutomoClick</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .maintenance-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="maintenance-bg min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-2xl p-8 text-center">
            <!-- Logo ou icône -->
            <div class="mb-6">
                <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto pulse-slow">
                    <i class="fas fa-tools text-3xl text-blue-600"></i>
                </div>
            </div>
            
            <!-- Titre -->
            <h1 class="text-2xl font-bold text-gray-800 mb-4">
                <?php echo htmlspecialchars($this->config['title']); ?>
            </h1>
            
            <!-- Message -->
            <p class="text-gray-600 mb-6 leading-relaxed">
                <?php echo htmlspecialchars($this->config['message']); ?>
            </p>
            
            <!-- Temps estimé -->
            <?php if (!empty($estimatedTime)): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-center">
                    <i class="fas fa-clock text-blue-600 mr-2"></i>
                    <span class="text-sm text-blue-800">
                        Retour estimé : <strong><?php echo $estimatedTime; ?></strong>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Barre de progression animée -->
            <div class="mb-6">
                <div class="bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full animate-pulse" style="width: 60%"></div>
                </div>
            </div>
            
            <!-- Informations de contact -->
            <div class="text-sm text-gray-500">
                <p class="mb-2">
                    <i class="fas fa-envelope mr-2"></i>
                    Pour toute urgence : 
                    <a href="mailto:<?php echo htmlspecialchars($this->config['contact_email']); ?>" 
                       class="text-blue-600 hover:underline">
                        <?php echo htmlspecialchars($this->config['contact_email']); ?>
                    </a>
                </p>
                <p>
                    <i class="fas fa-globe mr-2"></i>
                    Suivez-nous sur nos réseaux sociaux pour les mises à jour
                </p>
            </div>
            
            <!-- Réseaux sociaux -->
            <div class="flex justify-center space-x-4 mt-6">
                <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                    <i class="fab fa-facebook-f text-xl"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                    <i class="fab fa-twitter text-xl"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-blue-700 transition-colors">
                    <i class="fab fa-linkedin-in text-xl"></i>
                </a>
            </div>
        </div>
        
        <!-- Bouton de rechargement -->
        <div class="text-center mt-6">
            <button onclick="location.reload()" 
                    class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-6 py-2 rounded-lg transition-all duration-300">
                <i class="fas fa-sync-alt mr-2"></i>
                Actualiser la page
            </button>
        </div>
    </div>
    
    <!-- Auto-refresh toutes les 30 secondes -->
    <script>
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
