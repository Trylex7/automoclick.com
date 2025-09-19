<?php
require 'db/dbconnect2.php';

// Récupérer tous les emails depuis la table
$stmt = $db->query("SELECT email FROM email"); // ou ta table contenant les emails
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subject = 'Votre nouvelle plateforme Auto est enfin là !';

// Ton message HTML complet
$message = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lancement officiel - AutomoClick</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            min-height: 100vh;
        }
        .email-container { 
            width: 100%; 
            max-width: 650px; 
            margin: 20px auto; 
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        .header {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
        }
        .title { 
            font-size: 32px;
            font-weight: 800;
            margin: 20px 0 10px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }
        .subtitle {
            font-size: 18px;
            opacity: 0.95;
            margin: 0;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }
        .content {
            padding: 50px 40px;
        }
        .thank-you {
            text-align: center;
            font-size: 18px;
            color: #374151;
            margin-bottom: 40px;
            line-height: 1.7;
        }
        .transaction-showcase {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-radius: 24px;
            padding: 40px 30px;
            margin: 35px 0;
            border: 3px solid #10b981;
            text-align: center;
            position: relative;
        }
        .transaction-label {
            color: #047857;
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .transaction-note {
            font-size: 16px;
            color: #047857;
            font-weight: 600;
            margin: 0;
            line-height: 1.6;
        }
        .confirmation-message {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-radius: 16px;
            padding: 25px;
            margin: 30px 0;
            border: 2px solid #3b82f6;
            text-align: center;
        }
        .confirmation-message h3 {
            color: #1d4ed8;
            margin: 0 0 15px 0;
            font-size: 18px;
            font-weight: 700;
        }
        .confirmation-message p {
            color: #1e40af;
            margin: 0;
            font-size: 16px;
            line-height: 1.6;
        }
        .support-card {
            background: linear-gradient(135deg, #fef7ff 0%, #f3e8ff 100%);
            border-radius: 16px;
            padding: 25px;
            margin: 30px 0;
            border: 2px solid #8b5cf6;
            text-align: center;
        }
        .support-card h3 {
            color: #7c3aed;
            margin: 0 0 15px 0;
            font-weight: 700;
        }
        .support-links a {
            display: inline-block;
            margin: 0 15px;
            color: #7c3aed;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .support-links a:hover {
            background: rgba(139, 92, 246, 0.1);
            transform: translateY(-1px);
        }
        .footer {
            background: #f9fafb;
            padding: 40px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer-brand {
            font-size: 20px;
            font-weight: 800;
            color: #1f2937;
            margin: 0 0 25px 0;
        }
        .social-links {
            margin: 25px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 15px;
            color: #059669;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .social-links a:hover {
            color: #047857;
        }
        .footer-text {
            font-size: 13px;
            color: #9ca3af;
            margin: 15px 0;
            line-height: 1.5;
        }
        @media only screen and (max-width: 600px) {
            .email-container { margin: 10px; border-radius: 16px; }
            .content { padding: 30px 20px; }
            .footer { padding: 25px 20px; }
            .title { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img class="logo" src="https://automoclick.com/img/logo-automoclick.png" alt="AutomoClick">
            <h1 class="title"> Automoclick est en ligne !</h1>
            <p class="subtitle">La plateforme qui simplifie la mise en relation entre clients et professionnels de l’automobile</p>
        </div>
        
        <div class="content">
            <div class="thank-you">
                <p><strong>Bonne nouvelle !</strong></p>
                <p>Après plusieurs mois de développement, nous avons le plaisir de vous annoncer le <strong>lancement officiel d’AutomoClick</strong>.</p>
                <p>Réservez un rendez-vous en quelques clics, trouvez un professionnel de confiance et gagnez du temps.</p>
            </div>
            
            <div class="transaction-showcase">
                <div class="transaction-label">Ce que vous pouvez faire dès aujourd’hui</div>
                <div class="transaction-note">
                    ✅ Rechercher des professionnels près de chez vous<br>
                    ✅ Réserver des prestations en ligne<br>
                    ✅ Gérer vos rendez-vous depuis votre espace client
                </div>
            </div>
            
            <div class="confirmation-message">
                <h3>Pourquoi Automoclick ?</h3>
                <p>Notre mission est simple : rendre vos démarches automobiles plus rapides, plus transparentes et 100% en ligne.</p>
            </div>
            
            <div class="support-card">
                <h3>Rejoignez l’aventure !</h3>
                <p style="color: #7c3aed; margin: 0 0 20px 0; font-size: 15px;">
                    Découvrez notre plateforme dès maintenant et profitez d’une expérience unique.
                </p>
                <div class="support-links">
                    <a href="https://automoclick.com">Découvrir</a>
                    <a href="https://automoclick.com/inscription">Créer mon compte</a>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p class="footer-brand">AutomoClick</p>
            <div class="social-links">
                <a href="https://automoclick.com">Site web</a>
                <a href="https://automoclick.com/contact">Contact</a>
                <a href="https://instagram.com/automoclick">Instagram</a>
            </div>
            <p class="footer-text">© 2025 Automoclick</p>
            <p class="footer-text">
                🎉 Merci de faire partie de cette aventure !<br>
                Ensemble, changeons la façon de gérer l’automobile.
            </p>
        </div>
    </div>
</body>
</html>'; // place ici ton HTML complet

$headers = 'From: Automoclick <no-reply@automoclick.com>' . "\r\n" .
           'Reply-To: no-reply@automoclick.com' . "\r\n" .
           'X-Mailer: PHP/' . phpversion() . "\r\n" .
           'Content-Type: text/html; charset=UTF-8' . "\r\n" .
           'Content-Transfer-Encoding: 8bit';

foreach ($emails as $user) {
    $to = $user['email'];

    if (!mail($to, $subject, $message, $headers)) {
        echo "Erreur lors de l'envoi à : {$to}<br>";
    } else {
        echo "Mail envoyé à : {$to}<br>";
    }
}
?>

