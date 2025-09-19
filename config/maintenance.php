<?php
// config/maintenance.php
return [
    'enabled' => false, // true pour activer le mode maintenance
    'message' => 'Nous effectuons actuellement une maintenance programmée. Nous serons de retour très bientôt.',
    'title' => 'Maintenance en cours',
    'estimated_time' => '2024-12-20 14:00:00', // Heure estimée de fin
    'allowed_ips' => [
        '127.0.0.1',
        '::1',
        // Ajoutez vos IPs autorisées ici
    ],
    'bypass_key' => 'automoclick_bypass_2024', // Clé pour bypasser
    'contact_email' => 'support@automoclick.com'
];
?>
