<?php
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';
session_start();
require 'db/dbconnect2.php';

function dechiffrer($texte_chiffre, $cle)
{
    return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}

if (!isset($_GET['x'])) {
    exit("Token manquant.");
}

$token = $_GET['x'];
$json = dechiffrer($token, $cle_secrete);

// Vérifie si le déchiffrement a réussi
if (!$json) {
    exit("Échec du déchiffrement.");
}

// Decode le JSON
$donnees = json_decode($json, true);

// Vérifie si le JSON est valide
if (!is_array($donnees) || !isset($donnees['rdv_id'])) {
    exit("Données invalides.");
}

// Extraction des données
$rdv_id         = $donnees['rdv_id'] ?? null;
$numero_client   = $donnees['numero_client'] ?? null;
$numero_pro      = $donnees['numero_pro'] ?? null;
$date_rdv        = $donnees['date_rdv'] ?? null;
$heure_rdv       = $donnees['heure_rdv'] ?? null;
$transaction_id  = $donnees['transcation_id'] ?? null;
$amount          = $donnees['amount'] ?? null;


$rdv_id_unique = is_array($rdv_id) ? $rdv_id[0] : $rdv_id;
$sqlclient = $db->prepare("SELECT * FROM login_user WHERE numero_client = ?");
$sqlclient->execute([$numero_client]);
$data_client = $sqlclient->fetch(PDO::FETCH_ASSOC);

$sqlpro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
$sqlpro->execute([$numero_pro]);
$data_pro = $sqlpro->fetch(PDO::FETCH_ASSOC);

$sqlrdv = $db->prepare('SELECT * FROM rdvs WHERE rdv_id = ?');
$sqlrdv->execute([$rdv_id_unique]);
$data_rdv = $sqlrdv->fetch(PDO::FETCH_ASSOC);
$nom_complet = htmlspecialchars($data_client['nom'] . ' ' . $data_client['prenom']);
$date_heure = htmlspecialchars($data_rdv['date'] . ' à ' . $data_rdv['heure']);
$date_str = $data_rdv['date'] . ' ' . $data_rdv['heure'];
$date_obj = DateTime::createFromFormat('Y-d-m H:i', $date_str);
$date_display = $date_obj->format('d/m/Y \à H:i');
$prestation = htmlspecialchars($data_rdv['nom_prestation']);
$to = $data_pro['email'];
$denomination = htmlspecialchars($data_pro['denomination']);
$date_heure_esc = htmlspecialchars($date_heure);
$prestation_esc = htmlspecialchars($prestation);
$subject = 'Nouveau rendez-vous !';
$message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Nouveau rendez-vous - AutomoClick</title>
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
        .header::before {
            content: \'\';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url(\'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.1)"/></svg>\');
            z-index: 0;
        }
        .title { 
            font-size: 32px;
            font-weight: 800;
            margin: 0 0 10px 0;
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
            color: #374151;
        }
        .info-block {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-radius: 24px;
            padding: 30px 25px;
            margin: 35px 0;
            border: 3px solid #10b981;
            text-align: center;
            font-weight: 700;
            font-size: 20px;
            color: #047857;
            letter-spacing: 1.5px;
            user-select: all;
            font-family: monospace;
        }
        .details {
            font-size: 16px;
            margin-top: 20px;
            line-height: 1.6;
        }
        .footer {
            background: #f9fafb;
            padding: 40px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #9ca3af;
        }
        .footer-brand {
            font-weight: 800;
            color: #1f2937;
            font-size: 20px;
            margin-bottom: 25px;
        }
        .social-links a {
            margin: 0 15px;
            color: #059669;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .social-links a:hover {
            color: #047857;
        }
        @media only screen and (max-width: 600px) {
            .email-container { margin: 10px; border-radius: 16px; }
            .content { padding: 30px 20px; }
            .footer { padding: 25px 20px; }
            .title { font-size: 26px; }
            .info-block { font-size: 18px; padding: 25px 20px; }
        }
    </style>
</head>
<body>
    <div class="email-container" role="article" aria-roledescription="email" aria-label="Notification nouveau rendez-vous">
        <div class="header">
            <img src="https://automoclick.com/img/logo-automoclick.png" alt="AutomoClick" style="width:140px; position: relative; z-index:1;">
            <h1 class="title">Nouveau rendez-vous confirmé !</h1>
            <p class="subtitle">Vous avez un rendez-vous avec <strong>'. $denomination.'</strong></p>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            <p>Nous vous confirmons votre rendez-vous prévu le :</p>
            <div class="info-block">'. $date_heure_esc .'</div>
            <p class="details">Pour la prestation suivante : <strong>' . $prestation_esc .'</strong></p>
            <p>Nous vous remercions de votre confiance et restons à votre disposition pour toute question.</p>
            <p>Cordialement,<br>L\'équipe AutomoClick</p>
        </div>
        <div class="footer">
            <p class="footer-brand">AutomoClick</p>
            <div class="social-links">
                <a href="https://automoclick.com">Site web</a>
                <a href="https://automoclick.com/contact">Contact</a>
                <a href="https://instagram.com/automoclick">Instagram</a>
            </div>
            <p>© '. date('Y').' AutomoClick - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>


';
$headers = 'From: Automoclick <no-reply-automoclick@trusting-noyce.217-154-239-28.plesk.page>' . "\r\n" .
    'Reply-To: no-reply-automoclick@trusting-noyce.217-154-239-28.plesk.page' . "\r\n" .
    'X-Mailer: PHP/' . phpversion() . "\r\n" .
    'Content-Type: text/html; charset=UTF-8' . "\r\n" .
    'Content-Transfer-Encoding: 8bit';
if (!mail($to, $subject, $message, $headers)) {
    echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
}
$to2 = $data_client['email'];
$subject2 = 'Votre rendez-vous !';
$message2 = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement confirmé - AutomoClick</title>
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
        .header::before {
            content: \'\';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url(\'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.1)"/></svg>\');
        }
        .success-badge {
            width: 90px;
            height: 90px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 45px;
            position: relative;
            z-index: 1;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        .title { 
            font-size: 32px;
            font-weight: 800;
            margin: 0 0 10px 0;
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
            overflow: hidden;
        }
        .transaction-showcase::before {
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 100px;
            opacity: 0.08;
            transform: rotate(15deg);
        }
        .transaction-label {
            color: #047857;
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .transaction-id {
            font-size: 28px;
            font-weight: 900;
            color: #065f46;
            margin: 15px 0 25px 0;
            letter-spacing: 3px;
            font-family: monospace;
            background: rgba(255, 255, 255, 0.8);
            padding: 15px 25px;
            border-radius: 12px;
            border: 2px solid #10b981;
        }
        .transaction-note {
            font-size: 14px;
            color: #047857;
            font-weight: 600;
            margin: 0;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .confirmation-message h3::before {
            content: \'✨\';
            margin-right: 10px;
            font-size: 22px;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .support-card h3::before {
            content: \'🤝\';
            margin-right: 10px;
            font-size: 20px;
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
            .transaction-id { font-size: 22px; letter-spacing: 2px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img class="logo" src="https://automoclick.com/img/logo-automoclick.png" alt="AutomoClick">
            <h1 class="title">Paiement confirmé !</h1>
            <p class="subtitle">Votre transaction a été traitée avec succès</p>
        </div>
        
        <div class="content">
            <div class="thank-you">
                <p><strong>Merci beaucoup pour votre paiement !</strong></p>
                <p>Votre transaction a été traitée avec succès et sécurisé. Votre réservation est maintenant confirmée.</p>
            </div>
            
            <div class="transaction-showcase">
                <div class="transaction-label">🧾 Numéro de transaction</div>
                <div class="transaction-id">' . htmlspecialchars($transaction_id) . '</div>
                <div class="transaction-note">📋 Conservez ce numéro pour vos dossiers</div>
            </div>
            
            <div class="confirmation-message">
                <h3>Votre rendez-vous est confirmé</h3>
                <p>Vous pouvez maintenant accéder à tous les détails dans votre espace client. Un email de rappel vous sera envoyé avant votre rendez-vous.</p>
            </div>
            
            <div class="support-card">
                <h3>Nous restons à votre disposition</h3>
                <p style="color: #7c3aed; margin: 0 0 20px 0; font-size: 15px;">
                    Une question ? Notre équipe est là pour vous aider
                </p>
                <div class="support-links">
                    <a href="https://automoclick.com/mon-compte">Mon espace</a>
                    <a href="https://automoclick.com/contact">Support</a>
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
            <p class="footer-text">© ' . date('Y') . ' Automoclick</p>
            <p class="footer-text">
                🎉 <strong>Merci de votre confiance !</strong><br>
                Nous vous souhaitons une excellente expérience.
            </p>
        </div>
    </div>
</body>
</html>

';
$headers2 = 'From: Automoclick <no-reply-automoclick@automoclick.com>' . "\r\n" .
    'Reply-To: no-reply@automoclick.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion() . "\r\n" .
    'Content-Type: text/html; charset=UTF-8' . "\r\n" .
    'Content-Transfer-Encoding: 8bit';
if (!mail($to2, $subject2, $message2, $headers2)) {
    echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
    <title>Rendez-vous Confirmé</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-8 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-green-500 mb-6" fill="none"
            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        <h1 class="text-3xl font-bold text-green-600 mb-4">Rendez-vous confirmé avec <?= $data_pro['denomination'] ?> !</h1>
        <p class="text-gray-700 mb-4">Le <?= $date_display ?> - <strong><?= $prestation ?></strong></p>
        <p class="text-gray-700 mb-6">Merci d'avoir pris rendez-vous.</p>
        <a href="/"
            class="mt-4 px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition duration-300">
            Retour à l'accueil
        </a>
    </div>
</body>

</html>