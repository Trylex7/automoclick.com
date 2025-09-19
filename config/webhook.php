<?php
class WebhookConfig {
    // URLs des webhooks (Discord, Slack, ou votre endpoint personnalisé)
    const WEBHOOK_URLS = [
        'discord' => 'https://discord.com/api/webhooks/1415887359257350197/AtF0JZ3Mc_QPksdHQob5BXHsXDi5C5by37Xx1AKXwXwXlvwmeIFgQSBpbZOBj9OxBSQw',
        // 'slack' => 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK',
        // 'custom' => 'https://votre-endpoint.com/webhook'
    ];
    
    // Types d'événements à logger
    const EVENT_TYPES = [
        'USER_LOGIN' => '🔐 Connexion utilisateur',
        'USER_LOGOUT' => '🚪 Déconnexion utilisateur',
        'USER_REGISTER' => '👤 Inscription utilisateur',
        'EMAIL_VERIFICATION' => '✉️ Vérification email',
        'PASSWORD_RESET' => '🔑 Réinitialisation mot de passe',
        'VEHICLE_BOOKING' => '🚗 Réservation véhicule',
        'PAYMENT_SUCCESS' => '💰 Paiement réussi',
        'PAYMENT_FAILED' => '❌ Paiement échoué',
        'ERROR_404' => '⚠️ Page non trouvée',
        'ERROR_500' => '💥 Erreur serveur',
        'SECURITY_ALERT' => '🚨 Alerte sécurité',
        'FILE_UPLOAD' => '📁 Upload fichier',
        'DATABASE_ERROR' => '🗄️ Erreur base de données',
        'API_CALL' => '📡 Appel API',
        'ADMIN_ACTION' => '👑 Action administrateur'
    ];
    
    // Niveaux de criticité
    const SEVERITY_LEVELS = [
        'LOW' => '🟢',
        'MEDIUM' => '🟡',
        'HIGH' => '🟠',
        'CRITICAL' => '🔴'
    ];
    
    // Activer/désactiver le webhook
    const ENABLED = true;
    
    // Logs en base de données
    const LOG_TO_DATABASE = true;
}
?>