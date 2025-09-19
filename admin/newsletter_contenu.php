<?php
session_start();
require '../db/dbconnect2.php'; // $db = PDO
require __DIR__ . '/newsletter_functions.php';

// Protection CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

// Récupérer la liste des templates pour choix
$stmt = $db->query("SELECT id, nom FROM newsletter_templates ORDER BY date_creation DESC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editData = [
    'id' => null,
    'titre' => '',
    'contenu' => '',
    'template_id' => ''
];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM newsletter_contenus WHERE id=?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC) ?: $editData;
}

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die("Token CSRF invalide.");
    }

    $titre = $_POST['titre'] ?? '';
    $contenu = $_POST['contenu'] ?? '';
    $template_id = $_POST['template_id'] ?? null;

    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO newsletter_contenus (titre, contenu, template_id, date_creation) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$titre, $contenu, $template_id]);
        header("Location: newsletter_contenus.php");
        exit;
    }

    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("UPDATE newsletter_contenus SET titre=?, contenu=?, template_id=? WHERE id=?");
        $stmt->execute([$titre, $contenu, $template_id, $id]);
        header("Location: newsletter_contenus.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $action==='edit' ? 'Modifier' : 'Créer' ?> un contenu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: 'textarea#contenu',
            height: 400,
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | styles | bold italic underline | link image media table | align | numlist bullist indent outdent | removeformat',
            menubar: false,
        });
    </script>
</head>
<body class="bg-gray-100 p-6">
<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-4"><?= $action==='edit' ? 'Modifier' : 'Créer' ?> un contenu</h1>

    <form method="POST" class="bg-white p-6 rounded shadow-md">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

        <label class="block mb-2">Titre :</label>
        <input type="text" name="titre" value="<?= htmlspecialchars($editData['titre']) ?>" class="w-full mb-4 border p-2 rounded" required>

        <label class="block mb-2">Contenu :</label>
        <textarea id="contenu" name="contenu"><?= htmlspecialchars($editData['contenu']) ?></textarea>

        <label class="block mb-2 mt-4">Template associé :</label>
        <select name="template_id" class="w-full mb-4 border p-2 rounded">
            <option value="">-- Aucun template --</option>
            <?php foreach ($templates as $tpl): ?>
                <option value="<?= $tpl['id'] ?>" <?= $editData['template_id']==$tpl['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tpl['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">
            <?= $action==='edit' ? 'Modifier' : 'Créer' ?>
        </button>
        <a href="newsletter_contenus.php" class="ml-2 text-gray-700">Annuler</a>
    </form>
</div>
</body>
</html>
