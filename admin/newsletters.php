<?php
session_start();
require '../db/dbconnect2.php'; // $db = PDO

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

// Traitement POST pour add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $contenu = $_POST['contenu'] ?? '';
    $styles = $_POST['styles'] ?? '';

    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO newsletter_templates (nom, contenu, styles, date_creation) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$nom, $contenu, $styles]);
        header("Location: newsletters.php");
        exit;
    }

    if ($action === 'edit' && $id) {
        $stmt = $db->prepare("UPDATE newsletter_templates SET nom=?, contenu=?, styles=? WHERE id=?");
        $stmt->execute([$nom, $contenu, $styles, $id]);
        header("Location: newsletters.php");
        exit;
    }
}

// Suppression via POST
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    $stmt = $db->prepare("DELETE FROM newsletter_templates WHERE id=?");
    $stmt->execute([$id]);
    header("Location: newsletters.php");
    exit;
}

// Récupération des templates
$stmt = $db->query("SELECT * FROM newsletter_templates ORDER BY date_creation DESC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Newsletters</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tiny.cloud/1/mlapx6o02keq975lm439phvg5enkhkjyhasj0k7hyqq8enc1/tinymce/8/tinymce.min.js"
        referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: 'textarea#contenu',
            height: 400,
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
            menubar: false,
        });
    </script>
</head>
<body class="bg-gray-100 p-6">
    <div class="container mx-auto">
        <h1 class="text-2xl font-bold mb-4">Gestion des Newsletters</h1>

        <a href="newsletters.php?action=add" class="bg-blue-500 text-white px-4 py-2 rounded mb-4 inline-block">Nouvelle Newsletter</a>

        <?php if ($action === 'add' || ($action === 'edit' && $id)):
            $editData = ['nom'=>'', 'contenu'=>'', 'styles'=>''];
            if ($action === 'edit' && $id) {
                $stmt = $db->prepare("SELECT * FROM newsletter_templates WHERE id=?");
                $stmt->execute([$id]);
                $editData = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        ?>
        <form method="POST" class="bg-white p-6 rounded shadow-md mb-6">
            <label class="block mb-2">Nom du template:</label>
            <input type="text" name="nom" value="<?= htmlspecialchars($editData['nom']) ?>" class="w-full mb-4 border p-2 rounded" required>

            <label class="block mb-2">Contenu HTML (avec placeholders comme {{nom}}, {{email}}, {{image}}):</label>
            <textarea id="contenu" name="contenu"><?= htmlspecialchars($editData['contenu']) ?></textarea>

            <label class="block mb-2 mt-4">Styles CSS (appliqués au template):</label>
            <textarea name="styles" rows="5" class="w-full mb-4 border p-2 rounded"><?= htmlspecialchars($editData['styles']) ?></textarea>

            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded"><?= $action==='add'?'Créer':'Modifier' ?></button>
            <a href="newsletters.php" class="ml-2 text-gray-700">Annuler</a>
        </form>
        <?php endif; ?>

        <table class="w-full bg-white rounded shadow-md">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2">ID</th>
                    <th class="p-2">Nom</th>
                    <th class="p-2">Création</th>
                    <th class="p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $row): ?>
                    <tr class="border-b">
                        <td class="p-2"><?= $row['id'] ?></td>
                        <td class="p-2"><?= htmlspecialchars($row['nom']) ?></td>
                        <td class="p-2"><?= $row['date_creation'] ?></td>
                        <td class="p-2 flex gap-2">
                            <a href="newsletters.php?action=edit&id=<?= $row['id'] ?>" class="text-blue-500">Modifier</a>
                            <form method="POST" action="newsletters.php?action=delete&id=<?= $row['id'] ?>" onsubmit="return confirm('Supprimer cette newsletter ?')">
                                <button type="submit" class="text-red-500">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
