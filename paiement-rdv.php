<?php
session_start();
require_once 'header.php';
require_once 'includes/webhook.php';
$cle_secrete = 'y8KaIBCurqNas0vlSEEeMNPLnsOgG1';

// Générer un token CSRF si il n'existe pas
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function genererNumero10Chiffres()
{
    $min = 1000000000;
    $max = 9999999999;
    return random_int($min, $max);
}

function chiffrer($texte, $cle)
{
    return urlencode(base64_encode(openssl_encrypt($texte, 'AES-128-ECB', $cle)));
}

function dechiffrer($texte_chiffre, $cle)
{
    return openssl_decrypt(base64_decode(urldecode($texte_chiffre)), 'AES-128-ECB', $cle);
}

// Vérifie que le paramètre 'h' est présent
if (!isset($_GET['h'])) {
    die("Paramètre manquant.");
}

function imageToBase64($filePath, $maxWidth = 800, $quality = 70)
{
    $source = imagecreatefromstring(file_get_contents($filePath));
    $width = imagesx($source);
    $height = imagesy($source);

    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = intval($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $destination = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled(
        $destination,
        $source,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $width,
        $height
    );

    ob_start();
    imagejpeg($destination, null, $quality);
    $imageData = ob_get_clean();

    imagedestroy($source);
    imagedestroy($destination);

    return 'data:image/jpeg;base64,' . base64_encode($imageData);
}

$base64 = imageToBase64('asset/style/img/bg-automoclick.jpg');
$token = $_GET['h'];

// Déchiffrement du token
$all_data = dechiffrer($token, $cle_secrete);

// Décodage du JSON
$data = json_decode($all_data, true);

// Vérifie si le JSON est valide
if (!is_array($data)) {
    die("Données du token invalides.");
}

// Accès aux données
$numero_pro = $data['numero_pro'] ?? null;
$date = $data['date'] ?? null;
$heure = $data['heure'] ?? null;
$rdv_ids = json_decode($data['rdv_id'] ?? '[]', true);
$prix_total = $data['prix_total'] ?? null;
$prestations = $data['prestation_ids'];
$expire = $data['expire'] ?? null;

require_once 'db/dbconnect2.php';
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Stancer\Config;
use Stancer\Card;
use Stancer\Payment;
use Stancer\Auth;

if (!is_array($rdv_ids) || count($rdv_ids) === 0) {
    http_response_code(403);
    exit('Aucun rendez-vous valide transmis.');
}

function genererNumeroUnique(PDO $db): string
{
    do {
        $numero = str_pad(mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) FROM devis WHERE numero = ? AND statut = 'facture'");
        $stmt->execute([$numero]);
        $existe = $stmt->fetchColumn() > 0;
    } while ($existe);

    return $numero;
}

$numero_f = genererNumeroUnique($db);
$total_prix = 0;
foreach ($rdv_ids as $rdv_id) {
    if (time() > $expire) {
        $delete = $db->prepare("DELETE FROM rdvs WHERE rdv_id = ?");
        $delete->execute([$rdv_id]);
        unset($_SESSION['paiements_en_cours'][$rdv_id]);
        exit("Temps expiré pour le rendez-vous $rdv_id. Réservation annulée.");
    }
    $total_prix = $prix_total;
}
$taxes = 500;
$total_prix_display = 0;
$numero_client = $_SESSION['id_client'] ?? 'client-inconnu';

// Gestion du retour après 3D Secure
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['payment'])) {
    try {
        $api_secret = 'sprod_wzX1s3orkjfXwUoHuSbXgBFC';
        $api_key = 'pprod_fZgZcC0HE7nPWDM0kceHGLmi';
        $config = Config::init([$api_key, $api_secret]);
        $config->setMode(Config::LIVE_MODE);
        
        $payment = new Payment($_GET['payment']);
        $payment->fetch();
        
        $status = $payment->getStatus()->value;
        $transactionId = $payment->getId();
        
        if ($status === 'authorized' || $status === 'captured' || $status === 'to_capture') {
            // Paiement réussi - continuer avec la logique de confirmation...
            // [Votre code de confirmation existant ici]
            header('Location: rdv_confirm.php?x=' . urlencode($token) . '&payment={{payment_id}}');
            exit;
        } else {
            // Paiement échoué
            $delete = $db->prepare("DELETE FROM rdvs WHERE rdv_id = ?");
            foreach ($rdv_ids as $rdv_id) {
                $delete->execute([$rdv_id]);
                unset($_SESSION['paiements_en_cours'][$rdv_id]);
            }
            header('Location: paiement_refuse.php');
            exit;
        }
    } catch (Exception $e) {
        echo "Erreur lors de la vérification du paiement : " . $e->getMessage();
        exit;
    }
}

// Traitement du formulaire de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     die('Erreur CSRF : requête non autorisée.');
    // }
    
    $cvc = $_POST['cvc'] ?? null;
    $expire_m = $_POST['expire_m'] ?? null;
    $expire_y = $_POST['expire_y'] ?? null;
    $card_number = $_POST['card'] ?? null;
    $cardholder_name = $_POST['name'] ?? 'Jean Dupont';

    if (!$card_number || !$expire_m || !$expire_y || !$cvc) {
        exit('Veuillez remplir tous les champs.');
    }
    
    $taxes = 500;
    $amount = (int) ($total_prix * 100) + $taxes;
    $api_secret = 'sprod_wzX1s3orkjfXwUoHuSbXgBFC';
    $api_key = 'pprod_fZgZcC0HE7nPWDM0kceHGLmi';
    
    try {
        $config = Config::init([$api_key, $api_secret]);
        $config->setMode(Config::LIVE_MODE);

        $card = new Card([
            'number' => preg_replace('/\D/', '', $card_number),
            'exp_month' => (int) $expire_m,
            'exp_year' => (int) ('20' . $expire_y),
            'cvc' => $cvc,
            'name' => $cardholder_name,
        ]);

        $payment = new Payment([
            'amount' => $amount,
            'currency' => 'EUR',
            'card' => $card,
            'return_url' => 'https://automoclick.com/paiement-rdv.php?h=' . urlencode($token) ,
            'description'=> 'Paiement Automoclick',
        ]);
        
        // Envoyer le paiement
        $payment->send();
        
        // Récupérer le statut après envoi
        $status = $payment->getStatus()->value;
        $transactionId = $payment->getId();
        $currency = $payment->getCurrency()->value;
        $dateStr = date('Y-m-d H:i:s');

        $lastname = $card->getName() ?? 'Non défini';
        $cardLast4 = $card->getLast4();
        $cardBrand = $card->getBrand();

        // Enregistrer la transaction
        $stmt = $db->prepare("INSERT INTO transactions (
            transaction_id, numero_pro, numero_client, client_firstname, client_lastname, client_email,
            amount_cents, currency, transaction_date, status, card_last4, card_brand
        ) VALUES (
            :transaction_id, :numero_pro, :numero_client, :client_firstname, :client_lastname, :client_email,
            :amount_cents, :currency, :transaction_date, :status, :card_last4, :card_brand
        )");

        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':numero_pro' => $numero_pro,
            ':numero_client' => $numero_client,
            ':client_firstname' => null,
            ':client_lastname' => $lastname,
            ':client_email' => null,
            ':amount_cents' => $amount,
            ':currency' => $currency,
            ':transaction_date' => $dateStr,
            ':status' => $status,
            ':card_last4' => $cardLast4,
            ':card_brand' => $cardBrand,
        ]);

        // Vérifier si une authentification 3D Secure est requise
        if ($payment->getAuth() && $payment->getAuth()->getRedirectUrl()) {
            // Redirection vers 3D Secure
            header('Location: ' . $payment->getAuth()->getRedirectUrl());
            exit;
        }

        // Si pas de 3D Secure, vérifier le statut directement
        if ($status === 'authorized' || $status === 'captured' || $status === 'to_capture') {
            // Mise à jour des rendez-vous
            $update = $db->prepare("UPDATE rdvs SET etat = 'confirme', id_payment = ? WHERE rdv_id = ?");
            foreach ($rdv_ids as $rdv_id) {
                $update->execute([$transactionId, $rdv_id]);
                unset($_SESSION['paiements_en_cours'][$rdv_id]);
            }
            
            // [Votre code de génération de facture et d'email existant...]
            
            header('Location: rdv_confirm.php?x=' . urlencode($token));
            exit;
        } else {
            // Paiement refusé
            $delete = $db->prepare("DELETE FROM rdvs WHERE rdv_id = ?");
            foreach ($rdv_ids as $rdv_id) {
                $delete->execute([$rdv_id]);
                unset($_SESSION['paiements_en_cours'][$rdv_id]);
            }
            header('Location: paiement_refuse');
            exit;
        }
    } catch (Exception $e) {
        echo "Erreur de paiement : " . $e->getMessage();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-xl rounded-2xl p-8 max-w-md w-full">
        <form method="POST" class="space-y-5">
            <div class="text-center">
                <h1 class="text-2xl font-bold mb-2">Total à payer</h1>
                <p class="text-lg text-gray-700"><?= number_format($total_prix, 2, ',', ' ') ?> €</p>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Numéro de carte</label>
                <input type="text" name="card" placeholder="0000 0000 0000 0000"
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    required>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mois d'expiration</label>
                    <input id="expire_m" type="text" name="expire_m" maxlength="2" placeholder="MM"
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Année d'expiration</label>
                    <input type="text" name="expire_y" maxlength="2" placeholder="YY"
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">CVC</label>
                <input type="text" name="cvc" maxlength="3" placeholder="123"
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nom du titulaire</label>
                <input type="text" name="name" placeholder="Jean Dupont"
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="pt-4">
                <input type="submit" value="Payer"
                    class="w-full bg-green-600 text-white font-semibold py-2 rounded-lg hover:bg-green-700 transition">
            </div>
        </form>
    </div>
    
    <script>
        document.getElementById('expire_m').addEventListener('input', function () {
            let value = this.value.replace(/\D/g, '');
            if (value.length === 2 && parseInt(value, 10) > 12) {
                this.value = '12';
            } else {
                this.value = value;
            }
        });
    </script>
</body>
</html>
