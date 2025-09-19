<?php
// api/crud.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? null;

try {
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($table === 'produit') {
                $result = createProduit($data);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Produit ajouté avec succès' : 'Erreur lors de l\'ajout du produit'
                ]);
            }
            elseif ($table === 'admins') {
                $result = createAdmin($data);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Administrateur ajouté avec succès' : 'Erreur lors de l\'ajout de l\'administrateur'
                ]);
            }
            elseif ($table === 'newsletters') {
                $result = createNewsletter($data);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Newsletter créée avec succès' : 'Erreur lors de la création de la newsletter'
                ]);
            }
            else {
                echo json_encode(['success' => false, 'message' => 'Table non supportée']);
            }
            break;
        
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }
            
            if ($table === 'produit') {
                $result = updateProduit($id, $data);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Produit mis à jour avec succès' : 'Erreur lors de la mise à jour du produit'
                ]);
            }
            elseif ($table === 'admins') {
                $result = updateAdmin($id, $data);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Administrateur mis à jour avec succès' : 'Erreur lors de la mise à jour de l\'administrateur'
                ]);
            }
            elseif ($table === 'newsletters') {
                $result = updateNewsletter($id, $data);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Newsletter mise à jour avec succès' : 'Erreur lors de la mise à jour de la newsletter'
                ]);
            }
            elseif ($table === 'clients') {
                $result = updateClientStatus($id, $data['statut']);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Statut client mis à jour avec succès' : 'Erreur lors de la mise à jour du statut'
                ]);
            }
            else {
                echo json_encode(['success' => false, 'message' => 'Table non supportée']);
            }
            break;
        
        case 'DELETE':
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }
            
            if ($table === 'produit') {
                $result = deleteProduit($id);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Produit supprimé avec succès' : 'Erreur lors de la suppression du produit'
                ]);
            }
            elseif ($table === 'admins') {
                $result = deleteAdmin($id);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Administrateur supprimé avec succès' : 'Erreur lors de la suppression de l\'administrateur'
                ]);
            }
            elseif ($table === 'newsletters') {
                $result = deleteNewsletter($id);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Newsletter supprimée avec succès' : 'Erreur lors de la suppression de la newsletter'
                ]);
            }
            elseif ($table === 'clients') {
                // Soft delete pour les clients
                $result = updateClientStatus($id, 'inactif');
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Client désactivé avec succès' : 'Erreur lors de la désactivation du client'
                ]);
            }
            else {
                echo json_encode(['success' => false, 'message' => 'Table non supportée']);
            }
            break;
        
        case 'GET':
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }
            
            global $db;
            $query = "SELECT * FROM {$table} WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Enregistrement non trouvé']);
            }
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Méthode HTTP non supportée']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
