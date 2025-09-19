<?php
require_once '../config/webhook.php';

class WebhookLogger {
    private $db;
    private $enabled;
    
    public function __construct($database = null) {
        $this->db = $database;
        $this->enabled = WebhookConfig::ENABLED;
        
        // Créer la table de logs si elle n'existe pas
        if ($this->db && WebhookConfig::LOG_TO_DATABASE) {
            $this->createLogsTable();
        }
    }
    
    private function createLogsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            user_id VARCHAR(100) NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NULL,
            url VARCHAR(500) NOT NULL,
            data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_severity (severity),
            INDEX idx_created_at (created_at)
        )";
        
        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Erreur création table webhook_logs: " . $e->getMessage());
        }
    }
    
    public function log($eventType, $message, $severity = 'LOW', $additionalData = []) {
        if (!$this->enabled) return;
        
        $logData = $this->prepareLogData($eventType, $message, $severity, $additionalData);
        
        // Log en base de données
        if ($this->db && WebhookConfig::LOG_TO_DATABASE) {
            $this->logToDatabase($logData);
        }
        
        // Envoyer aux webhooks externes
        $this->sendToWebhooks($logData);
    }
    
    private function prepareLogData($eventType, $message, $severity, $additionalData) {
        $userId = $_SESSION['id_client'] ?? $_SESSION['numero_pro'] ?? $_SESSION['admin_id'] ?? 'Anonyme';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ipAddress = $this->getUserIP();
        $currentUrl = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        
        return [
            'event_type' => $eventType,
            'severity' => $severity,
            'message' => $message,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'url' => $currentUrl,
            'data' => $additionalData,
            'timestamp' => date('Y-m-d H:i:s'),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown'
        ];
    }
    
    private function getUserIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                if (filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return trim($ip);
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    private function logToDatabase($logData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO webhook_logs (event_type, severity, message, user_id, ip_address, user_agent, url, data) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $logData['event_type'],
                $logData['severity'],
                $logData['message'],
                $logData['user_id'],
                $logData['ip_address'],
                $logData['user_agent'],
                $logData['url'],
                json_encode($logData['data'])
            ]);
        } catch (PDOException $e) {
            error_log("Erreur webhook database: " . $e->getMessage());
        }
    }
    
    private function sendToWebhooks($logData) {
        $payload = $this->formatPayload($logData);
        
        foreach (WebhookConfig::WEBHOOK_URLS as $type => $url) {
            if (!empty($url) && $url !== 'YOUR_WEBHOOK_URL') {
                $this->sendWebhookRequest($url, $payload, $type);
            }
        }
    }
    
    private function formatPayload($logData) {
        $severity = WebhookConfig::SEVERITY_LEVELS[$logData['severity']] ?? '⚪';
        $eventIcon = WebhookConfig::EVENT_TYPES[$logData['event_type']] ?? '📋 ' . $logData['event_type'];
        
        return [
            'username' => 'AutomoClick Monitor',
            'avatar_url' => 'https://cdn-icons-png.flaticon.com/512/3003/3003035.png',
            'embeds' => [[
                'title' => $severity . ' ' . $eventIcon,
                'description' => $logData['message'],
                'color' => $this->getSeverityColor($logData['severity']),
                'timestamp' => date('c'),
                'fields' => [
                    [
                        'name' => '👤 Utilisateur',
                        'value' => $logData['user_id'],
                        'inline' => true
                    ],
                    [
                        'name' => '🌐 IP',
                        'value' => $logData['ip_address'],
                        'inline' => true
                    ],
                    [
                        'name' => '📍 URL',
                        'value' => $logData['url'],
                        'inline' => false
                    ],
                    [
                        'name' => '🕐 Timestamp',
                        'value' => $logData['timestamp'],
                        'inline' => true
                    ],
                    [
                        'name' => '🖥️ Serveur',
                        'value' => $logData['server_name'],
                        'inline' => true
                    ]
                ],
                'footer' => [
                    'text' => 'AutomoClick Monitoring System',
                    'icon_url' => 'https://cdn-icons-png.flaticon.com/512/3003/3003035.png'
                ]
            ]]
        ];
    }
    
    private function getSeverityColor($severity) {
        $colors = [
            'LOW' => 65280,      // Vert
            'MEDIUM' => 16776960, // Jaune
            'HIGH' => 16753920,   // Orange
            'CRITICAL' => 16711680 // Rouge
        ];
        return $colors[$severity] ?? 8421504; // Gris par défaut
    }
    
    private function sendWebhookRequest($url, $payload, $type) {
        // Adapter le payload selon le type de webhook
        if ($type === 'slack') {
            $payload = $this->formatForSlack($payload);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 && $httpCode !== 204) {
            error_log("Webhook failed for $type: HTTP $httpCode - Response: $response");
        }
    }
    
    private function formatForSlack($payload) {
        // Adapter le format pour Slack si nécessaire
        return $payload;
    }
    
    // Méthodes de logging rapides
    public function logUserLogin($userId, $email = '') {
        $this->log('USER_LOGIN', "Connexion réussie pour l'utilisateur: $userId ($email)", 'LOW', ['email' => $email]);
    }
    
    public function logUserLogout($userId) {
        $this->log('USER_LOGOUT', "Déconnexion de l'utilisateur: $userId", 'LOW');
    }
    
    public function logUserRegister($email, $userId = '') {
        $this->log('USER_REGISTER', "Nouvelle inscription: $email", 'MEDIUM', ['email' => $email, 'user_id' => $userId]);
    }
    
    public function logEmailVerification($email) {
        $this->log('EMAIL_VERIFICATION', "Email vérifié: $email", 'LOW', ['email' => $email]);
    }
    
    public function logVehicleBooking($vehicleInfo, $userId, $dates) {
        $this->log('VEHICLE_BOOKING', "Nouvelle réservation véhicule: {$vehicleInfo['marque']} {$vehicleInfo['model']}", 'MEDIUM', [
            'vehicle' => $vehicleInfo,
            'user_id' => $userId,
            'dates' => $dates
        ]);
    }
    
    public function logPaymentSuccess($amount, $userId, $orderId = '') {
        $this->log('PAYMENT_SUCCESS', "Paiement réussi: {$amount}€ (User: $userId)", 'MEDIUM', [
            'amount' => $amount,
            'user_id' => $userId,
            'order_id' => $orderId
        ]);
    }
    
    public function logPaymentFailed($amount, $userId, $reason = '') {
        $this->log('PAYMENT_FAILED', "Paiement échoué: {$amount}€ (User: $userId) - Raison: $reason", 'HIGH', [
            'amount' => $amount,
            'user_id' => $userId,
            'reason' => $reason
        ]);
    }
    
    public function logError($errorMessage, $severity = 'HIGH', $additionalData = []) {
        $this->log('ERROR_500', $errorMessage, $severity, $additionalData);
    }
    
    public function logSecurityAlert($message, $additionalData = []) {
        $this->log('SECURITY_ALERT', $message, 'CRITICAL', $additionalData);
    }
    
    public function logDatabaseError($query, $error) {
        $this->log('DATABASE_ERROR', "Erreur BDD: $error", 'HIGH', ['query' => $query, 'error' => $error]);
    }
}
?>