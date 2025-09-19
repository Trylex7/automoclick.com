<?php
session_start();
require_once '../db/dbconnect2.php';
require_once '../includes/webhook.php';

if (!isset($_SESSION['id_pro'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /connexion?success_url={$currentUrl}");
    exit;
}

$numero_pro = $_SESSION['id_pro'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = strtoupper(trim($_POST['nom']));
    $prenom = strtoupper(trim($_POST['prenom']));
    $id = trim($_POST['identifiant']);
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_ARGON2ID);

    if ($nom && $prenom && $id && $role && $_POST['password']) {
        $stmt = $db->prepare("INSERT INTO user_pro (numero_pro, nom, prenom, password, role) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$numero_pro, $nom, $prenom, $password, $role])) {
            $message = "Utilisateur ajouté avec succès !";
        } else {
            $message = "Erreur lors de l'ajout de l'utilisateur.";
        }
    } else {
        $message = "Veuillez remplir tous les champs.";
    }
    $verif_email = 1;
    $q_email = $db->prepare('INSERT INTO login_pro (mdp,verif_email, numero_pro, role) VALUES (?, ?, ?, ?)');
    $q_email->execute([$password, $verif_email, $numero_pro, $role]);
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ajouter un utilisateur - Automoclick PRO</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex flex-col md:flex-row">

       <?php include('../includes/aside.php'); ?>

    <main class="flex-1 max-w-4xl mx-auto p-8 mt-20 md:mt-12 md:ml-64 bg-white rounded-lg shadow-md min-h-[80vh] mb-12">
        <h1 class="text-3xl font-extrabold mb-8 text-gray-900">Ajouter un utilisateur</h1>

        <?php if ($message): ?>
            <div
                class="mb-6 rounded border border-green-400 bg-green-50 text-green-700 px-4 py-3 text-center font-semibold shadow-sm">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-gray-700 font-semibold mb-1">Nom</label>
                <input type="text" name="nom"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-green-500" required>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-1">Prénom</label>
                <input type="text" name="prenom"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-green-500" required>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-1">Identifiant</label>
                <input type="text" name="identifiant"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-green-500" value="<?= $_SESSION['id_pro'] ?>"
                    readonly>
            </div>

            <div class="relative">
                <label class="block text-gray-700 font-semibold mb-1">Mot de passe</label>
                <input type="password" id="password" name="password"
                    class="w-full border border-gray-300 rounded px-4 py-2 pr-10 focus:ring-2 focus:ring-green-500"
                    required>
                <span id="togglePassword" class="material-icons absolute right-3 top-9 cursor-pointer text-gray-500">
                    visibility
                </span>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-1">Rôle</label>
                <select name="role"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:ring-2 focus:ring-green-500" required>
                    <option value="">-- Sélectionner le rôle --</option>
                    <option value="admin">Admin</option>
                    <option value="technicien">Technicien</option>
                    <option value="gestionnaire">Gestionnaire</option>
                    <option value="utilisateur">Utilisateur</option>
                </select>
            </div>

            <button type="submit"
                class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition font-semibold">Ajouter</button>
        </form>
    </main>
</body>
<script>
    const passwordInput = document.getElementById("password");
    const togglePassword = document.getElementById("togglePassword");

    togglePassword.addEventListener("click", () => {
        const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
        passwordInput.setAttribute("type", type);

        // Changer l'icône
        togglePassword.textContent = type === "password" ? "visibility" : "visibility_off";
    });
</script>
</html>