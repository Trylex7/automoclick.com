<?php
require_once __DIR__ . '/../db/dbconnect2.php';

function getDashboardStats()
{
    global $db;
    $stats = [];

    // Total clients (utilisateurs)
    $query = "SELECT COUNT(*) as total FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total professionnels (entreprises)
    $query = "SELECT COUNT(*) as total FROM entreprises";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['professionnels'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Nouveaux professionnels ce mois
    $query = "SELECT COUNT(*) as total FROM entreprises WHERE MONTH(date_creation_account) = MONTH(NOW()) AND YEAR(date_creation_account) = YEAR(NOW())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['nouveaux_professionnels_mois'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Nouveaux professionnels cette semaine
    $query = "SELECT COUNT(*) as total FROM entreprises WHERE date_creation_account >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['nouveaux_professionnels_semaine'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Nouveaux professionnels aujourd'hui
    $query = "SELECT COUNT(*) as total FROM entreprises WHERE DATE(date_creation_account) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['nouveaux_professionnels_jour'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Pourcentage d'évolution par rapport au mois précédent
    $query = "SELECT COUNT(*) as total FROM entreprises WHERE MONTH(date_creation_account) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(date_creation_account) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $professionnels_mois_precedent = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    if ($professionnels_mois_precedent > 0) {
        $evolution = (($stats['nouveaux_professionnels_mois'] - $professionnels_mois_precedent) / $professionnels_mois_precedent) * 100;
        $stats['evolution_professionnels'] = round($evolution, 1);
    } else {
        $stats['evolution_professionnels'] = $stats['nouveaux_professionnels_mois'] > 0 ? 100 : 0;
    }

    // Total rendez-vous
    $query = "SELECT COUNT(*) as total FROM rdvs";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['rendez_vous'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Rendez-vous aujourd'hui
    $query = "SELECT COUNT(*) as total FROM rdvs WHERE date = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['rdv_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // CA Total - 5€ par transaction (hors abonnements) + montant total des abonnements
    $query = "SELECT 
                COUNT(CASE WHEN type != 'subscription' THEN 1 END) as transactions_service,
                SUM(CASE WHEN type = 'subscription' THEN amount_cents ELSE 0 END) as abonnements_total
              FROM transactions 
              WHERE status = 'captured'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $transactions_service = $result['transactions_service'] ?? 0;
    $abonnements_total_cents = $result['abonnements_total'] ?? 0;

    // Calcul du CA : (nombre de transactions service × 5€) + montant total abonnements
    $ca_service_cents = $transactions_service * 500; // 5€ = 500 centimes
    $ca_total_cents = $ca_service_cents + $abonnements_total_cents;

    // Conversion en euros
    $stats['ca_total'] = $ca_total_cents / 100;
    $stats['transactions_count'] = $transactions_service;
    $stats['abonnements_ca'] = $abonnements_total_cents / 100;

    return $stats;
}

function getProfessionnelsStats()
{
    global $db;

    $stats = [];
    $query = "SELECT 
                COUNT(CASE WHEN statut = 'actif' THEN 1 END) as actifs,
                COUNT(CASE WHEN statut = 'en_attente' THEN 1 END) as en_attente,
                COUNT(CASE WHEN statut = 'inactif' THEN 1 END) as inactifs
              FROM entreprises";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['actifs'] = $result['actifs'] ?? 0;
    $stats['en_attente'] = $result['en_attente'] ?? 0;
    $stats['inactifs'] = $result['inactifs'] ?? 0;

    // Évolution sur les 6 derniers mois
    $query = "SELECT 
                DATE_FORMAT(date_creation_account, '%Y-%m') as mois,
                COUNT(*) as total
              FROM entreprises 
              WHERE date_creation_account >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(date_creation_account, '%Y-%m')
              ORDER BY mois ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['evolution_6_mois'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 5 des spécialités
    $query = "SELECT spe, COUNT(*) as total 
              FROM entreprises 
              WHERE spe IS NOT NULL AND spe != ''
              GROUP BY spe 
              ORDER BY total DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['top_specialites'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Professionnels par région (si vous avez cette info)
    $query = "SELECT commune, COUNT(*) as total 
              FROM entreprises 
              WHERE commune IS NOT NULL AND commune != ''
              GROUP BY commune 
              ORDER BY total DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['par_ville'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $stats;
}

function getTransactionStats()
{
    global $db;

    $stats = [];

    // Transactions du jour
    $query = "SELECT COUNT(*) as total FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'captured'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['transactions_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // CA du jour
    $query = "SELECT 
                COUNT(CASE WHEN type != 'subscription' THEN 1 END) as transactions_service,
                SUM(CASE WHEN type = 'subscription' THEN amount_cents ELSE 0 END) as abonnements_cents
              FROM transactions 
              WHERE DATE(created_at) = CURDATE() AND status = 'captured'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $ca_service_today = ($result['transactions_service'] * 500) / 100;
    $ca_abonnements_today = ($result['abonnements_cents'] ?? 0) / 100;
    $stats['ca_today'] = $ca_service_today + $ca_abonnements_today;

    // Transactions du mois
    $query = "SELECT COUNT(*) as total FROM transactions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status = 'captured'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['transactions_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // CA du mois
    $query = "SELECT 
                COUNT(CASE WHEN type != 'subscription' THEN 1 END) as transactions_service,
                SUM(CASE WHEN type = 'subscription' THEN amount_cents ELSE 0 END) as abonnements_cents
              FROM transactions 
              WHERE MONTH(created_at) = MONTH(NOW()) 
                AND YEAR(created_at) = YEAR(NOW()) 
                AND status = 'captured'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $ca_service_month = ($result['transactions_service'] * 500) / 100;
    $ca_abonnements_month = ($result['abonnements_cents'] ?? 0) / 100;
    $stats['ca_month'] = $ca_service_month + $ca_abonnements_month;

    return $stats;
}

function getCADetails()
{
    global $db;

    $details = [];

    // CA par mois (12 derniers mois)
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as mois,
                COUNT(CASE WHEN type != 'subscription' THEN 1 END) as transactions_service,
                SUM(CASE WHEN type = 'subscription' THEN amount_cents ELSE 0 END) as abonnements_cents
              FROM transactions 
              WHERE status = 'captured' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY mois DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $ca_service = ($row['transactions_service'] * 500) / 100; // 5€ par transaction
        $ca_abonnements = $row['abonnements_cents'] / 100;
        $ca_total = $ca_service + $ca_abonnements;

        $details[] = [
            'mois' => $row['mois'],
            'ca_service' => $ca_service,
            'ca_abonnements' => $ca_abonnements,
            'ca_total' => $ca_total,
            'transactions_count' => $row['transactions_service']
        ];
    }

    return $details;
}

function getClients($search = '', $limit = 50)
{
    global $db;

    $query = "SELECT id_client, numero_client, nom, prenom, email, telephone, user_c FROM login_user";
    if (!empty($search)) {
        $query .= " WHERE nom LIKE :search OR prenom LIKE :search OR email LIKE :search";
    }
    $query .= " ORDER BY user_c DESC LIMIT :limit";

    $stmt = $db->prepare($query);
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getPro($search = '', $limit = 50)
{
    global $db;

    $query = "SELECT id_pro, denomination, forme_juridique, email, phone_number,pays, numero_pro, siret, siren, spe, date_creation_account FROM entreprises";
    if (!empty($search)) {
        $query .= " WHERE denomination LIKE :search OR siret LIKE :search OR email LIKE :search";
    }
    $query .= " ORDER BY date_creation_account DESC LIMIT :limit";

    $stmt = $db->prepare($query);
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProduits()
{
    global $db;

    $query = "SELECT * FROM produit ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNewsletters()
{
    global $db;

    $query = "SELECT * FROM newsletters ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAdmins()
{
    global $db;

    $query = "SELECT id, nom, email, role, statut, created_at FROM admins ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentActivity()
{
    global $db;

    $activities = [];

    // Nouveaux professionnels (entreprises)
    $query = "SELECT denomination, date_creation_account FROM entreprises WHERE date_creation_account >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY date_creation_account DESC LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $newPros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($newPros as $pro) {
        $activities[] = [
            'type' => 'professionnel',
            'message' => "Nouveau professionnel inscrit: {$pro['denomination']}",
            'time' => time_elapsed_string($pro['date_creation_account'])
        ];
    }

    // Rendez-vous confirmés
    $query = "SELECT * FROM rdvs WHERE etat = 'confirme' AND update_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY update_at DESC LIMIT 2";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rdvs as $rdv) {
        $activities[] = [
            'type' => 'rdv',
            'message' => "Rendez-vous confirmé",
            'time' => time_elapsed_string($rdv['update_at'])
        ];
    }

    // Nouvelles transactions
    $query = "SELECT amount_cents, type, created_at FROM transactions WHERE status = 'captured' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY created_at DESC LIMIT 2";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transactions as $transaction) {
        $montant = $transaction['type'] === 'subscription' ?
            '€' . number_format($transaction['amount_cents'] / 100, 2) :
            '€5.00';

        $activities[] = [
            'type' => 'transaction',
            'message' => "Paiement reçu: {$montant}",
            'time' => time_elapsed_string($transaction['created_at'])
        ];
    }

    return $activities;
}

function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'année',
        'm' => 'mois',
        'w' => 'semaine',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full)
        $string = array_slice($string, 0, 1);
    return $string ? 'Il y a ' . implode(', ', $string) : 'À l\'instant';
}

// Fonctions CRUD pour les opérations dynamiques
function createProduit($data)
{
    global $db;

    $query = "INSERT INTO produits (nom, description, prix, stock, reference) VALUES (:nom, :description, :prix, :stock, :reference)";
    $stmt = $db->prepare($query);

    return $stmt->execute([
        ':nom' => $data['nom'],
        ':description' => $data['description'],
        ':prix' => $data['prix'],
        ':stock' => $data['stock'],
        ':reference' => $data['reference']
    ]);
}

function updateProduit($id, $data)
{
    global $db;

    $query = "UPDATE produit SET nom = :nom, description = :description, prix = :prix, stock = :stock, statut = :statut WHERE id = :id";
    $stmt = $db->prepare($query);

    return $stmt->execute([
        ':nom' => $data['nom'],
        ':description' => $data['description'],
        ':prix' => $data['prix'],
        ':stock' => $data['stock'],
        ':statut' => $data['statut'],
        ':id' => $id
    ]);
}

function deleteProduit($id)
{
    global $db;

    $query = "DELETE FROM produit WHERE id = :id";
    $stmt = $db->prepare($query);

    return $stmt->execute([':id' => $id]);
}

function createAdmin($data)
{
    global $db;
    function genererNumeroClient()
    {
        $prefix = 'AM';
        $date = date('Ymd');
        $unique = strtoupper(bin2hex(random_bytes(3)));
        return $prefix . '-' . $date . '-' . $unique;
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
    $numero_admin = genererNumeroClient();
    $password_g = genererMdpSolide();

    $query = "INSERT INTO admins (nom, email, password, role, numero_admin) VALUES (:nom, :email, :password, :role, :numero_admin)";
    $stmt = $db->prepare($query);

    $sucess = $stmt->execute([
        ':nom' => $data['nom'],
        ':email' => $data['email'],
        ':password' => password_hash($password_g, PASSWORD_ARGON2ID),
        ':role' => $data['role'],
        ':numero_admin' => $numero_admin
    ]);
if ($sucess){
    $to = $data['email'];
    $subject = 'Bienvenue sur Automoclick';
    $message = '
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
                <p>Nous sommes ravis de vous accueillir dans la communauté Automoclick. Votre compte admin a été créé avec succès.</p>
                <p>Voici vos identifiants de connexion :</p>
            </div>
            
            <div class="credentials">
                <div class="credential-item">
                    <div class="credential-label">Identifiant</div>
                    <div class="credential-value">' . $data['email'] . '</div>
                </div>
                <div class="credential-item">
                    <div class="credential-label">Mot de passe temporaire</div>
                    <div class="credential-value">' . $password_g . '</div>
                </div>
            </div>
            
            <a href="https://automoclick.com/admin" class="cta-btn">
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

    $headers = 'From: Automoclick <no-reply@automoclick.com>' . "\r\n" .
        'Reply-To: no-reply@automoclick.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion() . "\r\n" .
        'Content-Type: text/html; charset=UTF-8' . "\r\n" .
        'Content-Transfer-Encoding: 8bit';
    if (!mail($to, $subject, $message, $headers)) {
        echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
    }
    return ([
          ':nom' => $data['nom'],
        ':email' => $data['email'],
        ':password' => password_hash($password_g, PASSWORD_ARGON2ID),
        ':role' => $data['role'],
        ':numero_admin' => $numero_admin
    ]);
}

}

function updateAdmin($id, $data)
{
    global $db;

    $query = "UPDATE admins SET nom = :nom, email = :email, role = :role, statut = :statut WHERE id = :id";
    $stmt = $db->prepare($query);

    return $stmt->execute([
        ':nom' => $data['nom'],
        ':email' => $data['email'],
        ':role' => $data['role'],
        ':statut' => $data['statut'],
        ':id' => $id
    ]);
}

function deleteAdmin($id)
{
    global $db;

    $query = "DELETE FROM admins WHERE id = :id";
    $stmt = $db->prepare($query);

    return $stmt->execute([':id' => $id]);
}

function createNewsletter($data)
{
    global $db;

    $query = "INSERT INTO newsletters (titre, contenu, date_envoi, destinataires_count) VALUES (:titre, :contenu, :date_envoi, :destinataires_count)";
    $stmt = $db->prepare($query);

    return $stmt->execute([
        ':titre' => $data['titre'],
        ':contenu' => $data['contenu'],
        ':date_envoi' => $data['date_envoi'],
        ':destinataires_count' => $data['destinataires_count']
    ]);
}


function updateNewsletter($id, $data)
{
    global $db;

    $query = "UPDATE newsletters SET titre = :titre, contenu = :contenu, statut = :statut, date_envoi = :date_envoi WHERE id = :id";
    $stmt = $db->prepare($query);

    return $stmt->execute([
        ':titre' => $data['titre'],
        ':contenu' => $data['contenu'],
        ':statut' => $data['statut'],
        ':date_envoi' => $data['date_envoi'],
        ':id' => $id
    ]);
}

function deleteNewsletter($id)
{
    global $db;

    $query = "DELETE FROM newsletters WHERE id = :id";
    $stmt = $db->prepare($query);

    return $stmt->execute([':id' => $id]);
}

function updateClientStatus($id, $statut)
{
    global $db;

    $query = "UPDATE clients SET statut = :statut WHERE id = :id";
    $stmt = $db->prepare($query);

    return $stmt->execute([
        ':statut' => $statut,
        ':id' => $id
    ]);
}

function getBoutiqueStats()
{
    global $db;

    $stats = [];

    // Produits actifs
    $query = "SELECT COUNT(*) as total FROM produit WHERE statut = 'actif'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['produits_actifs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Stock faible (moins de 10)
    $query = "SELECT COUNT(*) as total FROM produit WHERE stock < 10 AND statut = 'actif'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['stock_faible'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Valeur du stock
    $query = "SELECT SUM(prix * stock) as total FROM produit WHERE statut = 'actif'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['valeur_stock'] = $result['total'] ?? 0;

    return $stats;
}

function getNewsletterStats()
{
    global $db;

    $stats = [];

    // Abonnés actifs (simulation - vous devrez adapter selon votre structure)
    $query = "SELECT COUNT(*) as total FROM login_user";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['abonnes_actifs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Taux d'ouverture moyen
    $query = "SELECT AVG(taux_ouverture) as moyenne FROM newsletters WHERE statut = 'envoyee'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['taux_ouverture'] = round($result['moyenne'] ?? 0, 1);

    // Campagnes envoyées
    $query = "SELECT COUNT(*) as total FROM newsletters WHERE statut = 'envoyee'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['campagnes_envoyees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Campagnes en attente
    $query = "SELECT COUNT(*) as total FROM newsletters WHERE statut IN ('brouillon', 'programmee')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['campagnes_attente'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    return $stats;
}
?>