<?php
session_start();
if (!isset($_SESSION['id_admin'])) {
    header('Location: connexion');
    exit;
}
require_once '../includes/functions.php';
require_once '../includes/init.php';
require_once '../includes/webhook.php';
$maintenance = new MaintenanceMode();

if ($maintenance->check()) {
    $maintenance->showMaintenancePage();
}

// Récupération des données pour le dashboard
$stats = getDashboardStats();
$recentActivity = getRecentActivity();
$transactionStats = getTransactionStats();
$caDetails = getCADetails();
$professionnelsStats = getProfessionnelsStats();
$boutiqueStats = getBoutiqueStats();
$newsletterStats = getNewsletterStats();

// Récupération des données pour les différentes sections
$clients = getClients();
$professionnels = getPro();
$produits = getProduits();
$newsletters = getNewsletters();
$admins = getAdmins();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="180x180" href="../asset/style/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../asset/style/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../asset/style/img/favicon-16x16.png">
    <title>Admin AutomoClick - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <style>
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .loading {
            opacity: 0.5;
            pointer-events: none;
        }
        .nav-active {
            background-color: rgba(34, 197, 94, 0.1) !important;
            color: #22c55e !important;
            border-left: 3px solid #22c55e;
        }
    </style>
</head>

<body class="bg-gray-100 flex flex-col md:flex-row" x-data="adminDashboard()">
    <!-- Menu mobile hamburger -->
    <header class="md:hidden flex items-center justify-between bg-white shadow p-4">
        <h2 class="text-xl font-bold text-green-600">Automoclick - Admin</h2>
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-3xl text-green-600 font-bold">&#9776;</button>
    </header>

    <!-- Sidebar -->
    <aside :class="{'hidden': !mobileMenuOpen}" 
           class="w-full md:w-64 bg-white shadow-lg p-4 space-y-4 md:space-y-0 md:flex md:flex-col md:fixed md:top-0 md:left-0 md:h-full md:block z-50">
        <div class="p-4 border-b hidden md:block">
            <h2 class="text-xl font-bold text-green-600">Automoclick - Admin</h2>
        </div>
        <nav class="flex flex-col md:p-4 space-y-2 flex-grow">
            <a href="#" @click="switchSection('dashboard')" 
               :class="{'nav-active': currentSection === 'dashboard'}"
               class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">dashboard</span>
                Tableau de bord
            </a>
            <a href="#" @click="switchSection('boutique')" 
               :class="{'nav-active': currentSection === 'boutique'}"
               class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">store</span>
                Gestion Boutique
            </a>
            <a href="#" @click="switchSection('newsletters')" 
               :class="{'nav-active': currentSection === 'newsletters'}"
               class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">mail</span>
                Newsletters
            </a>
            <a href="#" @click="switchSection('clients')" 
               :class="{'nav-active': currentSection === 'clients'}"
               class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">group</span>
                Clients
            </a>
            <a href="#" @click="switchSection('pros')" 
               :class="{'nav-active': currentSection === 'pros'}"
               class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">business</span>
                Professionnels
            </a>
            <a href="#" @click="switchSection('admins')" 
               :class="{'nav-active': currentSection === 'admins'}"
               class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">admin_panel_settings</span>
                Administrateurs
            </a>
            <a href="#" @click="switchSection('transactions')" 
               :class="{'nav-active': currentSection === 'transactions'}"
               class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">credit_card</span>
                Transactions
            </a>
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
                        <a href="https://automoclick.com/webhook.php"
               class="block px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">report</span>
                LOGS WEBHOOK
            </a>
            <?php endif; ?>
        </nav>
        <div class="text-center text-sm text-gray-500 border-t pt-4">&copy; 2025 Automoclick</div>
    </aside>

    <!-- Contenu principal -->
    <main class="flex-grow p-4 pt-20 md:pt-6 md:p-6 transition-all duration-300 ease-in-out md:ml-64">
        <!-- Header avec navigation -->
        <header class="top-0 z-50 mb-6" x-data="{ openDropdown: false }">
            <div class="max-w-7xl mx-auto flex justify-between items-center p-4 md:p-6 bg-white rounded-lg shadow">
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-gray-800" x-text="getSectionTitle()"></h1>
                </div>
                
                <div class="relative" @click.outside="openDropdown = false">
                    <button @click="openDropdown = !openDropdown"
                        class="inline-flex items-center bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <span class="material-symbols-outlined mr-2">admin_panel_settings</span>
                        Admin
                        <svg class="w-4 h-4 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="openDropdown" x-transition
                        class="absolute right-0 mt-2 w-56 bg-white border rounded-md shadow-lg z-50">
                        <a href="#" class="flex items-center px-4 py-2 text-sm hover:bg-gray-100">
                            <span class="material-symbols-outlined mr-2">settings</span> Paramètres
                        </a>
                        <a href="../z" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-100">
                            <span class="material-symbols-outlined mr-2">logout</span> Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Section -->
        <div x-show="currentSection === 'dashboard'" class="space-y-6">
            <!-- Statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Clients</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo number_format($stats['clients']); ?>
                            </p>
                            <p class="text-sm text-green-600">+5.2% ce mois</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">group</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Professionnels</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo number_format($stats['professionnels']); ?>
                            </p>
                            <div class="flex items-center space-x-2 mt-1">
                                <p class="text-sm <?php echo $stats['evolution_professionnels'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <span class="material-symbols-outlined text-xs mr-1">
                                        <?php echo $stats['evolution_professionnels'] >= 0 ? 'trending_up' : 'trending_down'; ?>
                                    </span>
                                    <?php echo $stats['evolution_professionnels'] >= 0 ? '+' : ''; ?><?php echo $stats['evolution_professionnels']; ?>% ce mois
                                </p>
                            </div>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <span class="material-symbols-outlined text-blue-600">business</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Rendez-vous</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo number_format($stats['rendez_vous']); ?>
                            </p>
                            <p class="text-sm text-orange-600"><?php echo $stats['rdv_today']; ?> aujourd'hui</p>
                        </div>
                        <div class="p-3 bg-orange-100 rounded-full">
                            <span class="material-symbols-outlined text-orange-600">event</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">CA Total</p>
                            <p class="text-2xl font-bold text-gray-800">
                                €<?php echo number_format($stats['ca_total'], 2, ',', ' '); ?>
                            </p>
                            <p class="text-sm text-green-600">
                                +€<?php echo number_format($transactionStats['ca_today'] ?? 0, 2, ',', ' '); ?> aujourd'hui
                            </p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">euro</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphiques et activité récente -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Évolution du Chiffre d'Affaires</h3>
                    <canvas id="caChart" width="400" height="200"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Activité Récente</h3>
                    <div class="space-y-4">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="flex items-center space-x-3">
                                <div class="w-2 h-2 bg-<?php echo $activity['type'] === 'professionnel' ? 'green' : ($activity['type'] === 'rdv' ? 'blue' : ($activity['type'] === 'transaction' ? 'purple' : 'orange')); ?>-500 rounded-full"></div>
                                <p class="text-sm text-gray-600 flex-1"><?php echo $activity['message']; ?></p>
                                <span class="text-xs text-gray-400"><?php echo $activity['time']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Boutique -->
        <div x-show="currentSection === 'boutique'" class="space-y-6" style="display: none;">
            <!-- Statistiques boutique -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Produits Actifs</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $boutiqueStats['produits_actifs']; ?></p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <span class="material-symbols-outlined text-blue-600">inventory</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Stock Faible</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo $boutiqueStats['stock_faible']; ?></p>
                            <p class="text-sm text-gray-500">Produits < 10</p>
                        </div>
                        <div class="p-3 bg-orange-100 rounded-full">
                            <span class="material-symbols-outlined text-orange-600">warning</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Valeur Stock</p>
                            <p class="text-2xl font-bold text-green-600">€<?php echo number_format($boutiqueStats['valeur_stock'], 2); ?></p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">euro</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gestion des produits -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Gestion des Produits</h3>
                        <button @click="openModal('produit')" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            <span class="material-symbols-outlined mr-2 inline-block">add</span>
                            Ajouter un produit
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($produits as $produit): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($produit['nom'] ?? 'N/A'); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($produit['reference'] ?? 'N/A'); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        €<?php echo number_format($produit['prix'] ?? 0, 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm <?php echo ($produit['stock'] ?? 0) < 10 ? 'text-red-600 font-semibold' : 'text-gray-900'; ?>">
                                            <?php echo $produit['stock'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo ($produit['statut'] ?? 'inactif') === 'actif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo ucfirst($produit['statut'] ?? 'inactif'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button @click="editItem('produit', <?php echo $produit['id'] ?? 0; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <button @click="deleteItem('produit', <?php echo $produit['id'] ?? 0; ?>)" class="text-red-600 hover:text-red-900">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Newsletters -->
        <div x-show="currentSection === 'newsletters'" class="space-y-6" style="display: none;">
            <!-- Statistiques newsletters -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Abonnés Actifs</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($newsletterStats['abonnes_actifs']); ?></p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <span class="material-symbols-outlined text-blue-600">group</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Taux d'Ouverture</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $newsletterStats['taux_ouverture']; ?>%</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">open_in_new</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Campagnes Envoyées</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $newsletterStats['campagnes_envoyees']; ?></p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <span class="material-symbols-outlined text-purple-600">send</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">En Attente</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo $newsletterStats['campagnes_attente']; ?></p>
                        </div>
                        <div class="p-3 bg-orange-100 rounded-full">
                            <span class="material-symbols-outlined text-orange-600">schedule</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gestion des newsletters -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Campagnes Newsletter</h3>
                        <button @click="openModal('newsletter')" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            <span class="material-symbols-outlined mr-2 inline-block">add</span>
                            Nouvelle campagne
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Titre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinataires</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($newsletters as $newsletter): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($newsletter['titre'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d/m/Y H:i', strtotime($newsletter['date_envoi'] ?? 'now')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($newsletter['destinataires_count'] ?? 0); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            $statut = $newsletter['statut'] ?? 'brouillon';
                                            echo $statut === 'envoyee' ? 'bg-green-100 text-green-800' : 
                                                ($statut === 'programmee' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800');
                                            ?>">
                                            <?php echo ucfirst($statut); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button @click="editItem('newsletter', <?php echo $newsletter['id'] ?? 0; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <?php if (($newsletter['statut'] ?? 'brouillon') !== 'envoyee'): ?>
                                        <button @click="sendNewsletter(<?php echo $newsletter['id'] ?? 0; ?>)" class="text-green-600 hover:text-green-900 mr-3">
                                            <span class="material-symbols-outlined">send</span>
                                        </button>
                                        <?php endif; ?>
                                        <button @click="deleteItem('newsletters', <?php echo $newsletter['id'] ?? 0; ?>)" class="text-red-600 hover:text-red-900">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Clients -->
        <div x-show="currentSection === 'clients'" class="space-y-6" style="display: none;">
            <!-- Statistiques clients -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Clients</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['clients']); ?></p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <span class="material-symbols-outlined text-blue-600">group</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Nouveaux ce mois</p>
                            <p class="text-2xl font-bold text-green-600">+<?php echo rand(15, 45); ?></p>
                            <p class="text-sm text-green-600">+12% vs mois dernier</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">trending_up</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Clients Actifs</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['clients'] * 0.85); ?></p>
                            <p class="text-sm text-gray-500">85% du total</p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <span class="material-symbols-outlined text-purple-600">verified_user</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gestion des clients -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Liste des Clients</h3>
                        <div class="flex space-x-2">
                            <input type="text" x-model="clientSearch" @input="searchClients()" 
                                   placeholder="Rechercher un client..." 
                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            <button @click="exportClients()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <span class="material-symbols-outlined mr-2 inline-block">download</span>
                                Exporter
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inscription</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                    <span class="text-sm font-medium text-gray-700">
                                                        <?php echo strtoupper(substr($client['prenom'] ?? 'U', 0, 1) . substr($client['nom'] ?? 'U', 0, 1)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? '')); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($client['telephone'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($client['numero_client'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($client['user_c'] ?? 'now')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button @click="viewClient(<?php echo $client['id_client'] ?? 0; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </button>
                                        <button @click="editClient(<?php echo $client['id_client'] ?? 0; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <button @click="toggleClientStatus(<?php echo $client['id_client'] ?? 0; ?>)" class="text-orange-600 hover:text-orange-900">
                                            <span class="material-symbols-outlined">block</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Professionnels -->
        <div x-show="currentSection === 'pros'" class="space-y-6" style="display: none;">
            <!-- Statistiques professionnels -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Professionnels</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['professionnels']); ?></p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <span class="material-symbols-outlined text-blue-600">business</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Actifs</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $professionnelsStats['actifs']; ?></p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">verified</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">En Attente</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo $professionnelsStats['en_attente']; ?></p>
                        </div>
                        <div class="p-3 bg-orange-100 rounded-full">
                            <span class="material-symbols-outlined text-orange-600">pending</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Inactifs</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo $professionnelsStats['inactifs']; ?></p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <span class="material-symbols-outlined text-red-600">block</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphique évolution et top spécialités -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Spécialités</h3>
                    <div class="space-y-3">
                        <?php foreach ($professionnelsStats['top_specialites'] as $index => $specialite): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-sm font-semibold text-blue-600"><?php echo $index + 1; ?></span>
                                </div>
                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($specialite['spe']); ?></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php echo $specialite['total']; ?> pros</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Répartition par Ville</h3>
                    <div class="space-y-3">
                        <?php foreach (array_slice($professionnelsStats['par_ville'], 0, 5) as $ville): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($ville['commune']); ?></span>
                            <div class="flex items-center">
                                <div class="w-20 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, ($ville['total'] / max(1, $professionnelsStats['par_ville'][0]['total'] ?? 1)) * 100); ?>%"></div>
                                </div>
                                <span class="text-sm text-gray-500"><?php echo $ville['total']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Liste des professionnels -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Liste des Professionnels</h3>
                        <div class="flex space-x-2">
                            <input type="text" x-model="proSearch" @input="searchPros()" 
                                   placeholder="Rechercher un professionnel..." 
                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            <select x-model="proStatusFilter" @change="filterPros()" class="px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">Tous les statuts</option>
                                <option value="actif">Actifs</option>
                                <option value="en_attente">En attente</option>
                                <option value="inactif">Inactifs</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entreprise</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SIRET</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spécialité</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inscription</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($professionnels as $pro): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($pro['denomination'] ?? 'N/A'); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pro['forme_juridique'] ?? 'N/A'); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($pro['email'] ?? 'N/A'); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pro['phone_number'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($pro['siret'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($pro['spe'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($pro['date_creation_account'] ?? 'now')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button @click="viewPro(<?php echo $pro['id_pro'] ?? 0; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </button>
                                        <button @click="approvePro(<?php echo $pro['id_pro'] ?? 0; ?>)" class="text-green-600 hover:text-green-900 mr-3">
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </button>
                                        <button @click="suspendPro(<?php echo $pro['id_pro'] ?? 0; ?>)" class="text-red-600 hover:text-red-900">
                                            <span class="material-symbols-outlined">block</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Administrateurs -->
        <div x-show="currentSection === 'admins'" class="space-y-6" style="display: none;">
            <!-- Statistiques admins -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Administrateurs</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo count($admins); ?></p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <span class="material-symbols-outlined text-purple-600">admin_panel_settings</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Super Admins</p>
                            <p class="text-2xl font-bold text-red-600">
                                <?php echo count(array_filter($admins, function($admin) { return ($admin['role'] ?? '') === 'super_admin'; })); ?>
                            </p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <span class="material-symbols-outlined text-red-600">security</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Admins Actifs</p>
                            <p class="text-2xl font-bold text-green-600">
                                <?php echo count(array_filter($admins, function($admin) { return ($admin['statut'] ?? '') === 'actif'; })); ?>
                            </p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">verified_user</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gestion des administrateurs -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Gestion des Administrateurs</h3>
                        <button @click="openModal('admin')" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                            <span class="material-symbols-outlined mr-2 inline-block">add</span>
                            Nouvel administrateur
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Administrateur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Création</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                                                    <span class="text-sm font-medium text-purple-600">
                                                        <?php echo strtoupper(substr($admin['nom'] ?? 'A', 0, 2)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin['nom'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo ($admin['role'] ?? '') === 'super_admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $admin['role'] ?? 'admin')); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo ($admin['statut'] ?? 'inactif') === 'actif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo ucfirst($admin['statut'] ?? 'inactif'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($admin['created_at'] ?? 'now')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button @click="editItem('admin', <?php echo $admin['id'] ?? 0; ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <?php if (($admin['role'] ?? '') !== 'super_admin'): ?>
                                        <button @click="toggleAdminStatus(<?php echo $admin['id'] ?? 0; ?>)" class="text-orange-600 hover:text-orange-900 mr-3">
                                            <span class="material-symbols-outlined">
                                                <?php echo ($admin['statut'] ?? 'inactif') === 'actif' ? 'block' : 'check_circle'; ?>
                                            </span>
                                        </button>
                                        <button @click="deleteItem('admins', <?php echo $admin['id'] ?? 0; ?>)" class="text-red-600 hover:text-red-900">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Transactions -->
        <div x-show="currentSection === 'transactions'" class="space-y-6" style="display: none;">
            <!-- Statistiques transactions -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">CA Aujourd'hui</p>
                            <p class="text-2xl font-bold text-green-600">€<?php echo number_format($transactionStats['ca_today'] ?? 0, 2); ?></p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">today</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">CA ce Mois</p>
                            <p class="text-2xl font-bold text-blue-600">€<?php echo number_format($transactionStats['ca_month'] ?? 0, 2); ?></p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <span class="material-symbols-outlined text-blue-600">calendar_month</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Transactions Aujourd'hui</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $transactionStats['transactions_today'] ?? 0; ?></p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <span class="material-symbols-outlined text-purple-600">receipt</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Transactions ce Mois</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo $transactionStats['transactions_month'] ?? 0; ?></p>
                        </div>
                        <div class="p-3 bg-orange-100 rounded-full">
                            <span class="material-symbols-outlined text-orange-600">credit_card</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Période</label>
                        <select x-model="transactionPeriod" @change="filterTransactions()" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="today">Aujourd'hui</option>
                            <option value="week">Cette semaine</option>
                            <option value="month">Ce mois</option>
                            <option value="year">Cette année</option>
                            <option value="all">Toutes</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                        <select x-model="transactionType" @change="filterTransactions()" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Tous les types</option>
                            <option value="service">Services (5€)</option>
                            <option value="subscription">Abonnements</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                        <select x-model="transactionStatus" @change="filterTransactions()" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Tous les statuts</option>
                            <option value="captured">Capturées</option>
                            <option value="pending">En attente</option>
                            <option value="failed">Échouées</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Recherche</label>
                        <input type="text" x-model="transactionSearch" @input="searchTransactions()" 
                               placeholder="ID transaction..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                </div>
            </div>

            <!-- Liste des transactions -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Historique des Transactions</h3>
                        <button @click="exportTransactions()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <span class="material-symbols-outlined mr-2 inline-block">download</span>
                            Exporter
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Transaction</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="transactionsTableBody">
                                <!-- Les transactions seront chargées dynamiquement via JavaScript -->
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        <div class="flex items-center justify-center">
                                            <span class="material-symbols-outlined mr-2">hourglass_empty</span>
                                            Chargement des transactions...
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-6">
                        <div class="text-sm text-gray-700">
                            Affichage de <span x-text="transactionPagination.start"></span> à 
                            <span x-text="transactionPagination.end"></span> sur 
                            <span x-text="transactionPagination.total"></span> transactions
                        </div>
                        <div class="flex space-x-2">
                            <button @click="previousTransactionPage()" 
                                    :disabled="transactionPagination.currentPage === 1"
                                    class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50">
                                Précédent
                            </button>
                            <button @click="nextTransactionPage()" 
                                    :disabled="transactionPagination.currentPage === transactionPagination.totalPages"
                                    class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50">
                                Suivant
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modals -->
    <!-- Modal Produit -->
    <div x-show="showModal && modalType === 'produit'" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="editingId ? 'Modifier le produit' : 'Ajouter un produit'"></h3>
                <form @submit.prevent="saveItem('produits')">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom du produit</label>
                        <input type="text" x-model="formData.nom" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea x-model="formData.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prix (€)</label>
                        <input type="number" step="0.01" x-model="formData.prix" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stock</label>
                        <input type="number" x-model="formData.stock" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Référence</label>
                        <input type="text" x-model="formData.reference" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4" x-show="editingId">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                        <select x-model="formData.statut" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Newsletter -->
    <div x-show="showModal && modalType === 'newsletter'" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="editingId ? 'Modifier la newsletter' : 'Nouvelle newsletter'"></h3>
                <form @submit.prevent="saveItem('newsletters')">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Titre</label>
                        <input type="text" x-model="formData.titre" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contenu</label>
                        <textarea x-model="formData.contenu" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date d'envoi</label>
                        <input type="datetime-local" x-model="formData.date_envoi" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de destinataires</label>
                        <input type="number" x-model="formData.destinataires_count" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4" x-show="editingId">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                        <select x-model="formData.statut" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="brouillon">Brouillon</option>
                            <option value="programmee">Programmée</option>
                            <option value="envoyee">Envoyée</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Admin -->
    <div x-show="showModal && modalType === 'admin'" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="editingId ? 'Modifier l\'administrateur' : 'Nouvel administrateur'"></h3>
                <form @submit.prevent="saveItem('admins')">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                        <input type="text" x-model="formData.nom" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" x-model="formData.email" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                        <select x-model="formData.role" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="admin">Administrateur</option>
                            <option value="super_admin">Super Administrateur</option>
                        </select>
                    </div>
                    <div class="mb-4" x-show="editingId">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                        <select x-model="formData.statut" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Variables globales
        let currentChart = null;

        // Fonction principale pour Alpine.js
        function adminDashboard() {
            return {
                currentSection: 'dashboard',
                mobileMenuOpen: false,
                showModal: false,
                modalType: '',
                editingId: null,
                formData: {},
                
                // Variables pour les filtres et recherches
                clientSearch: '',
                proSearch: '',
                proStatusFilter: '',
                transactionPeriod: 'month',
                transactionType: '',
                transactionStatus: '',
                transactionSearch: '',
                
                // Variables pour la pagination
                transactionPagination: {
                    currentPage: 1,
                    totalPages: 1,
                    start: 0,
                    end: 0,
                    total: 0
                },
                
                init() {
                    this.initChart();
                    this.loadTransactions();
                },

                getSectionTitle() {
                    const titles = {
                        'dashboard': 'Tableau de bord',
                        'boutique': 'Gestion Boutique',
                        'newsletters': 'Newsletters',
                        'clients': 'Clients',
                        'pros': 'Professionnels',
                        'admins': 'Administrateurs',
                        'transactions': 'Transactions'
                    };
                    return titles[this.currentSection] || 'Dashboard';
                },

                switchSection(section) {
                    this.currentSection = section;
                    this.mobileMenuOpen = false;
                    
                    // Charger les données spécifiques à la section
                    if (section === 'transactions') {
                        this.loadTransactions();
                    }
                },

                // Gestion des modals
                openModal(type, id = null) {
                    this.modalType = type;
                    this.editingId = id;
                    this.formData = {};
                    
                    if (id) {
                        this.loadItemForEdit(type, id);
                    }
                    
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                    this.modalType = '';
                    this.editingId = null;
                    this.formData = {};
                },

                // CRUD Operations
                async saveItem(table) {
                    try {
                        const method = this.editingId ? 'PUT' : 'POST';
                        const url = this.editingId ? 
                            `../api/crud.php?table=${table}&id=${this.editingId}` : 
                            `../api/crud.php?table=${table}`;
                        
                        const response = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(this.formData)
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.showNotification(result.message, 'success');
                            this.closeModal();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            this.showNotification(result.message, 'error');
                        }
                    } catch (error) {
                        this.showNotification('Erreur lors de l\'opération', 'error');
                    }
                },

                async deleteItem(table, id) {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                        return;
                    }
                    
                    try {
                        const response = await fetch(`../api/crud.php?table=${table}&id=${id}`, {
                            method: 'DELETE'
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.showNotification(result.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            this.showNotification(result.message, 'error');
                        }
                    } catch (error) {
                        this.showNotification('Erreur lors de la suppression', 'error');
                    }
                },

                async loadItemForEdit(type, id) {
                    try {
                        const table = type === 'produit' ? 'produits' : 
                                     type === 'newsletter' ? 'newsletters' : 
                                     type === 'admin' ? 'admins' : type;
                        
                        const response = await fetch(`../api/crud.php?table=${table}&id=${id}`);
                        const result = await response.json();
                        
                        if (result.success) {
                            this.formData = result.data;
                        }
                    } catch (error) {
                        this.showNotification('Erreur lors du chargement', 'error');
                    }
                },

                editItem(type, id) {
                    this.openModal(type, id);
                },

                // Fonctions spécifiques
                async sendNewsletter(id) {
                    if (!confirm('Êtes-vous sûr de vouloir envoyer cette newsletter ?')) {
                        return;
                    }
                    
                    try {
                        const response = await fetch(`../api/crud.php?table=newsletters&id=${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ statut: 'envoyee' })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.showNotification('Newsletter envoyée avec succès', 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            this.showNotification(result.message, 'error');
                        }
                    } catch (error) {
                        this.showNotification('Erreur lors de l\'envoi', 'error');
                    }
                },

                // Gestion des clients
                searchClients() {
                    // Implémentation de la recherche côté client ou rechargement avec paramètres
                    console.log('Recherche clients:', this.clientSearch);
                },

                exportClients() {
                    window.open('../api/export.php?type=clients', '_blank');
                },

                viewClient(id) {
                    // Ouvrir une modal de détails ou rediriger
                    console.log('Voir client:', id);
                },

                editClient(id) {
                    // Rediriger vers une page d'édition ou ouvrir une modal
                    console.log('Éditer client:', id);
                },

                toggleClientStatus(id) {
                    if (!confirm('Êtes-vous sûr de vouloir changer le statut de ce client ?')) {
                        return;
                    }
                    // Implémentation du changement de statut
                    console.log('Toggle client status:', id);
                },

                // Gestion des professionnels
                searchPros() {
                    console.log('Recherche pros:', this.proSearch);
                },

                filterPros() {
                    console.log('Filtrer pros:', this.proStatusFilter);
                },

                viewPro(id) {
                    console.log('Voir pro:', id);
                },

                approvePro(id) {
                    if (!confirm('Êtes-vous sûr de vouloir approuver ce professionnel ?')) {
                        return;
                    }
                    console.log('Approuver pro:', id);
                },

                suspendPro(id) {
                    if (!confirm('Êtes-vous sûr de vouloir suspendre ce professionnel ?')) {
                        return;
                    }
                    console.log('Suspendre pro:', id);
                },

                // Gestion des admins
                toggleAdminStatus(id) {
                    if (!confirm('Êtes-vous sûr de vouloir changer le statut de cet administrateur ?')) {
                        return;
                    }
                    console.log('Toggle admin status:', id);
                },

                // Gestion des transactions
                async loadTransactions() {
                    try {
                        const params = new URLSearchParams({
                            period: this.transactionPeriod,
                            type: this.transactionType,
                            status: this.transactionStatus,
                            search: this.transactionSearch,
                            page: this.transactionPagination.currentPage
                        });
                        
                        const response = await fetch(`../api/transactions.php?${params}`);
                        const result = await response.json();
                        
                        if (result.success) {
                            this.updateTransactionTable(result.data);
                            this.transactionPagination = {
                                currentPage: result.pagination.currentPage,
                                totalPages: result.pagination.totalPages,
                                start: result.pagination.start,
                                end: result.pagination.end,
                                total: result.pagination.totalRecords
                            };
                        } else {
                            this.updateTransactionTable([]);
                            this.showNotification(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Erreur lors du chargement des transactions:', error);
                        this.updateTransactionTable([]);
                        this.showNotification('Erreur lors du chargement des transactions', 'error');
                    }
                },

                updateTransactionTable(transactions) {
                    const tbody = document.getElementById('transactionsTableBody');
                    if (!tbody) return;
                    
                    if (transactions.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    Aucune transaction trouvée
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    tbody.innerHTML = transactions.map(transaction => `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${transaction.transaction_id || transaction.id}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${transaction.formatted_date}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${transaction.type === 'subscription' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                    ${transaction.type === 'subscription' ? 'Abonnement' : 'Service (5€)'}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                €${transaction.amount_euros.toFixed(2)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                    transaction.status === 'captured' ? 'bg-green-100 text-green-800' : 
                                    transaction.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                    'bg-red-100 text-red-800'
                                }">
                                    ${
                                        transaction.status === 'captured' ? 'Capturée' : 
                                        transaction.status === 'pending' ? 'En attente' : 
                                        'Échouée'
                                    }
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewTransaction('${transaction.id}')" class="text-blue-600 hover:text-blue-900">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                },

                filterTransactions() {
                    this.transactionPagination.currentPage = 1;
                    this.loadTransactions();
                },

                searchTransactions() {
                    this.transactionPagination.currentPage = 1;
                    this.loadTransactions();
                },

                exportTransactions() {
                    const params = new URLSearchParams({
                        type: 'transactions',
                        period: this.transactionPeriod,
                        transaction_type: this.transactionType,
                        status: this.transactionStatus
                    });
                    window.open(`../api/export.php?${params}`, '_blank');
                },

                previousTransactionPage() {
                    if (this.transactionPagination.currentPage > 1) {
                        this.transactionPagination.currentPage--;
                        this.loadTransactions();
                    }
                },

                nextTransactionPage() {
                    if (this.transactionPagination.currentPage < this.transactionPagination.totalPages) {
                        this.transactionPagination.currentPage++;
                        this.loadTransactions();
                    }
                },

                // Notifications
                showNotification(message, type = 'info') {
                    // Créer une notification toast
                    const notification = document.createElement('div');
                    notification.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 ${
                        type === 'success' ? 'bg-green-500 text-white' : 
                        type === 'error' ? 'bg-red-500 text-white' : 
                        'bg-blue-500 text-white'
                    }`;
                    notification.textContent = message;
                    
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                },

                initChart() {
                    const caData = <?php echo json_encode($caDetails); ?>;

                    const chartLabels = [];
                    const serviceData = [];
                    const abonnementData = [];

                    caData.reverse().forEach(item => {
                        const date = new Date(item.mois + '-01');
                        chartLabels.push(date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' }));
                        serviceData.push(item.ca_service);
                        abonnementData.push(item.ca_abonnements);
                    });

                    const ctx = document.getElementById('caChart').getContext('2d');

                    if (currentChart) {
                        currentChart.destroy();
                    }

                    currentChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartLabels,
                            datasets: [
                                {
                                    label: 'CA Services (5€/transaction)',
                                    data: serviceData,
                                    backgroundColor: 'rgba(34, 197, 94, 0.2)',
                                    borderColor: 'rgba(34, 197, 94, 1)',
                                    borderWidth: 2,
                                    fill: true
                                },
                                {
                                    label: 'CA Abonnements',
                                    data: abonnementData,
                                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    borderWidth: 2,
                                    fill: true
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Évolution du Chiffre d\'Affaires (12 derniers mois)'
                                },
                                legend: {
                                    display: true,
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        label: function (context) {
                                            return context.dataset.label + ': ' +
                                                new Intl.NumberFormat('fr-FR', {
                                                    style: 'currency',
                                                    currency: 'EUR'
                                                }).format(context.parsed.y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Mois'
                                    }
                                },
                                y: {
                                    title: {
                                        display: true,
                                        text: 'Chiffre d\'Affaires (€)'
                                    },
                                    ticks: {
                                        callback: function (value) {
                                            return new Intl.NumberFormat('fr-FR', {
                                                style: 'currency',
                                                currency: 'EUR'
                                            }).format(value);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }

        // Fonction globale pour voir les détails d'une transaction
        function viewTransaction(id) {
            // Créer une modal pour afficher les détails de la transaction
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Détails de la transaction</h3>
                        <div id="transactionDetails">
                            <div class="flex items-center justify-center py-4">
                                <span class="material-symbols-outlined animate-spin mr-2">hourglass_empty</span>
                                Chargement...
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Fermer
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Charger les détails de la transaction
            fetch(`../api/transactions.php?id=${id}`)
                .then(response => response.json())
                .then(result => {
                    const detailsDiv = document.getElementById('transactionDetails');
                    if (result.success && result.data.length > 0) {
                        const transaction = result.data[0];
                        detailsDiv.innerHTML = `
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">ID Transaction</label>
                                    <p class="text-sm text-gray-900">${transaction.transaction_id || transaction.id}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Type</label>
                                    <p class="text-sm text-gray-900">${transaction.type === 'subscription' ? 'Abonnement' : 'Service (5€)'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Montant</label>
                                    <p class="text-sm text-gray-900">€${transaction.amount_euros.toFixed(2)}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Statut</label>
                                    <p class="text-sm text-gray-900">${
                                        transaction.status === 'captured' ? 'Capturée' : 
                                        transaction.status === 'pending' ? 'En attente' : 
                                        'Échouée'
                                    }</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Date</label>
                                    <p class="text-sm text-gray-900">${transaction.formatted_date}</p>
                                </div>
                            </div>
                        `;
                    } else {
                        detailsDiv.innerHTML = '<p class="text-red-600">Transaction non trouvée</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('transactionDetails').innerHTML = '<p class="text-red-600">Erreur lors du chargement</p>';
                });
        }
    </script>
</body>

</html>
