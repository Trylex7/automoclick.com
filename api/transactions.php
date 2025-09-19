<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$period = $_GET['period'] ?? 'month';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    global $db;
    
    // Si un ID spécifique est demandé
    if ($id) {
        $query = "SELECT 
                    id,
                    transaction_id,
                    type,
                    amount_cents,
                    status,
                    created_at
                  FROM transactions 
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            $formattedTransaction = [
                'id' => $transaction['id'],
                'transaction_id' => $transaction['transaction_id'] ?? $transaction['id'],
                'type' => $transaction['type'],
                'amount_cents' => $transaction['amount_cents'],
                'amount_euros' => $transaction['amount_cents'] / 100,
                'status' => $transaction['status'],
                'created_at' => $transaction['created_at'],
                'formatted_date' => date('d/m/Y H:i', strtotime($transaction['created_at']))
            ];
            
            echo json_encode([
                'success' => true,
                'data' => [$formattedTransaction]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ]);
        }
        exit;
    }
    
    // Construction de la requête de base pour la liste
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
    if (!empty($type)) {
        if ($type === 'service') {
            $whereConditions[] = "type != 'subscription'";
        } else {
            $whereConditions[] = 'type = :type';
            $params[':type'] = $type;
        }
    }
    
    // Filtrage par statut
    if (!empty($status)) {
        $whereConditions[] = 'status = :status';
        $params[':status'] = $status;
    }
    
    // Recherche
    if (!empty($search)) {
        $whereConditions[] = '(id LIKE :search OR transaction_id LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Compter le total
    $countQuery = "SELECT COUNT(*) as total FROM transactions WHERE $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupérer les transactions
    $query = "SELECT 
                id,
                transaction_id,
                type,
                amount_cents,
                status,
                created_at
              FROM transactions 
              WHERE $whereClause 
              ORDER BY created_at DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    // Bind des paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatage des données
    $formattedTransactions = array_map(function($transaction) {
        return [
            'id' => $transaction['id'],
            'transaction_id' => $transaction['transaction_id'] ?? $transaction['id'],
            'type' => $transaction['type'],
            'amount_cents' => $transaction['amount_cents'],
            'amount_euros' => $transaction['amount_cents'] / 100,
            'status' => $transaction['status'],
            'created_at' => $transaction['created_at'],
            'formatted_date' => date('d/m/Y H:i', strtotime($transaction['created_at']))
        ];
    }, $transactions);
    
    $totalPages = ceil($totalRecords / $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $formattedTransactions,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'limit' => $limit,
            'start' => $offset + 1,
            'end' => min($offset + $limit, $totalRecords)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des transactions: ' . $e->getMessage()
    ]);
}
?>
