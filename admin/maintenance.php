<?php
// admin/maintenance.php
session_start();
require_once '../includes/maintenance.php';

// Vérifier si l'admin est connecté
// if (!isset($_SESSION['admin_logged_in'])) {
//     header('Location: login.php');
//     exit;
// }

$maintenance = new MaintenanceMode();
$config = include '../config/maintenance.php';

// Traitement des actions
if ($_POST) {
    if (isset($_POST['enable'])) {
        $message = $_POST['message'] ?? '';
        $estimatedTime = $_POST['estimated_time'] ?? '';
        $maintenance->enable($message, $estimatedTime);
        $success = "Mode maintenance activé";
    } elseif (isset($_POST['disable'])) {
        $maintenance->disable();
        $success = "Mode maintenance désactivé";
    }
    
    // Recharger la config
    $config = include '../config/maintenance.php';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Maintenance - AutomoClick Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800">
                        <i class="fas fa-tools mr-3"></i>
                        Gestion du Mode Maintenance
                    </h1>
                </div>
                
                <div class="p-6">
                    <?php if (isset($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Statut actuel -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4">Statut Actuel</h2>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full <?php echo $config['enabled'] ? 'bg-red-500' : 'bg-green-500'; ?> mr-2"></div>
                                <span class="font-medium <?php echo $config['enabled'] ? 'text-red-600' : 'text-green-600'; ?>">
                                    <?php echo $config['enabled'] ? 'Maintenance ACTIVÉE' : 'Site OPÉRATIONNEL'; ?>
                                </span>
                            </div>
                            
                            <?php if ($config['enabled']): ?>
                            <a href="/?bypass=<?php echo $config['bypass_key']; ?>" 
                               target="_blank" 
                               class="text-blue-600 hover:underline text-sm">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                Voir la page de maintenance
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Formulaire de gestion -->
                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Message de maintenance
                            </label>
                            <textarea name="message" 
                                      rows="3" 
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500"
                                      placeholder="Message à afficher aux utilisateurs..."><?php echo htmlspecialchars($config['message']); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Heure de fin estimée
                            </label>
                            <input type="datetime-local" 
                                   name="estimated_time" 
                                   value="<?php echo $config['estimated_time'] ? date('Y-m-d\TH:i', strtotime($config['estimated_time'])) : ''; ?>"
                                   class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                        
                        <div class="flex space-x-4">
                            <?php if (!$config['enabled']): ?>
                            <button type="submit" 
                                    name="enable" 
                                    class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg flex items-center">
                                <i class="fas fa-power-off mr-2"></i>
                                Activer la Maintenance
                            </button>
                            <?php else: ?>
                            <button type="submit" 
                                    name="disable" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg flex items-center">
                                <i class="fas fa-play mr-2"></i>
                                Désactiver la Maintenance
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>    
</body>
</html>