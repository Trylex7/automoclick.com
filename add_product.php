<?php
require_once 'db/dbconnect2.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix = floatval($_POST['prix']);
    $type = $_POST['type'];
    $categorie = $_POST['categorie'];
    $duree_abonnement = isset($_POST['duree_abonnement']) && $_POST['duree_abonnement'] !== '' ? (int) $_POST['duree_abonnement'] : null;
    $image = null;
    $fichier = null;

    // Gestion image
    if (!empty($_FILES['image']['name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image = "public/uploads/images/" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $image);
    }

    // Gestion fichier
    if (!empty($_FILES['fichier']['name'])) {
        $ext = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $lien_fichier = "public/uploads/files/" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES['fichier']['tmp_name'], $lien_fichier);
    }

    if ($nom && $prix > 0) {
        $sql = "INSERT INTO produit 
            (nom, description, prix, type, image, lien_fichier, duree_abonnement, categorie) 
            VALUES 
            (:nom, :description, :prix, :type, :image, :lien_fichier, :duree_abonnement, :categorie)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':nom' => $nom,
            ':description' => $description,
            ':prix' => $prix,
            ':type' => $type,
            ':image' => $image ?? null,
            ':lien_fichier' => $lien_fichier ?? null,
            ':duree_abonnement' => $duree_abonnement,
            ':categorie' => $categorie
        ]);
        $message = "✅ Produit ajouté avec succès.";
    } else {
        $message = "❌ Veuillez remplir tous les champs requis.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - Automoclick</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>

<body class="bg-gray-100 flex h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-white shadow-lg flex flex-col">
        <div class="p-6 text-2xl font-bold text-green-600 border-b">Automoclick</div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="#" class="block px-4 py-2 rounded-lg hover:bg-green-100 font-medium">Dashboard</a>
            <a href="#" class="block px-4 py-2 rounded-lg hover:bg-green-100 font-medium">Produits</a>
            <a href="#" class="block px-4 py-2 rounded-lg hover:bg-green-100 font-medium">Commandes</a>
            <a href="#" class="block px-4 py-2 rounded-lg hover:bg-green-100 font-medium">Clients</a>
            <a href="#" class="block px-4 py-2 rounded-lg hover:bg-green-100 font-medium">Paramètres</a>
        </nav>
        <div class="p-6 border-t">
            <button
                class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg font-semibold">Déconnexion</button>
        </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 p-6 overflow-auto">
        <!-- Topbar -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
            <button id="openModal"
                class="bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-2 rounded-lg shadow-lg">➕
                Ajouter produit</button>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white p-6 rounded-2xl shadow flex flex-col items-start">
                <span class="text-gray-400">Total Produits</span>
                <span class="text-2xl font-bold text-gray-800 mt-2">128</span>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow flex flex-col items-start">
                <span class="text-gray-400">Commandes</span>
                <span class="text-2xl font-bold text-gray-800 mt-2">45</span>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow flex flex-col items-start">
                <span class="text-gray-400">Clients</span>
                <span class="text-2xl font-bold text-gray-800 mt-2">76</span>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-2xl shadow overflow-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix (€)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Catégorie</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php

                    $stmt = $db->query("SELECT * FROM produit ORDER BY nom ASC");
                    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Boucle sur chaque produit
                    foreach ($produits as $produit) {
                        echo '<tr>';
                        echo '<td class="px-6 py-4 whitespace-nowrap">' . htmlspecialchars($produit['nom']) . '</td>';
                        echo '<td class="px-6 py-4 whitespace-nowrap">' . htmlspecialchars($produit['type']) . '</td>';
                        echo '<td class="px-6 py-4 whitespace-nowrap">' . number_format($produit['prix'], 2, '.', '') . '</td>';
                        echo '<td class="px-6 py-4 whitespace-nowrap">' . htmlspecialchars($produit['categorie']) . '</td>';
                        echo '<td class="px-6 py-4 whitespace-nowrap text-right">';
                        echo '<button class="text-blue-600 hover:text-blue-800 mr-2" onclick="modifierProduit(' . $produit['id'] . ')">✏️ Modifier</button>';
                        echo '<button class="text-red-600 hover:text-red-800" onclick="supprimerProduit(' . $produit['id'] . ')">🗑 Supprimer</button>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>

            </table>
        </div>
    </main>

    <!-- Modal -->
    <div id="modalOverlay" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl p-8 relative animate-fadeIn">
            <button id="closeModal"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 text-2xl font-bold">✖</button>
            <h2 class="text-3xl font-bold mb-6 text-gray-800 text-center border-b pb-4">➕ Ajouter un produit</h2>
            <form method="post" enctype="multipart/form-data" class="flex flex-wrap gap-6">

                <div class="flex-1 min-w-[200px]">
                    <label class="block text-gray-700 font-semibold mb-1">Nom *</label>
                    <input type="text" name="nom" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-400 focus:outline-none transition">
                </div>

                <div class="flex-1 min-w-[200px]">
                    <label class="block text-gray-700 font-semibold mb-1">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-400 focus:outline-none transition"></textarea>
                </div>

                <div class="flex-1 min-w-[120px]">
                    <label class="block text-gray-700 font-semibold mb-1">Prix (€) *</label>
                    <input type="number" step="0.01" name="prix" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-400 focus:outline-none transition">
                </div>

                <div class="flex-1 min-w-[150px]">
                    <label class="block text-gray-700 font-semibold mb-1">Type *</label>
                    <select name="type" id="type" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-400 focus:outline-none transition">
                        <option value="article">Article</option>
                        <option value="fichier">Fichier</option>
                        <option value="abonnement">Abonnement</option>
                    </select>
                </div>

                <div class="flex-1 min-w-[150px]">
                    <label class="block text-gray-700 font-semibold mb-1">Catégorie *</label>
                    <select name="categorie" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-400 focus:outline-none transition">
                        <option value="pro">Professionnel</option>
                        <option value="particulier">Particulier</option>
                    </select>
                </div>

                <div class="flex-1 min-w-[150px]">
                    <label class="block text-gray-700 font-semibold mb-1">Image du produit</label>
                    <input type="file" name="image" accept="image/*"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none transition">
                </div>

                <div id="fileField" class="flex-1 min-w-[150px] hidden">
                    <label class="block text-gray-700 font-semibold mb-1">Fichier (PDF, DOCX, etc.)</label>
                    <input type="file" name="fichier"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none transition">
                </div>

                <div id="subField" class="flex-1 min-w-[150px] hidden">
                    <label class="block text-gray-700 font-semibold mb-1">Durée abonnement (jours)</label>
                    <input type="number" name="duree_abonnement"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-400 focus:outline-none transition">
                </div>

                <div class="w-full flex justify-end mt-4">
                    <button type="submit"
                        class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-all font-semibold shadow-lg hover:scale-105">💾
                        Enregistrer</button>
                </div>

            </form>
        </div>
    </div>

    <script>
        const openModal = document.getElementById("openModal");
        const closeModal = document.getElementById("closeModal");
        const modalOverlay = document.getElementById("modalOverlay");

        openModal.addEventListener("click", () => modalOverlay.classList.remove("hidden"));
        closeModal.addEventListener("click", () => modalOverlay.classList.add("hidden"));
        modalOverlay.addEventListener("click", (e) => { if (e.target === modalOverlay) modalOverlay.classList.add("hidden"); });

        const typeSelect = document.getElementById('type');
        const fileField = document.getElementById('fileField');
        const subField = document.getElementById('subField');
        function toggleFields() {
            fileField.classList.toggle('hidden', typeSelect.value !== 'fichier');
            subField.classList.toggle('hidden', typeSelect.value !== 'abonnement');
        }
        typeSelect.addEventListener('change', toggleFields);
        toggleFields();
    </script>

</body>

</html>