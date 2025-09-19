<?php
require_once '../includes/WebhookLogger.php';

class SiteMonitor {
    private $webhook;
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->webhook = new WebhookLogger($database);
        $this->setupErrorHandlers();
        $this->trackPageView();
    }
    
    private function setupErrorHandlers() {
        // Gestionnaire d'erreurs personnalisé
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    public function handleError($severity, $message, $file, $line) {
        $errorInfo = [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
        
        $webhookSeverity = $severity >= E_ERROR ? 'CRITICAL' : 'HIGH';
        $this->webhook->logError("Erreur PHP: $message dans $file:$line", $webhookSeverity, $errorInfo);
        
        return false; // Laisser PHP gérer l'erreur normalement
    }
    
    public function handleException($exception) {
        $errorInfo = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        $this->webhook->logError("Exception non capturée: " . $exception->getMessage(), 'CRITICAL', $errorInfo);
    }
    
    public function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->webhook->logError("Erreur fatale: {$error['message']} dans {$error['file']}:{$error['line']}", 'CRITICAL', $error);
        }
    }
    
    private function trackPageView() {
        $page = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $userId = $_SESSION['id_client'] ?? $_SESSION['numero_pro'] ?? 'Anonyme';
        
        // Ne pas logger certaines pages (CSS, JS, images)
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg)$/i', $page)) {
            return;
        }
        
        $this->webhook->log('PAGE_VIEW', "Page visitée: $method $page", 'LOW', [
            'method' => $method,
            'page' => $page,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null
        ]);
    }
    
    // Méthodes publiques pour logging spécifique
    public function logLogin($userId, $email = '') {
        $this->webhook->logUserLogin($userId, $email);
    }
    
    public function logLogout($userId) {
        $this->webhook->logUserLogout($userId);
    }
    
    public function logRegister($email, $userId = '') {
        $this->webhook->logUserRegister($email, $userId);
    }
    
    public function logBooking($vehicleInfo, $userId, $dates) {
        $this->webhook->logVehicleBooking($vehicleInfo, $userId, $dates);
    }
    
    public function logPayment($amount, $userId, $success = true, $orderId = '', $reason = '') {
        if ($success) {
            $this->webhook->logPaymentSuccess($amount, $userId, $orderId);
        } else {
            $this->webhook->logPaymentFailed($amount, $userId, $reason);
        }
    }
    
    public function logSecurityEvent($message, $additionalData = []) {
        $this->webhook->logSecurityAlert($message, $additionalData);
    }
    
    public function logDatabaseError($query, $error) {
        $this->webhook->logDatabaseError($query, $error);
    }
    
    public function logFileUpload($fileName, $size, $type) {
        $this->webhook->log('FILE_UPLOAD', "Fichier uploadé: $fileName ($size bytes)", 'LOW', [
            'file_name' => $fileName,
            'file_size' => $size,
            'file_type' => $type
        ]);
    }
    
    public function logAdminAction($action, $adminId, $targetData = []) {
        $this->webhook->log('ADMIN_ACTION', "Action admin: $action par $adminId", 'MEDIUM', [
            'action' => $action,
            'admin_id' => $adminId,
            'target' => $targetData
        ]);
    }
}
?>