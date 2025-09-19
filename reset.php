<?php
session_start();
require_once 'db/dbconnect2.php'; // PDO $db
require_once 'includes/webhook.php';
require_once 'api/traker.php';
$success = $error = null;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_GET['selector']) || empty($_GET['validator'])) {
    die("Lien invalide.");
}

$selector = $_GET['selector'];
$validator = $_GET['validator'];

if (!ctype_xdigit($selector) || !ctype_xdigit($validator)) {
    die("Lien invalide.");
}

// Chercher le token
$stmt = $db->prepare("SELECT * FROM password_resets WHERE selector = ? AND expires >= ?");
$stmt->execute([$selector, time()]);
$resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resetRow || !password_verify(hex2bin($validator), $resetRow['token'])) {
    die("Lien invalide ou expiré.");
}

$email = $resetRow['email'];
$users = [];

// 1️⃣ login_user
$stmt = $db->prepare("SELECT 'login_user' AS type, 0 AS id, email FROM login_user WHERE email = ?");
$stmt->execute([$email]);
$loginUser = $stmt->fetch(PDO::FETCH_ASSOC);
if ($loginUser) {
    $users[] = $loginUser;
}

// 2️⃣ login_pro via entreprises
$stmt = $db->prepare("SELECT numero_pro, mdp FROM entreprises JOIN login_pro USING(numero_pro) WHERE email = ?");
$stmt->execute([$email]);
$entreprise = $stmt->fetch(PDO::FETCH_ASSOC);

if ($entreprise) {
    $numeroPro = $entreprise['numero_pro'];
    $mdpPrincipal = $entreprise['mdp'];

    // Vérifier si des comptes additionnels existent
    $stmt = $db->prepare("SELECT id, nom, prenom, password FROM user_pro WHERE numero_pro = ?");
    $stmt->execute([$numeroPro]);
    $additionnels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($additionnels) {
        // Ajouter tous les comptes additionnels
        foreach ($additionnels as $user) {
            $users[] = [
                'type' => 'user_pro',
                'id' => $user['id'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'numero_pro' => $numeroPro,
                'mdp' => $user['password'] // pour comparaison simple si nécessaire
            ];
        }
        // Ajouter aussi le compte principal pour qu'il soit sélectionnable
        $users[] = [
            'type' => 'pro_principal',
            'numero_pro' => $numeroPro,
            'mdp' => $mdpPrincipal
        ];
    } else {
        // Aucun user_pro → c'est le compte principal uniquement
        $users[] = [
            'type' => 'pro_principal',
            'numero_pro' => $numeroPro,
            'mdp' => $mdpPrincipal
        ];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    // Vérification CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Token CSRF invalide.");
    }

    $selected = $_POST['selected_user'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Vérification mot de passe
    if ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8 || strlen($password) > 16) {
        $error = "Le mot de passe doit contenir entre 8 et 16 caractères.";
    } else {
        // Extraire le type et l'id/numero_pro du select
        list($type, $id, $numero_pro) = explode(':', $selected);

        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

        try {
            if ($type === 'login_user') {
                $stmt = $db->prepare("UPDATE login_user SET mdp = ? WHERE email = ?");
                $stmt->execute([$hashedPassword, $email]);
            } elseif ($type === 'pro_principal') {
                $stmt = $db->prepare("UPDATE login_pro SET mdp = ? WHERE numero_pro = ?");
                $stmt->execute([$hashedPassword, $numero_pro]);
            } elseif ($type === 'user_pro') {
                $stmt = $db->prepare("UPDATE user_pro SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $id]);
            }

            $success = "Mot de passe mis à jour avec succès !";
            
            // Optionnel : supprimer le token de réinitialisation
            $stmt = $db->prepare("DELETE FROM password_resets WHERE selector = ?");
            $stmt->execute([$selector]);
            $to = $email;
                $subject = 'Mot de passe mis à jour';
                $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe mis à jour</title>
 <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .email-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: black;
            padding: 20px;
            border: 1px solid #dddddd;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px;
            text-align: center;
        }
        th {
            background-color: black;
            color: #ffffff;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: white;
            padding: 20px;
        }
        .logo {
            width: 150px;
            margin: 0 auto;
            display: block;
        }
        .title {
            text-align: center;
            color: white;
            margin-top: 20px;
        }
        .title_color {
            color: #58b88a;
        }
        .c_btn {
            display: inline-block;
            text-align: center;
            color: white;
            font-size: 20px;
            padding: 15px 30px;
            background-color:  #58b88a;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
        }
        .text_footer {
            text-align: center;
            color: white;
        }
        a {
            color: #58b88a;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            body { padding: 10px; }
            .email-container { width: 100%; padding: 15px; }
            .logo { width: 120px; }
            .c_btn { font-size: 18px; padding: 12px 25px; }
            th, td { padding: 10px; }
            .footer { font-size: 10px; color: white; }
        }
    </style>
</head>
<body>
    <div style="display:none;max-height:0px;overflow:hidden;">Mot de passe mis à jour</div>
    <div class="email-container">
        <table>
            <thead>
                <tr>
                    <th colspan="2">
                        <a href="https://automoclick.com">
                            <img class="logo" src="https://automoclick.com/img/android-chrome-512x512.png" alt="Logo automoclick">
                        </a>
                        <div class="title">Votre mot de passe a été mis à jour <span class="title_color">avec succès</span></div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Nous avons mis à jour votre mot de passe.<br><br>
                        Si vous n\'êtes pas à l\'origine de cette action, veuillez contacter notre support immédiatement.<br><br>
                        <a href="mailto:support@automoclick.com" class="c_btn">Contacter notre support</a><br><br>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="footer">
                        <p>Bonne journée,</p>
                        L\'équipe Automoclick<br><br>
                        <a target="_blank" href="https://instagram.com/automoclick">Suivez-nous sur Instagram</a><br><br>
                        <div class="text_footer">Merci de faire confiance à Automoclick.</div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
';
                $headers = 'From: Automoclick <no-reply@automoclick.com>' . "\r\n" .
                    'Reply-To: support@automoclick.com' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion() . "\r\n" .
                    'Content-Type: text/html; charset=UTF-8' . "\r\n" .
                    'Content-Transfer-Encoding: 8bit';
                     if (!mail($to, $subject, $message, $headers)) {
                    echo 'Une erreur s\'est produite. Veuillez réessayer ulterieurment !';
                }
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour du mot de passe : " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Réinitialisation du mot de passe</title>
<script src="https://cdn.tailwindcss.com"></script>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<!-- Google Material Icons -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

<style>
body { font-family: 'Inter', sans-serif; }
.material-symbols-outlined {
    font-variation-settings:
    'FILL' 0,
    'wght' 400,
    'GRAD' 0,
    'opsz' 48;
}
</style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="bg-white shadow-lg rounded-2xl p-8 w-full max-w-md">
    <h2 class="text-2xl font-bold text-gray-800 text-center mb-6">Réinitialiser votre mot de passe</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!$success && !empty($users)): ?>
    <form method="post" class="space-y-4">
        <div>
            <label class="block text-gray-700 font-medium mb-1">Compte à réinitialiser :</label>
            <select name="selected_user" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <?php foreach($users as $user): ?>
                <option value="<?= $user['type'] ?>:<?= $user['id'] ?? 0 ?>:<?= $user['numero_pro'] ?? 0 ?>">
                    <?php
                    switch ($user['type']) {
                        case 'pro_principal':
                            echo "Compte principal";
                            break;
                        case 'user_pro':
                            echo htmlspecialchars(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? ''));
                            break;
                        case 'login_user':
                            echo "Compte utilisateur";
                            break;
                    }
                    ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-1">Nouveau mot de passe :</label>
            <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-1">Confirmer le mot de passe :</label>
            <input type="password" id="password_confirm" name="password_confirm" required placeholder="••••••••" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>

        <!-- Feedback mot de passe -->
        <div id="password-criteria" class="text-gray-600 text-sm space-y-1 mt-2">
            <p id="length" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>8 à 16 caractères</p>
            <p id="uppercase" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>Au moins une majuscule</p>
            <p id="lowercase" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>Au moins une minuscule</p>
            <p id="number" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>Au moins un chiffre</p>
            <p id="special" class="flex items-center"><span class="material-symbols-outlined mr-2 text-red-600">close</span>Au moins un caractère spécial (!@#$%^&*)</p>
        </div>

        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <button type="submit" name="reset_password" class="w-full bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 transition-colors">Réinitialiser le mot de passe</button>
    </form>
    <?php endif; ?>

    <p class="text-center text-gray-500 text-sm mt-4">
        <a href="/connexion" class="text-blue-600 hover:underline">Retour à la connexion</a>
    </p>
</div>

<script>
const passwordInput = document.getElementById('password');
const criteria = {
    length: document.getElementById('length'),
    uppercase: document.getElementById('uppercase'),
    lowercase: document.getElementById('lowercase'),
    number: document.getElementById('number'),
    special: document.getElementById('special')
};

passwordInput.addEventListener('input', () => {
    const value = passwordInput.value;

    // Fonction pour mettre à jour l'icône
    const updateIcon = (element, condition) => {
        const icon = element.querySelector('span');
        if(condition){
            icon.textContent = 'check';
            icon.classList.remove('text-red-600');
            icon.classList.add('text-green-600');
        } else {
            icon.textContent = 'close';
            icon.classList.remove('text-green-600');
            icon.classList.add('text-red-600');
        }
    }

    // Patterns pour validation
    updateIcon(criteria.length, value.length >= 8 && value.length <= 16);
    updateIcon(criteria.uppercase, /[A-Z]/.test(value));
    updateIcon(criteria.lowercase, /[a-z]/.test(value));
    updateIcon(criteria.number, /\d/.test(value));
    updateIcon(criteria.special, /[!@#$%^&*]/.test(value));
});
</script>

</body>
</html>

