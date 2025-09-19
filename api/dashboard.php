<?php
// api/dashboard.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'stats':
            echo json_encode(getDashboardStats());
            break;

        case 'clients':
            $search = $_GET['search'] ?? '';
            echo json_encode(getClients($search));
            break;
        case 'pros':
            $search = $_GET['search'] ?? '';
            echo json_encode(getPro($search));
            break;

        case 'produits':
            echo json_encode(getProduits());
            break;

        case 'newsletters':
            echo json_encode(getNewsletters());
            break;

        case 'admins':
            echo json_encode(getAdmins());
            break;

        case 'activity':
            echo json_encode(getRecentActivity());
            break;

        case 'boutique-stats':
            echo json_encode(getBoutiqueStats());
            break;

        case 'transactions':
            global $db;
            $query = "SELECT * FROM transactions ORDER BY created_at DESC LIMIT 100";
            $stmt = $db->prepare($query);
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'newsletter-stats':
            echo json_encode(getNewsletterStats());
            break;

        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>