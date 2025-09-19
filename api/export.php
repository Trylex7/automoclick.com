<?php
require_once '../includes/functions.php';

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

if (empty($type)) {
    http_response_code(400);
    echo json_encode(['error' => 'Type d\'export manquant']);
    exit;
}

try {
    global $db;
    
    switch ($type) {
        case 'clients':
            exportClients($format);
            break;
        case 'professionnels':
            exportProfessionnels($format);
            break;
        case 'transactions':
            exportTransactions($format);
            break;
        case 'newsletters':
            exportNewsletters($format);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Type d\'export non supporté']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'export: ' . $e->getMessage()]);
}

function exportClients($format) {
    global $db;
    
    $query = "SELECT 
                numero_client,
                nom,
                prenom,
                email,
                telephone,
                user_c as date_inscription
              FROM login_user 
              ORDER BY user_c DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="clients_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, [
            'Numéro Client',
            'Nom',
            'Prénom',
            'Email',
            'Téléphone',
            'Date Inscription'
        ], ';');
        
        // Données
        foreach ($clients as $client) {
            fputcsv($output, [
                $client['numero_client'],
                $client['nom'],
                $client['prenom'],
                $client['email'],
                $client['telephone'],
                date('d/m/Y', strtotime($client['date_inscription']))
            ], ';');
        }
        
        fclose($output);
    }
}

function exportProfessionnels($format) {
    global $db;
    
    $query = "SELECT 
                numero_pro,
                denomination,
                forme_juridique,
                email,
                phone_number,
                siret,
                siren,
                spe,
                ville,
                pays,
                statut,
                date_creation_account
              FROM entreprises 
              ORDER BY date_creation_account DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $professionnels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="professionnels_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, [
            'Numéro Pro',
            'Dénomination',
            'Forme Juridique',
            'Email',
            'Téléphone',
            'SIRET',
            'SIREN',
            'Spécialité',
            'Ville',
            'Pays',
            'Statut',
            'Date Inscription'
        ], ';');
        
        // Données
        foreach ($professionnels as $pro) {
            fputcsv($output, [
                $pro['numero_pro'],
                $pro['denomination'],
                $pro['forme_juridique'],
                $pro['email'],
                $pro['phone_number'],
                $pro['siret'],
                $pro['siren'],
                $pro['spe'],
                $pro['ville'],
                $pro['pays'],
                $pro['statut'],
                date('d/m/Y', strtotime($pro['date_creation_account']))
            ], ';');
        }
        
        fclose($output);
    }
}

function exportTransactions($format) {
    global $db;
    
    // Filtres optionnels
    $period = $_GET['period'] ?? '';
    $transaction_type = $_GET['transaction_type'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $whereConditions = ['1=1'];
    $params = [];
    
    // Filtrage par période
    switch ($period) {
        case 'today':
            $whereConditions[] = 'DATE(created_at) = CURDATE()';
            break;
        case 'week':
            $whereConditions[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
            break;
        case 'month':
            $whereConditions[] = 'MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())';
            break;
        case 'year':
            $whereConditions[] = 'YEAR(created_at) = YEAR(NOW())';
            break;
    }
    
    // Filtrage par type
    if (!empty($transaction_type)) {
        if ($transaction_type === 'service') {
            $whereConditions[] = "type != 'subscription'";
        } else {
            $whereConditions[] = 'type = :type';
            $params[':type'] = $transaction_type;
        }
    }
    
    // Filtrage par statut
    if (!empty($status)) {
        $whereConditions[] = 'status = :status';
        $params[':status'] = $status;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT 
                id,
                transaction_id,
                type,
                amount_cents,
                status,
                created_at,
                metadata
              FROM transactions 
              WHERE $whereClause
              ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, [
            'ID',
            'ID Transaction',
            'Type',
            'Montant (€)',
            'Statut',
            'Date Création'
        ], ';');
        
        // Données
        foreach ($transactions as $transaction) {
            fputcsv($output, [
                $transaction['id'],
                $transaction['transaction_id'] ?? $transaction['id'],
                $transaction['type'] === 'subscription' ? 'Abonnement' : 'Service',
                number_format($transaction['amount_cents'] / 100, 2, ',', ''),
                $transaction['status'],
                date('d/m/Y H:i', strtotime($transaction['created_at']))
            ], ';');
        }
        
        fclose($output);
    }
}

function exportNewsletters($format) {
    global $db;
    
    $query = "SELECT 
                id,
                titre,
                statut,
                date_envoi,
                destinataires_count,
                taux_ouverture,
                created_at
              FROM newsletters 
              ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $newsletters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="newsletters_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, [
            'ID',
            'Titre',
            'Statut',
            'Date Envoi',
            'Destinataires',
            'Taux Ouverture (%)',
            'Date Création'
        ], ';');
        
        // Données
        foreach ($newsletters as $newsletter) {
            fputcsv($output, [
                $newsletter['id'],
                $newsletter['titre'],
                $newsletter['statut'],
                $newsletter['date_envoi'] ? date('d/m/Y H:i', strtotime($newsletter['date_envoi'])) : '',
                $newsletter['destinataires_count'],
                $newsletter['taux_ouverture'] ?? 0,
                date('d/m/Y H:i', strtotime($newsletter['created_at']))
            ], ';');
        }
        
        fclose($output);
    }
}
?>
