<?php
// includes/maintenance.php
class MaintenanceMode {
    private $config;
    
    public function __construct() {
        $this->config = include  '../config/maintenance.php';
    }
    
    public function check() {
        // Si la maintenance n'est pas activée, continuer normalement
        if (!$this->config['enabled']) {
            return false;
        }
        
        // Vérifier si l'IP est autorisée
        if ($this->isIpAllowed()) {
            return false;
        }
        
        // Vérifier si la clé de bypass est présente
        if ($this->hasBypassKey()) {
            return false;
        }
        
        // Vérifier si c'est un admin connecté
        if ($this->isAdminLoggedIn()) {
            return false;
        }
        
        return true;
    }
    
    private function isIpAllowed() {
        $userIp = $this->getUserIp();
        return in_array($userIp, $this->config['allowed_ips']);
    }
    
    private function hasBypassKey() {
        return isset($_GET['bypass']) && $_GET['bypass'] === $this->config['bypass_key'];
    }
    
    private function isAdminLoggedIn() {
        session_start();
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    private function getUserIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    public function showMaintenancePage() {
        http_response_code(503);
        header('Retry-After: 3600'); // Réessayer dans 1 heure
        
        $estimatedTime = '';
        if (!empty($this->config['estimated_time'])) {
            $estimatedTime = date('d/m/Y à H:i', strtotime($this->config['estimated_time']));
        }
        
        include __DIR__ . '/../templates/maintenance.php';
        exit;
    }
    
    public function enable($message = null, $estimatedTime = null) {
        $config = $this->config;
        $config['enabled'] = true;
        
        if ($message) {
            $config['message'] = $message;
        }
        
        if ($estimatedTime) {
            $config['estimated_time'] = $estimatedTime;
        }
        
        $this->saveConfig($config);
    }
    
    public function disable() {
        $config = $this->config;
        $config['enabled'] = false;
        $this->saveConfig($config);
    }
    
    private function saveConfig($config) {
        $configContent = "<?php\nreturn " . var_export($config, true) . ";\n?>";
        file_put_contents('../config/maintenance.php', $configContent);
        $this->config = $config;
    }
}
?>
