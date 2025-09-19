<?php
session_start();
require_once '../db/dbconnect2.php';
require_once '../api/traker.php';
$user_id = $_SESSION['id_client'];
if (!isset($_SESSION['id_client'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /connexion?success_url={$currentUrl}");
    exit;
}
require_once '../includes/webhook.php';
// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_vehicule':
            $immatriculation = $_POST['immatriculation'] ?? '';
            $result = addVehiculeFromAPI($user_id, $immatriculation);
            echo json_encode($result);
            exit;

        case 'update_rdv':
            $rdv_id = $_POST['rdv_id'] ?? '';
            $new_date = $_POST['new_date'] ?? '';
            $new_heure = $_POST['new_heure'] ?? '';
            $result = updateRdv($rdv_id, $user_id, $new_date, $new_heure);
            echo json_encode($result);
            exit;

        case 'send_message':
            $pro_id = $_POST['pro_id'] ?? '';
            $message = $_POST['message'] ?? '';
            $result = sendMessage($user_id, $pro_id, $message);
            echo json_encode($result);
            exit;

        case 'update_profile':
            $data = $_POST;
            $result = updateClientProfile($user_id, $data);
            echo json_encode($result);
            exit;
    }
}

// Récupérer les informations du client
function getClientInfo($user_id)
{
    global $db;
    $query = "SELECT * FROM login_user WHERE numero_client = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function getClientTransactions($user_id)
{
    global $db;
    $query = "SELECT * FROM transactions WHERE numero_client = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getClientDevis($user_id)
{
    global $db;
    $query = "SELECT * FROM devis WHERE client_id = :user_id AND statut = 'devis'";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getClientFactures($user_id)
{
    global $db;
    $query = "SELECT * FROM devis WHERE client_id = :user_id AND statut = 'facture'";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Récupérer les rendez-vous du client
function getClientRdvs($user_id)
{
    global $db;
    $query = "    SELECT 
        r.*, 
        e.denomination, 
        e.email AS pro_email, 
        e.phone_number AS pro_phone, 
        e.description AS pro_description
    FROM rdvs r
    LEFT JOIN entreprises e ON r.numero_pro = e.numero_pro
    WHERE r.numero_client = :user_id AND etat = 'confirme'  
    ORDER BY r.date DESC, r.heure DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les véhicules du client
function getClientVehicules($user_id)
{
    global $db;
    $query = "SELECT * FROM vehicule_c WHERE numero_client = :user_id ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Ajouter un véhicule via l'API plaque d'immatriculation
function addVehiculeFromAPI($user_id, $immatriculation)
{
    global $db;

    // Appel à l'API
    $api_url = "https://api.apiplaqueimmatriculation.com/plaque?immatriculation="
        . urlencode($immatriculation)
        . "&token=142bb4769028&pays=FR";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'message' => "Erreur cURL : $error"
        ];
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return [
            'success' => false,
            'message' => "Erreur API (HTTP $http_code)",
            'response' => $response // réponse brute pour debug
        ];
    }

    $vehicule_data = json_decode($response, true);

    // Vérifie si on a bien un tableau et que "data" existe
    if (!$vehicule_data || !isset($vehicule_data['data']['marque'])) {
        return [
            'success' => false,
            'message' => 'Plaque d\'immatriculation non trouvée',
            'response' => $response // utile pour debug
        ];
    }
    $data = $vehicule_data['data'];

    // Vérifier si le véhicule existe déjà
    $check_query = "SELECT id FROM vehicule_c WHERE immatriculation = :immat AND numero_client = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([
        ':immat' => $immatriculation,
        ':user_id' => $user_id
    ]);

    if ($check_stmt->fetch()) {
        return [
            'success' => false,
            'message' => "Ce véhicule est déjà enregistré"
        ];
    }

    // Insérer le véhicule
    $insert_query = "INSERT INTO vehicule_c 
    (numero_client, immatriculation, vin, version, boite_vitesse, code_moteur, marque, modele, annee, carburant, puissance, created_at) 
    VALUES 
    (:user_id, :immat, :vin, :version, :boite_vitesse, :code_moteur,:marque, :modele, :annee, :carburant, :puissance, NOW())";

    $insert_stmt = $db->prepare($insert_query);
    $result = $insert_stmt->execute([
        ':user_id' => $user_id,
        ':immat' => $immatriculation,
        ':marque' => $data['marque'] ?? '',
        ':vin' => $data['vin'] ?? '',
        ':version' => $data['version'] ?? '',
        ':boite_vitesse' => $data['boite_vitesse'] ?? '',
        ':code_moteur' => $data['code_moteur'] ?? '',
        ':modele' => $data['modele'] ?? '',
        ':annee' => $data['date1erCir_fr'] ?? '',
        ':carburant' => $data['energieNGC'] ?? '',
        ':puissance' => $data['puisFiscReelCH'] ?? ''
    ]);

    if ($result) {
        return [
            'success' => true,
            'message' => "Véhicule ajouté avec succès",
            'data' => $data
        ];
    } else {
        return [
            'success' => false,
            'message' => "Erreur lors de l'ajout du véhicule"
        ];
    }
}

// Modifier un rendez-vous (48h avant seulement)
function updateRdv($rdv_id, $user_id, $new_date, $new_heure)
{
    global $db;

    // Vérifier que le RDV appartient au client
    $check_query = "SELECT date, heure FROM rdvs WHERE id = :rdv_id AND numero_client = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([':rdv_id' => $rdv_id, ':user_id' => $user_id]);
    $rdv = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rdv) {
        return ['success' => false, 'message' => 'Rendez-vous non trouvé'];
    }

    // Vérifier si on est à plus de 48h du RDV
    $rdv_datetime = new DateTime($rdv['date'] . ' ' . $rdv['heure']);
    $now = new DateTime();
    $diff = $now->diff($rdv_datetime);
    $hours_diff = ($diff->days * 24) + $diff->h;

    if ($hours_diff < 48) {
        return ['success' => false, 'message' => 'Vous ne pouvez modifier un rendez-vous que 48h avant'];
    }

    // Mettre à jour le RDV
    $update_query = "UPDATE rdvs SET date = :new_date, heure = :new_heure, etat = 'modifie' WHERE id = :rdv_id";
    $update_stmt = $db->prepare($update_query);
    $result = $update_stmt->execute([
        ':new_date' => $new_date,
        ':new_heure' => $new_heure,
        ':rdv_id' => $rdv_id
    ]);

    if ($result) {
        return ['success' => true, 'message' => 'Rendez-vous modifié avec succès'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la modification'];
    }
}

// Envoyer un message
function sendMessage($user_id, $pro_id, $message)
{
    global $db;

    $insert_query = "INSERT INTO messages (id_client, id_pro, message, sender_type, created_at) 
                     VALUES (:user_id, :pro_id, :message, 'client', NOW())";

    $insert_stmt = $db->prepare($insert_query);
    $result = $insert_stmt->execute([
        ':user_id' => $user_id,
        ':pro_id' => $pro_id,
        ':message' => $message
    ]);

    if ($result) {
        return ['success' => true, 'message' => 'Message envoyé avec succès'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de l\'envoi du message'];
    }
}

// Mettre à jour le profil client
function updateClientProfile($user_id, $data)
{
    global $db;

    $update_query = "UPDATE login_user SET nom = :nom, prenom = :prenom, email = :email, telephone = :telephone,
                     code_postal = :code_postal, commune = :commune, adresse = :adresse 
                     WHERE numero_client = :user_id";

    $update_stmt = $db->prepare($update_query);
    $result = $update_stmt->execute([
        ':nom' => $data['nom'],
        ':prenom' => $data['prenom'],
        ':email' => $data['email'],
        ':telephone' => $data['telephone'],
        ':code_postal' => $data['code_postal'],
        ':commune' => $data['commune'],
        ':adresse' => $data['adresse'],
        ':user_id' => $user_id
    ]);

    if ($result) {
        return ['success' => true, 'message' => 'Profil mis à jour avec succès'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
    }
}

$clientInfo = getClientInfo($user_id);
$rdvs = getClientRdvs($user_id);
$vehicules = getClientVehicules($user_id);
$transactions = getClientTransactions($user_id);
$devis = getClientDevis($user_id);
$factures = getClientFactures($user_id);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="180x180" href="asset/style/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="asset/style/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="asset/style/img/favicon-16x16.png">
    <title>Mon Espace Client - AutomoClick</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
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

        .nav-active {
            background-color: rgba(34, 197, 94, 0.1) !important;
            color: #22c55e !important;
            border-left: 3px solid #22c55e;
        }

        .card-hover {
            transition: transform 0.2s ease-in-out;
        }

        .card-hover:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const phoneInputField = document.getElementById("phone_number");

        const iti = window.intlTelInput(phoneInputField, {
            initialCountry: "fr",
            separateDialCode: false,
            formatOnDisplay: true,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js",
        });
    });
</script>
<script>
    function clientDashboard() {
        return {
            currentSection: 'dashboard',
            mobileMenuOpen: false,
            loading: false,
            init() {
                try {
                    const savedSection = localStorage.getItem('currentSection');
                    if (savedSection) {
                        this.currentSection = savedSection;
                    }
                } catch (e) {

                }
                this.updateAIMessage();
            },
            switchSection(section) {
                this.currentSection = section;
                this.mobileMenuOpen = false;
                this.updateAIMessage();
                try {
                    localStorage.setItem('currentSection', section);
                } catch (e) {

                }
            },

            showAddVehiculeModal: false,
            showModifyRdvModal: false,
            showNewMessageModal: false,

            showAIAssistant: false,
            hasNewSuggestion: true,
            currentAIMessage: 'Bonjour ! Je suis votre assistant AutomoClick. Comment puis-je vous aider aujourd\'hui ?',
            aiSuggestions: [
                {
                    id: 1,
                    icon: 'event',
                    text: 'Prendre un nouveau rendez-vous',
                    action: 'book_rdv'
                },
                {
                    id: 2,
                    icon: 'directions_car',
                    text: 'Ajouter un véhicule',
                    action: 'add_vehicle'
                },
                {
                    id: 3,
                    icon: 'build',
                    text: 'Trouver un garage proche',
                    action: 'find_garage'
                }
            ],

            // Objectifs
            objectives: [
                {
                    id: 1,
                    title: 'Ajouter votre premier véhicule',
                    completed: <?php echo count($vehicules) > 0 ? 'true' : 'false'; ?>,
                    reward: '+10 pts'
                },
                {
                    id: 2,
                    title: 'Prendre votre premier RDV',
                    completed: <?php echo count($rdvs) > 0 ? 'true' : 'false'; ?>,
                    reward: '+20 pts'
                },
                {
                    id: 3,
                    title: 'Compléter votre profil',
                    completed: <?php echo !empty($clientInfo['telephone']) && !empty($clientInfo['adresse']) && !empty($clientInfo['code_postal']) && !empty($clientInfo['commune']) ? 'true' : 'false'; ?>,
                    reward: '+15 pts'
                }
            ],

            // Données des formulaires
            newVehicule: {
                immatriculation: ''
            },

            modifyRdvData: {
                id: '',
                date: '',
                heure: ''
            },

            newMessage: {
                pro_id: '',
                message: ''
            },

            profileData: {
                nom: '<?php echo addslashes(htmlspecialchars($clientInfo['nom'] ?? '')); ?>',
                prenom: '<?php echo addslashes(htmlspecialchars($clientInfo['prenom'] ?? '')); ?>',
                email: '<?php echo addslashes(htmlspecialchars($clientInfo['email'] ?? '')); ?>',
                telephone: '<?php echo addslashes(htmlspecialchars($clientInfo['telephone'] ?? '')); ?>',
                adresse: '<?php echo addslashes(htmlspecialchars($clientInfo['adresse'] ?? '')); ?>',
                code_postal: '<?php echo addslashes(htmlspecialchars($clientInfo['code_postal'] ?? '')); ?>',
                commune: '<?php echo addslashes(htmlspecialchars($clientInfo['commune'] ?? '')); ?>'

            },

            // Notifications
            notification: {
                show: false,
                type: 'success',
                message: ''
            },


            updateAIMessage() {
                const messages = {
                    dashboard: 'Voici un aperçu de votre activité. Que souhaitez-vous faire ?',
                    rdvs: 'Gérez vos rendez-vous ici. N\'oubliez pas que vous pouvez modifier un RDV jusqu\'à 48h avant.',
                    vehicules: 'Ajoutez vos véhicules pour faciliter la prise de rendez-vous.',
                    messages: 'Communiquez directement avec vos professionnels de confiance.',
                    profil: 'Maintenez vos informations à jour pour un meilleur service.'
                };
                this.currentAIMessage = messages[this.currentSection] || messages.dashboard;
            },

            toggleAIAssistant() {
                this.showAIAssistant = !this.showAIAssistant;
                this.hasNewSuggestion = false;
            },

            executeAISuggestion(suggestion) {
                switch (suggestion.action) {
                    case 'book_rdv':
                        window.location.href = 'pro';
                        break;
                    case 'add_vehicle':
                        this.openAddVehicule();
                        break;
                    case 'find_garage':
                        window.location.href = 'pro';
                        break;
                }
                this.showAIAssistant = false;
            },

            // Gestion des véhicules
            openAddVehicule() {
                this.newVehicule.immatriculation = '';
                this.showAddVehiculeModal = true;
            },

            async addVehicule() {
                if (!this.newVehicule.immatriculation.trim()) {
                    this.showNotification('error', 'Veuillez saisir une plaque d\'immatriculation');
                    return;
                }

                this.loading = true;

                try {
                    const formData = new FormData();
                    formData.append('action', 'add_vehicule');
                    formData.append('immatriculation', this.newVehicule.immatriculation.toUpperCase());

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showNotification('success', result.message);
                        this.showAddVehiculeModal = false;
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        this.showNotification('error', result.message);
                    }
                } catch (error) {
                    this.showNotification('error', 'Erreur de connexion');
                } finally {
                    this.loading = false;
                }
            },

            // bookRdvForVehicule(immatriculation) {
            //     window.location.href = `pro?vehicule=${encodeURIComponent(immatriculation)}`;
            // },

            viewVehiculeDetails(vehiculeId) {
                // Afficher les détails du véhicule (à implémenter)
                this.showNotification('info', 'Fonctionnalité en cours de développement');
            },

            // Gestion des rendez-vous
            openModifyRdv(rdvId, currentDate, currentHeure) {
                this.modifyRdvData.id = rdvId;
                this.modifyRdvData.date = currentDate;
                this.modifyRdvData.heure = currentHeure;
                this.showModifyRdvModal = true;
            },

            async modifyRdv() {
                if (!this.modifyRdvData.date || !this.modifyRdvData.heure) {
                    this.showNotification('error', 'Veuillez remplir tous les champs');
                    return;
                }

                this.loading = true;

                try {
                    const formData = new FormData();
                    formData.append('action', 'update_rdv');
                    formData.append('rdv_id', this.modifyRdvData.id);
                    formData.append('new_date', this.modifyRdvData.date);
                    formData.append('new_heure', this.modifyRdvData.heure);

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showNotification('success', result.message);
                        this.showModifyRdvModal = false;
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        this.showNotification('error', result.message);
                    }
                } catch (error) {
                    this.showNotification('error', 'Erreur de connexion');
                } finally {
                    this.loading = false;
                }
            },

            viewRdvDetails(rdvId) {
                // Afficher les détails du RDV (à implémenter)
                this.showNotification('info', 'Fonctionnalité en cours de développement');
            },

            // Gestion des messages
            openNewMessage() {
                this.newMessage.pro_id = '';
                this.newMessage.message = '';
                this.showNewMessageModal = true;
            },

            replyToMessage(proId, proName) {
                this.newMessage.pro_id = proId;
                this.newMessage.message = '';
                this.showNewMessageModal = true;
            },

            async sendNewMessage() {
                if (!this.newMessage.pro_id || !this.newMessage.message.trim()) {
                    this.showNotification('error', 'Veuillez remplir tous les champs');
                    return;
                }

                this.loading = true;

                try {
                    const formData = new FormData();
                    formData.append('action', 'send_message');
                    formData.append('pro_id', this.newMessage.pro_id);
                    formData.append('message', this.newMessage.message);

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showNotification('success', result.message);
                        this.showNewMessageModal = false;
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        this.showNotification('error', result.message);
                    }
                } catch (error) {
                    this.showNotification('error', 'Erreur de connexion');
                } finally {
                    this.loading = false;
                }
            },

            // Gestion du profil
            async updateProfile() {
                if (!this.profileData.nom || !this.profileData.prenom || !this.profileData.email) {
                    this.showNotification('error', 'Veuillez remplir les champs obligatoires');
                    return;
                }

                this.loading = true;

                try {
                    const formData = new FormData();
                    formData.append('action', 'update_profile');
                    formData.append('nom', this.profileData.nom);
                    formData.append('prenom', this.profileData.prenom);
                    formData.append('email', this.profileData.email);
                    formData.append('telephone', this.profileData.telephone);
                    formData.append('adresse', this.profileData.adresse);
                    formData.append('code_postal', this.profileData.code_postal);
                    formData.append('commune', this.profileData.commune);
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showNotification('success', result.message);
                        // Mettre à jour les objectifs
                        this.checkObjectiveCompletion();
                    } else {
                        this.showNotification('error', result.message);
                    }
                } catch (error) {
                    this.showNotification('error', 'Erreur de connexion');
                } finally {
                    this.loading = false;
                }
            },

            // Système de notifications
            showNotification(type, message) {
                this.notification.type = type;
                this.notification.message = message;
                this.notification.show = true;

                setTimeout(() => {
                    this.notification.show = false;
                }, 5000);
            },

            // Système d'objectifs
            checkObjectiveCompletion() {
                // Vérifier si le profil est complet
                if (this.profileData.nom && this.profileData.prenom && this.profileData.telephone) {
                    const profileObjective = this.objectives.find(obj => obj.id === 3);
                    if (profileObjective && !profileObjective.completed) {
                        profileObjective.completed = true;
                        this.showNotification('success', 'Objectif accompli : Profil complété ! +15 points');
                    }
                }
            },

            // Fonctions utilitaires
            formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR');
            },

            formatTime(timeString) {
                return timeString.substring(0, 5);
            },

            getStatusColor(status) {
                const colors = {
                    'confirme': 'bg-green-100 text-green-800',
                    'en_attente': 'bg-yellow-100 text-yellow-800',
                    'modifie': 'bg-blue-100 text-blue-800',
                    'annule': 'bg-red-100 text-red-800'
                };
                return colors[status] || 'bg-gray-100 text-gray-800';
            },

            // Gestion responsive
            handleResize() {
                if (window.innerWidth >= 768) {
                    this.mobileMenuOpen = false;
                }
            }
        }
    }

    // Gestion des événements globaux
    document.addEventListener('DOMContentLoaded', function () {
        // Fermer les modales en cliquant à l'extérieur
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('fixed') && e.target.classList.contains('inset-0')) {
                // Fermer toutes les modales
                Alpine.store('dashboard', {
                    showAddVehiculeModal: false,
                    showModifyRdvModal: false,
                    showNewMessageModal: false
                });
            }
        });

        // Gestion du redimensionnement
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768) {
                document.querySelector('[x-data="clientDashboard()"]').__x.$data.mobileMenuOpen = false;
            }
        });

        // Auto-refresh des données toutes les 5 minutes
        setInterval(function () {
            // Vérifier s'il y a de nouveaux messages ou RDV
            fetch(window.location.href + '?check_updates=1')
                .then(response => response.json())
                .then(data => {
                    if (data.hasUpdates) {
                        document.querySelector('[x-data="clientDashboard()"]').__x.$data.hasNewSuggestion = true;
                    }
                })
                .catch(error => console.log('Erreur lors de la vérification des mises à jour'));
        }, 300000); // 5 minutes
    });

    // Fonctions d'aide pour les animations
    function slideIn(el) {
        el.style.transform = 'translateX(-100%)';
        el.style.transition = 'transform 0.3s ease-in-out';
        setTimeout(() => {
            el.style.transform = 'translateX(0)';
        }, 10);
    }

    function slideOut(el, callback) {
        el.style.transform = 'translateX(-100%)';
        setTimeout(callback, 300);
    }

    // Service Worker pour les notifications push (optionnel)
    if ('serviceWorker' in navigator && 'PushManager' in window) {
        navigator.serviceWorker.register('/sw.js')
            .then(function (registration) {
                console.log('Service Worker enregistré');
            })
            .catch(function (error) {
                console.log('Erreur Service Worker:', error);
            });
    }
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCxSYMnBapanxmvZ77sGnWdupt6yDsJc7g&libraries=places&callback=initAutocomplete"
    async defer></script>
<script>
function initAutocomplete() {
  const input = document.getElementById('adresse');
  if (!input) {
    console.error('Input adresse non trouvé');
    return;
  }

  const autocomplete = new google.maps.places.Autocomplete(input, {
    types: ['address'],
  });

  autocomplete.addListener('place_changed', () => {
    const place = autocomplete.getPlace();
    
    if (!place.address_components) {

      return;
    }

    let postalCode = '';
    let city = '';

    place.address_components.forEach(component => {
      const types = component.types;
      if (types.includes('postal_code')) {
        postalCode = component.long_name;
      }
      if (types.includes('locality') || types.includes('administrative_area_level_2')) {
        city = component.long_name;
      }
    });


    // Mise à jour des champs
    const codePostalInput = document.getElementById('code_postal');
    const communeInput = document.getElementById('commune');

    if (codePostalInput && communeInput) {
      codePostalInput.value = postalCode;
      communeInput.value = city;

      codePostalInput.dispatchEvent(new Event('input', { bubbles: true }));
      communeInput.dispatchEvent(new Event('input', { bubbles: true }));

    } else {
   
    }
  });

 
}


</script>

<body class="bg-gray-100 flex flex-col md:flex-row" x-data="clientDashboard()">
    <!-- Menu mobile hamburger -->
    <header class="md:hidden flex items-center justify-between bg-white shadow p-4">
        <h2 class="text-xl font-bold text-green-600">Mon Espace AutomoClick</h2>
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-3xl text-green-600 font-bold">&#9776;</button>
    </header>

    <!-- Sidebar -->
    <aside :class="{'hidden': !mobileMenuOpen}"
        class="w-full md:w-64 bg-white shadow-lg p-4 space-y-4 md:space-y-0 md:flex md:flex-col md:fixed md:top-0 md:left-0 md:h-full md:block z-50">
        <div class="p-4 border-b hidden md:block">
            <h2 class="text-xl font-bold text-green-600">Mon Espace AutomoClick</h2>
            <p class="text-sm text-gray-600">Bonjour <?php echo htmlspecialchars($clientInfo['prenom'] ?? ''); ?></p>
        </div>
        <nav class="flex flex-col md:p-4 space-y-2 flex-grow">
            <a href="#" @click="switchSection('dashboard')" :class="{'nav-active': currentSection === 'dashboard'}"
                class="flex items-center px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">dashboard</span>
                Tableau de bord
            </a>
            <a href="#" @click="switchSection('profil')" :class="{'nav-active': currentSection === 'profil'}"
                class="flex items-center px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">person</span>
                Mon Profil
            </a>
            <a href="#" @click="switchSection('rdvs')" :class="{'nav-active': currentSection === 'rdvs'}"
                class="flex items-center px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">event</span>
                Mes Rendez-vous
            </a>
            <a href="#" @click="switchSection('finances')" :class="{'nav-active': currentSection === 'finances'}"
                class="flex items-center px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">finance</span>
                Mes finances
            </a>
            <a href="#" @click="switchSection('vehicules')" :class="{'nav-active': currentSection === 'vehicules'}"
                class="flex items-center px-4 py-2 rounded hover:bg-green-100 hover:text-green-700 font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">directions_car</span>
                Mes Véhicules
            </a>

        </nav>
        <div class="border-t pt-4">
            <a href="logout.php"
                class="flex items-center  px-4 py-2 text-red-600 hover:bg-red-100 rounded font-semibold transition-colors">
                <span class="material-symbols-outlined mr-2 inline-block">logout</span>
                Déconnexion
            </a>
        </div>
    </aside>

    <!-- Contenu principal -->
    <main class="flex-grow p-4 pt-20 md:pt-6 md:p-6 transition-all duration-300 ease-in-out md:ml-64">
        <!-- Dashboard Section -->
        <div x-show="currentSection === 'dashboard'" class="space-y-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Tableau de bord</h1>
            <?php if (empty($vehicules)): ?>
                <div class="bg-white p-6 rounded-lg shadow">
                    <p class="text-gray-600">Vous n'avez aucun véhicule enregistré.</p>
                </div>
            <?php endif; ?>
            <?php if (empty($clientInfo['adresse'])): ?>
                <div class="bg-white p-6 rounded-lg shadow">
                    <p class="text-gray-600">Vous n'avez aucune adresse enregistrée.</p>
                </div>
            <?php endif; ?>
                        <?php if (empty($clientInfo['telephone'])): ?>
                <div class="bg-white p-6 rounded-lg shadow">
                    <p class="text-gray-600">Vous n'avez aucun numéro de telephone enregistré.</p>
                </div>
            <?php endif; ?>
            <!-- Statistiques rapides -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg shadow card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Rendez-vous</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo count($rdvs); ?></p>
                            <p class="text-sm text-gray-500">Total</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <span class="material-symbols-outlined text-blue-600">event</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Véhicules</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo count($vehicules); ?></p>
                            <p class="text-sm text-gray-500">Enregistrés</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <span class="material-symbols-outlined text-green-600">directions_car</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prochains rendez-vous -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Prochains Rendez-vous</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($rdvs)): ?>
                        <p class="text-gray-500 text-center py-4">Aucun rendez-vous programmé</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach (array_slice($rdvs, 0, 3) as $rdv): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <h4 class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($rdv['denomination'] ?? 'Professionnel'); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            <?php echo date('d/m/Y', strtotime($rdv['date'])); ?> à <?php echo $rdv['heure']; ?>
                                        </p>
                                    </div>
                                    <button @click="switchSection('rdvs')" class="text-blue-600 hover:text-blue-800">
                                        <span class="material-symbols-outlined">arrow_forward</span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section Rendez-vous -->
        <div x-show="currentSection === 'rdvs'" class="space-y-6" style="display: none;">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800">Mes Rendez-vous</h1>
                <a href="pro" class="flex items-center bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <span class="material-symbols-outlined mr-2 inline-block">add</span>
                    Nouveau RDV
                </a>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <?php if (empty($rdvs)): ?>
                        <div class="text-center py-8">
                            <span class="material-symbols-outlined text-gray-400 text-6xl">event</span>
                            <p class="text-gray-500 mt-4">Aucun rendez-vous programmé</p>
                            <a href="pro"
                                class="mt-4 inline-block bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                                Prendre un rendez-vous
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($rdvs as $rdv): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($rdv['denomination']); ?>
                                            </h4>
                                            <p class="text-gray-600 mt-1">
                                                <span class="material-symbols-outlined text-sm mr-1">event</span>
                                                <?php echo date('d/m/Y', strtotime($rdv['date'])); ?> à
                                                <?php echo $rdv['heure']; ?>
                                            </p>
                                            <p class="text-gray-600 mt-1">
                                                <span class="material-symbols-outlined text-sm mr-1">build</span>
                                                <?php echo htmlspecialchars($rdv['nom_prestation'] ?? 'Service non spécifié'); ?>
                                            </p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <?php
                                            $rdv_datetime = new DateTime($rdv['date'] . ' ' . $rdv['heure']);
                                            $now = new DateTime();
                                            $diff = $now->diff($rdv_datetime);
                                            $hours_diff = ($diff->days * 24) + $diff->h;

                                            if ($hours_diff >= 48 && $rdv['etat'] !== 'annule'): ?>
                                                <button
                                                    @click="openModifyRdv(<?php echo $rdv['id']; ?>, '<?php echo $rdv['date']; ?>', '<?php echo $rdv['heure']; ?>')"
                                                    class="text-blue-600 hover:text-blue-800">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </button>
                                            <?php endif; ?>
                                            <button @click="viewRdvDetails(<?php echo $rdv['id']; ?>)"
                                                class="text-gray-600 hover:text-gray-800">
                                                <span class="material-symbols-outlined">visibility</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section Véhicules -->
        <div x-show="currentSection === 'vehicules'" class="space-y-6" style="display: none;">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800">Mes Véhicules</h1>
                <button @click="openAddVehicule()"
                    class="flex items-center bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <span class="material-symbols-outlined mr-2 inline-block">add</span>
                    Ajouter un véhicule
                </button>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <?php if (empty($vehicules)): ?>
                        <div class="text-center py-8">
                            <span class="material-symbols-outlined text-gray-400 text-6xl">directions_car</span>
                            <p class="text-gray-500 mt-4">Aucun véhicule enregistré</p>
                            <button @click="openAddVehicule()"
                                class="mt-4 inline-block bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                                Ajouter mon premier véhicule
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($vehicules as $vehicule): ?>
                                <div class="border border-gray-200 rounded-lg p-4 card-hover">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($vehicule['immatriculation']); ?>
                                        </h4>
                                        <span class="material-symbols-outlined text-green-600">directions_car</span>
                                    </div>
                                    <div class="space-y-2 text-sm text-gray-600">
                                        <p><strong>Marque:</strong>
                                            <?php echo htmlspecialchars($vehicule['marque'] ?? 'Non spécifiée'); ?></p>
                                        <p><strong>Modèle:</strong>
                                            <?php echo htmlspecialchars($vehicule['modele'] ?? 'Non spécifié'); ?></p>
                                        <p><strong>Date de mise en circulation:</strong>
                                            <?php echo htmlspecialchars($vehicule['annee'] ?? 'Non spécifiée'); ?></p>
                                        <p><strong>Carburant:</strong>
                                            <?php echo htmlspecialchars($vehicule['carburant'] ?? 'Non spécifié'); ?></p>
                                        <?php if (!empty($vehicule['puissance'])): ?>
                                            <p><strong>Puissance:</strong> <?php echo htmlspecialchars($vehicule['puissance']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-4 flex space-x-2">
                                        <button @click="viewVehiculeDetails(<?php echo $vehicule['id']; ?>)"
                                            class="flex-1 bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700">
                                            Détails
                                        </button>
                                        <button
                                            @click="bookRdvForVehicule('<?php echo htmlspecialchars($vehicule['immatriculation']); ?>')"
                                            class="flex-1 bg-red-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700">
                                            Supprimer
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div x-show="currentSection === 'finances'" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Mes Finances</h3>
                </div>
                <div class="p-6 space-y-8">

                    <!-- Transactions -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Transactions récentes</h4>
                        <?php if (empty($transactions)): ?>
                            <p class="text-gray-500 text-center py-4">Aucune transaction récente</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach (array_slice($transactions, 0, 3) as $transaction): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <h5 class="font-semibold text-gray-800">
                                                <?php echo strtoupper(htmlspecialchars($transaction['type']) ?? 'Transaction'); ?>
                                            </h5>
                                            <p class="text-sm text-gray-600">
                                                <?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?> -
                                                Montant :
                                                <?= number_format($transaction['amount_cents'] / 100, 2, ',', ' ') ?> €
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Devis -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Devis récents</h4>
                        <?php if (empty($devis)): ?>
                            <p class="text-gray-500 text-center py-4">Aucun devis récent</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach (array_slice($devis, 0, 3) as $devisItem): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <h5 class="font-semibold text-gray-800">
                                                N°<?= htmlspecialchars($devisItem['numero'] ?? 'Devis'); ?>
                                            </h5>
                                            <p class="text-sm text-gray-600">
                                                Date : <?php echo date('d/m/Y', strtotime($devisItem['date_creation'])); ?> -
                                                Montant estimé :
                                                <?php echo number_format($devisItem['montant_total'], 2, ',', ' '); ?> €
                                            </p>
                                            <a href="dzt/<?= $devisItem['chemin_pdf'] ?>"
                                                class="text-blue-600 hover:text-blue-800">Voir le devis</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Factures -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Factures récentes</h4>
                        <?php if (empty($factures)): ?>
                            <p class="text-gray-500 text-center py-4">Aucune facture récente</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach (array_slice($factures, 0, 3) as $facture): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <h5 class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($facture['numero'] ?? 'Facture'); ?>
                                            </h5>
                                            <p class="text-sm text-gray-600">
                                                Date : <?php echo date('d/m/Y', strtotime($facture['date_creation'])); ?> -
                                                Montant : <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> €
                                            </p>
                                            <a href="fzt/<?= $facture['chemin_pdf'] ?>"
                                                class="text-blue-600 hover:text-blue-800">Voir la facture </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
        <!-- Section Profil -->
        <div x-show="currentSection === 'profil'" class="space-y-6" style="display: none;">
            <h1 class="text-3xl font-bold text-gray-800">Mon Profil</h1>

            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Informations personnelles</h3>
                </div>
                <div class="p-6">
                    <form @submit.prevent="updateProfile()" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                                <input type="text" x-model="profileData.nom"
                                    value="<?php echo htmlspecialchars($clientInfo['nom'] ?? ''); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                                <input type="text" x-model="profileData.prenom"
                                    value="<?php echo htmlspecialchars($clientInfo['prenom'] ?? ''); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" x-model="profileData.email"
                                value="<?php echo htmlspecialchars($clientInfo['email'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                            <input id="adresse" type="text" x-model="profileData.adresse"
                                value="<?php echo htmlspecialchars($clientInfo['adresse'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Code postal</label>
                                <input id="code_postal" type="text" x-model="profileData.code_postal"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Commune</label>
                                <input id="commune" type="text" x-model="profileData.commune"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                            <input type="tel" id="phone_number" x-model="profileData.telephone"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">
                                Mettre à jour
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Assistant IA et Objectifs -->
            <div class="fixed bottom-4 right-4 z-50">
                <!-- Bouton Assistant IA -->
                <div class="relative">
                    <button @click="toggleAIAssistant()"
                        class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                        <span class="material-symbols-outlined text-2xl">smart_toy</span>
                    </button>

                    <!-- Badge de notification -->
                    <div x-show="hasNewSuggestion"
                        class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        !
                    </div>
                </div>

                <!-- Panel Assistant IA -->
                <div x-show="showAIAssistant" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 transform translate-y-4"
                    x-transition:enter-end="opacity-100 transform translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 transform translate-y-0"
                    x-transition:leave-end="opacity-0 transform translate-y-4"
                    class="absolute bottom-16 right-0 w-80 bg-white rounded-lg shadow-xl border border-gray-200 mb-2"
                    style="display: none;">

                    <div
                        class="p-4 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">Assistant AutomoClick</h3>
                            <button @click="showAIAssistant = false" class="text-white hover:text-gray-200">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                    </div>

                    <div class="p-4 max-h-96 overflow-y-auto">
                        <!-- Messages de l'assistant -->
                        <div class="space-y-3">
                            <div class="bg-blue-50 p-3 rounded-lg">
                                <div class="flex items-start space-x-2">
                                    <span class="material-symbols-outlined text-blue-600 text-sm mt-1">smart_toy</span>
                                    <div>
                                        <p class="text-sm text-gray-800" x-text="currentAIMessage"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Suggestions d'actions -->
                            <div x-show="aiSuggestions.length > 0" class="space-y-2">
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Actions
                                    suggérées:
                                </p>
                                <template x-for="suggestion in aiSuggestions" :key="suggestion.id">
                                    <button @click="executeAISuggestion(suggestion)"
                                        class="w-full text-left p-2 bg-green-50 hover:bg-green-100 rounded border border-green-200 transition-colors">
                                        <div class="flex items-center space-x-2">
                                            <span class="material-symbols-outlined text-green-600 text-sm"
                                                x-text="suggestion.icon"></span>
                                            <span class="text-sm text-green-800" x-text="suggestion.text"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>

                            <!-- Objectifs -->
                            <div class="border-t pt-3">
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Mes
                                    objectifs:
                                </p>
                                <template x-for="objective in objectives" :key="objective.id">
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-4 h-4 rounded-full border-2"
                                                :class="objective.completed ? 'bg-green-500 border-green-500' : 'border-gray-300'">
                                                <span x-show="objective.completed"
                                                    class="material-symbols-outlined text-white text-xs">check</span>
                                            </div>
                                            <span class="text-sm"
                                                :class="objective.completed ? 'line-through text-gray-500' : 'text-gray-800'"
                                                x-text="objective.title"></span>
                                        </div>
                                        <span class="text-xs text-green-600" x-text="objective.reward"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </main>

    <!-- Modales -->

    <!-- Modal Ajouter Véhicule -->
    <div x-show="showAddVehiculeModal" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Ajouter un véhicule</h3>
                <button @click="showAddVehiculeModal = false" class="text-gray-500 hover:text-gray-700">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form @submit.prevent="addVehicule()">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Plaque d'immatriculation</label>
                    <input type="text" x-model="newVehicule.immatriculation" placeholder="Ex: AB-123-CD"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                    <p class="text-xs text-gray-500 mt-1">Les informations du véhicule seront récupérées automatiquement
                    </p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" @click="showAddVehiculeModal = false"
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" :disabled="loading"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
                        <span x-show="!loading">Ajouter</span>
                        <span x-show="loading">Ajout en cours...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Modifier RDV -->
    <div x-show="showModifyRdvModal" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Modifier le rendez-vous</h3>
                <button @click="showModifyRdvModal = false" class="text-gray-500 hover:text-gray-700">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form @submit.prevent="modifyRdv()">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nouvelle date</label>
                    <input type="date" x-model="modifyRdvData.date"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nouvelle heure</label>
                    <input type="time" x-model="modifyRdvData.heure"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" @click="showModifyRdvModal = false"
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" :disabled="loading"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                        <span x-show="!loading">Modifier</span>
                        <span x-show="loading">Modification...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- Notifications Toast -->
    <div x-show="notification.show" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2" class="fixed top-4 right-4 z-50 max-w-sm"
        style="display: none;">
        <div class="rounded-lg shadow-lg p-4"
            :class="notification.type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'">
            <div class="flex items-center">
                <span class="material-symbols-outlined mr-2"
                    x-text="notification.type === 'success' ? 'check_circle' : 'error'"></span>
                <p x-text="notification.message"></p>
            </div>
        </div>
    </div>


    <!-- Styles additionnels pour les animations -->
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease-out;
        }

        .animate-pulse-slow {
            animation: pulse 2s infinite;
        }

        /* Styles pour les barres de progression */
        .progress-bar {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            height: 4px;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* Styles pour les badges de notification */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        /* Styles pour les cartes interactives */
        .interactive-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .interactive-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        /* Styles pour les boutons d'action flottants */
        .floating-action {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        /* Responsive design amélioré */
        @media (max-width: 768px) {
            .floating-action {
                bottom: 80px;
                right: 15px;
            }

            .card-hover {
                transform: none !important;
            }

            .interactive-card:hover {
                transform: none;
            }
        }

        /* Styles pour les messages de l'assistant IA */
        .ai-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .ai-suggestion {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ai-suggestion:hover {
            background: rgba(34, 197, 94, 0.2);
            transform: translateX(4px);
        }

        /* Styles pour les objectifs */
        .objective-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 6px;
            transition: all 0.2s ease;
        }

        .objective-completed {
            background: rgba(34, 197, 94, 0.1);
            text-decoration: line-through;
            opacity: 0.7;
        }

        .objective-pending {
            background: rgba(156, 163, 175, 0.1);
        }

        /* Animation pour les nouveaux éléments */
        .new-item {
            animation: fadeInUp 0.5s ease-out;
        }

        /* Styles pour les indicateurs de chargement */
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #10b981;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</body>

</html>