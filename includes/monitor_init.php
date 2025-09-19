<?php
// À inclure en haut de chaque page
require_once '../includes/SiteMonitor.php';

// Initialiser le monitoring (avec votre connexion DB existante)
global $monitor;
$monitor = new SiteMonitor($db);

// Gestionnaire d'erreurs PDO personnalisé
function setupPDOErrorHandler($pdo, $monitor) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Wrapper pour les requêtes avec logging automatique des erreurs
    class MonitoredPDO {
        private $pdo;
        private $monitor;
        
        public function __construct($pdo, $monitor) {
            $this->pdo = $pdo;
            $this->monitor = $monitor;
        }
        
        public function prepare($query) {
            try {
                return $this->pdo->prepare($query);
            } catch (PDOException $e) {
                $this->monitor->logDatabaseError($query, $e->getMessage());
                throw $e;
            }
        }
        
        public function query($query) {
            try {
                return $this->pdo->query($query);
            } catch (PDOException $e) {
                $this->monitor->logDatabaseError($query, $e->getMessage());
                throw $e;
            }
        }
        
        // Déléguer tous les autres appels à PDO
        public function __call($method, $args) {
            return call_user_func_array([$this->pdo, $method], $args);
        }
    }
    
    return new MonitoredPDO($pdo, $monitor);
}

// Remplacer votre $db par la version monitorée
$db = setupPDOErrorHandler($db, $monitor);
?>