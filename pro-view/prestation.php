<?php
require_once '../header.php';
session_start();
require '../db/dbconnect2.php';
require '../vendor/autoload.php';
require_once '../includes/webhook.php';
use Dompdf\Dompdf;
use Dompdf\Options;
if (!isset($_SESSION['id_pro'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /connexion?success_url={$currentUrl}");
    exit;
}
$token = bin2hex(random_bytes(32));
$numero_pro = $_SESSION['id_pro'] ?? null;
$data_spec = $db->prepare('SELECT spe FROM entreprises WHERE numero_pro = ? ');
$data_spec->execute([$numero_pro]);
$data_s = $data_spec->fetch(PDO::FETCH_ASSOC);
$specialisation = $data_s['spe'];
// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['new_photo']) && isset($_POST['photo_id'])) {
        $photoId = (int) $_POST['photo_id'];
        $file = $_FILES['new_photo'];
        $edit_id = (int) $_POST['edit_id_photo'];
        if ($file['error'] === UPLOAD_ERR_OK) {

            // Vérifie que l'image existe déjà
            $stmt = $db->prepare("SELECT chemin FROM photos_prestations_vehicule WHERE id = ? AND id_prestation = ? AND numero_pro = ?");
            $stmt->execute([$photoId, $edit_id, $numero_pro]);
            $oldPath = $stmt->fetchColumn();

            // Supprime l'ancienne image si elle existe
            if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
                unlink(__DIR__ . '/' . $oldPath);
            }

            // Gérer l'upload de la nouvelle photo
            $uploadDir = __DIR__ . '/uploads/prestations/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $originalName = basename($file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($extension, $allowedExtensions)) {
                die("Type de fichier non autorisé.");
            }

            $newName = time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
            $newPath = $uploadDir . $newName;
            $relativePath = 'uploads/prestations/' . $newName;

            if (move_uploaded_file($file['tmp_name'], $newPath)) {
                if ($oldPath) {
                    // Mise à jour de la photo existante
                    $stmt = $db->prepare("UPDATE photos_prestations_vehicule SET chemin = ? WHERE id = ? AND id_prestation = ? AND numero_pro = ?");
                    $stmt->execute([$relativePath, $photoId, $edit_id, $numero_pro]);
                } else {
                    // Nouvelle insertion
                    $stmt = $db->prepare("INSERT INTO photos_prestations_vehicule (id_prestation, numero_pro, chemin) VALUES (?, ?, ?)");
                    $stmt->execute([$edit_id, $numero_pro, $relativePath]);
                }
            } else {
                die("Erreur lors de l'upload du fichier.");
            }
        }
    }

    if (isset($_POST['delete_photo'], $_POST['photo_id'], $_POST['edit_id_p'])) {
        $edit_id = (int) $_POST['edit_id_p'];
        $photoId = (int) $_POST['photo_id'];

        // Récupérer le chemin
        $stmt = $db->prepare("SELECT chemin FROM photos_prestations_vehicule WHERE id = ? AND id_prestation = ? AND numero_pro = ?");
        $stmt->execute([$photoId, $edit_id, $numero_pro]);
        $oldPath = $stmt->fetchColumn();

        if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
            unlink(__DIR__ . '/' . $oldPath);
        }

        // Supprimer de la base
        $stmt = $db->prepare("DELETE FROM photos_prestations_vehicule WHERE id = ? AND id_prestation = ? AND numero_pro = ?");
        $stmt->execute([$photoId, $edit_id, $numero_pro]);

    }

    // Supprimer une prestation
    if (isset($_POST['delete_id'])) {
        $db->prepare("DELETE FROM prestations WHERE id = ? AND numero_pro = ?")
            ->execute([$_POST['delete_id'], $numero_pro]);
        $data_pro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
        $data_pro->execute([$numero_pro]);
        $pro = $data_pro->fetch(PDO::FETCH_ASSOC);

        $data_prestation = $db->prepare('SELECT * FROM prestations WHERE numero_pro = ?');
        $data_prestation->execute([$numero_pro]);
        $prestations = $data_prestation->fetchAll(PDO::FETCH_ASSOC);

        // Construction du tableau HTML
        $prestationsHtml = '';
        $duree_totale = 0;

        foreach ($prestations as $p) {
            $prixHT = (float) $p['prix'];
            $tva = isset($p['tva']) ? (float) $p['tva'] : 20;
            $prixTTC = $prixHT * (1 + $tva / 100);
            $duree_totale += (float) $p['duree'];

            $prestationsHtml .= '<tr>
                <td>' . htmlspecialchars($p['nom']) . '</td>
                <td>' . number_format($p['duree'], 2, ',', ' ') . ' h</td>
                <td>' . number_format($prixHT, 2, ',', ' ') . ' €</td>
                <td>' . number_format($tva, 2, ',', ' ') . ' %</td>
                <td>' . number_format($prixTTC, 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $taux_horaire = isset($pro['taux_horaire']) ? (float) $pro['taux_horaire'] : 0;
        $cout_main_oeuvre = $duree_totale * $taux_horaire;

        // Génération HTML
        $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Conditions Générales de Prestations - ' . htmlspecialchars($pro['denomination']) . '</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.4; }
    h1, h2 { color: #2F4F4F; }
    h1 { font-size: 18px; margin-bottom: 10px; }
    h2 { font-size: 14px; margin-top: 20px; }
    p, ul { margin: 8px 0; }
    ul { padding-left: 20px; }
    strong { color: #000; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>
</head>
<body>
<h1>Conditions Générales de Prestations de Services (CGPS)</h1>
<p>Les présentes Conditions Générales de prestations de services régissent les relations entre <strong>' . htmlspecialchars($pro['denomination']) . '</strong> et ses clients.</p>

<h2>ARTICLE 1 : DISPOSITIONS GÉNÉRALES</h2>
<p>Les présentes CGPS régissent les relations entre <strong>' . htmlspecialchars($pro['denomination']) . '</strong> et ses clients dans le cadre des prestations de services. Toute commande implique l’adhésion pleine et entière du client aux présentes CGPS.</p>

<h2>ARTICLE 2 : NATURE DES PRESTATIONS</h2>
<p><strong>' . htmlspecialchars($pro['denomination']) . '</strong> propose les prestations suivantes :</p>
<table>
<thead>
<tr><th>Prestation</th><th>Durée</th><th>Prix HT (€)</th><th>TVA (%)</th><th>Prix TTC (€)</th></tr>
</thead>
<tbody>' . $prestationsHtml . '</tbody>
</table>

<p><strong>Taux horaire de la main d’œuvre :</strong> ' . number_format($pro['taux_horaire'], 2, ',', ' ') . ' € HT</p>

<h2>ARTICLE 3 : DEVIS ET COMMANDE</h2>
<p>Un devis peut être établi à la demande du client, précisant les éléments de la prestation. La commande peut être validée directement via la plateforme sans signature préalable du devis.</p>

<h2>ARTICLE 4 : PRIX</h2>
<p>Les prix des prestations sont exprimés en euros hors taxes (HT) et toutes taxes comprises (TTC), selon le taux de TVA en vigueur.</p>

<h2>ARTICLE 5 : MODALITÉS DE PAIEMENT</h2>
<p>Le paiement des prestations s’effectue intégralement lors de la prise de rendez-vous sur la plateforme Automoclick, par carte bancaire ou tout autre moyen de paiement sécurisé proposé.</p>

<h2>ARTICLE 6 : MODIFICATION DES RENDEZ-VOUS</h2>
<p>Les prestations sont <strong>modifiables</strong> jusqu\'à 48 heures avant la date prévue. <strong>Aucun remboursement ni annulation</strong> ne sera accepté après la validation du paiement.</p>

<h2>ARTICLE 7 : FORCE MAJEURE</h2>
<p>En cas de force majeure, aucune des parties ne pourra être tenue responsable. La partie concernée devra informer l’autre dans un délai de 5 jours ouvrés.</p>

<h2>ARTICLE 8 : OBLIGATIONS ET CONFIDENTIALITÉ</h2>
<p><strong>' . htmlspecialchars($pro['denomination']) . '</strong> s’engage à garantir la confidentialité des données échangées avec le client.</p>

<h2>ARTICLE 9 : RESPONSABILITÉ</h2>
<p>Le prestataire s’engage à fournir les moyens nécessaires à la bonne exécution de la prestation. Sa responsabilité ne peut excéder le montant HT payé pour la prestation.</p>

<h2>ARTICLE 10 : RÉFÉRENCES</h2>
<p>Sauf demande contraire,<strong>' . htmlspecialchars($pro['denomination']) . '</strong> autorise le client à mentionner son nom ou logo à titre de référence.</p>

<h2>ARTICLE 11 : LITIGES</h2>
<p>Toute contestation sera soumise à un arbitrage selon les règles de la Chambre de Commerce Internationale.</p>

<h2>ARTICLE 12 : LOI APPLICABLE</h2>
<p>Le présent contrat est régi par la loi française.</p>
</body>
</html>';

        // PDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nom de fichier sécurisé
        $filename = "CGP-" . preg_replace('/[^a-zA-Z0-9_-]/', '', $pro['denomination']) . ".pdf";
        $pdf_directory = __DIR__ . 'docs/cgp/';
        $pdf_path = $pdf_directory . $filename;

        // Création du dossier si besoin
        if (!is_dir($pdf_directory)) {
            if (!mkdir($pdf_directory, 0775, true)) {
                die("❌ Erreur : Impossible de créer le dossier docs/cgp.");
            }
        }

        // Sauvegarde du PDF
        if (file_put_contents($pdf_path, $dompdf->output()) === false) {
            die("❌ Erreur : Impossible d'enregistrer le fichier PDF.");
        }

        // Encodage base64 si nécessaire
        $file_content = file_get_contents($pdf_path);
        if ($file_content === false) {
            die("❌ Erreur : Impossible de lire le fichier PDF.");
        }
        $file_content_base64 = chunk_split(base64_encode($file_content));
    }

    // Modifier une prestation
    elseif (isset($_POST['edit_id'])) {
        $duree_edit = isset($_POST['duree']) && $_POST['duree'] !== ''
            ? (int) $_POST['duree']
            : null;
        $prix = isset($data_pro['prix']) ? (float) $data_pro['prix'] : 0;

        $db->prepare("UPDATE prestations SET nom = ?, duree = ?, prix = ?, ref = ?, tva = ? WHERE id = ? AND numero_pro = ?")
            ->execute([
                $_POST['nom'],
                $duree_edit,
                $_POST['prix_f'] ?? $_POST['prix_j'],
                $_POST['ref'] ?? null,
                $_POST['tva'],
                $_POST['edit_id'],
                $numero_pro
            ]);

        $update = $db->prepare('UPDATE entreprises SET profil_valid = "1" WHERE numero_pro = ?');
        $update->execute([$numero_pro]);

        $data_pro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
        $data_pro->execute([$numero_pro]);
        $pro = $data_pro->fetch(PDO::FETCH_ASSOC);

        $data_prestation = $db->prepare('SELECT * FROM prestations WHERE numero_pro = ?');
        $data_prestation->execute([$numero_pro]);
        $prestations = $data_prestation->fetchAll(PDO::FETCH_ASSOC);

        // Construction du tableau HTML
        $prestationsHtml = '';
        $duree_totale = 0;

        foreach ($prestations as $p) {
            $prixHT = (float) $p['prix'];
            $tva = isset($p['tva']) ? (float) $p['tva'] : 20;
            $prixTTC = $prixHT * (1 + $tva / 100);
            $duree_totale += (float) $p['duree'];

            $prestationsHtml .= '<tr>
                <td>' . htmlspecialchars($p['nom']) . '</td>
                <td>' . number_format($p['duree'], 2, ',', ' ') . ' h</td>
                <td>' . number_format($prixHT, 2, ',', ' ') . ' €</td>
                <td>' . number_format($tva, 2, ',', ' ') . ' %</td>
                <td>' . number_format($prixTTC, 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $taux_horaire = isset($pro['taux_horaire']) ? (float) $pro['taux_horaire'] : 0;
        $cout_main_oeuvre = $duree_totale * $taux_horaire;

        // Génération HTML
        $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Conditions Générales de Prestations - ' . htmlspecialchars($pro['denomination']) . '</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.4; }
    h1, h2 { color: #2F4F4F; }
    h1 { font-size: 18px; margin-bottom: 10px; }
    h2 { font-size: 14px; margin-top: 20px; }
    p, ul { margin: 8px 0; }
    ul { padding-left: 20px; }
    strong { color: #000; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>
</head>
<body>
<h1>Conditions Générales de Prestations de Services (CGPS)</h1>
<p>Les présentes Conditions Générales de prestations de services régissent les relations entre <strong>' . htmlspecialchars($pro['denomination']) . '</strong> et ses clients.</p>

<h2>ARTICLE 1 : DISPOSITIONS GÉNÉRALES</h2>
<p>Les présentes CGPS régissent les relations entre <strong>' . htmlspecialchars($pro['denomination']) . '</strong> et ses clients dans le cadre des prestations de services. Toute commande implique l’adhésion pleine et entière du client aux présentes CGPS.</p>

<h2>ARTICLE 2 : NATURE DES PRESTATIONS</h2>
<p><strong>' . htmlspecialchars($pro['denomination']) . '</strong> propose les prestations suivantes :</p>
<table>
<thead>
<tr><th>Prestation</th><th>Durée</th><th>Prix HT (€)</th><th>TVA (%)</th><th>Prix TTC (€)</th></tr>
</thead>
<tbody>' . $prestationsHtml . '</tbody>
</table>

<p><strong>Taux horaire de la main d’œuvre :</strong> ' . number_format($pro['taux_horaire'], 2, ',', ' ') . ' € HT</p>

<h2>ARTICLE 3 : DEVIS ET COMMANDE</h2>
<p>Un devis peut être établi à la demande du client, précisant les éléments de la prestation. La commande peut être validée directement via la plateforme sans signature préalable du devis.</p>

<h2>ARTICLE 4 : PRIX</h2>
<p>Les prix des prestations sont exprimés en euros hors taxes (HT) et toutes taxes comprises (TTC), selon le taux de TVA en vigueur.</p>

<h2>ARTICLE 5 : MODALITÉS DE PAIEMENT</h2>
<p>Le paiement des prestations s’effectue intégralement lors de la prise de rendez-vous sur la plateforme Automoclick, par carte bancaire ou tout autre moyen de paiement sécurisé proposé.</p>

<h2>ARTICLE 6 : MODIFICATION DES RENDEZ-VOUS</h2>
<p>Les prestations sont <strong>modifiables</strong> jusqu\'à 48 heures avant la date prévue. <strong>Aucun remboursement ni annulation</strong> ne sera accepté après la validation du paiement.</p>

<h2>ARTICLE 7 : FORCE MAJEURE</h2>
<p>En cas de force majeure, aucune des parties ne pourra être tenue responsable. La partie concernée devra informer l’autre dans un délai de 5 jours ouvrés.</p>

<h2>ARTICLE 8 : OBLIGATIONS ET CONFIDENTIALITÉ</h2>
<p><strong>' . htmlspecialchars($pro['denomination']) . '</strong> s’engage à garantir la confidentialité des données échangées avec le client.</p>

<h2>ARTICLE 9 : RESPONSABILITÉ</h2>
<p>Le prestataire s’engage à fournir les moyens nécessaires à la bonne exécution de la prestation. Sa responsabilité ne peut excéder le montant HT payé pour la prestation.</p>

<h2>ARTICLE 10 : RÉFÉRENCES</h2>
<p>Sauf demande contraire,<strong>' . htmlspecialchars($pro['denomination']) . '</strong> autorise le client à mentionner son nom ou logo à titre de référence.</p>

<h2>ARTICLE 11 : LITIGES</h2>
<p>Toute contestation sera soumise à un arbitrage selon les règles de la Chambre de Commerce Internationale.</p>

<h2>ARTICLE 12 : LOI APPLICABLE</h2>
<p>Le présent contrat est régi par la loi française.</p>
</body>
</html>';

        // PDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nom de fichier sécurisé
        $filename = "CGP-" . preg_replace('/[^a-zA-Z0-9_-]/', '', $pro['denomination']) . ".pdf";
        $pdf_directory = __DIR__ . 'docs/cgp/';
        $pdf_path = $pdf_directory . $filename;

        // Création du dossier si besoin
        if (!is_dir($pdf_directory)) {
            if (!mkdir($pdf_directory, 0775, true)) {
                die("❌ Erreur : Impossible de créer le dossier docs/cgp.");
            }
        }

        // Sauvegarde du PDF
        if (file_put_contents($pdf_path, $dompdf->output()) === false) {
            die("❌ Erreur : Impossible d'enregistrer le fichier PDF.");
        }

        // Encodage base64 si nécessaire
        $file_content = file_get_contents($pdf_path);
        if ($file_content === false) {
            die("❌ Erreur : Impossible de lire le fichier PDF.");
        }
        $file_content_base64 = chunk_split(base64_encode($file_content));
    }

    // Ajouter une prestation
    elseif (isset($_POST['new_prest'])) {
        $duree = isset($_POST['new_duree']) && $_POST['new_duree'] !== ''
            ? (int) $_POST['new_duree']
            : null;
        $db->prepare("INSERT INTO prestations (numero_pro, nom, ref, duree, prix, tva ) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([
                $numero_pro,
                $_POST['new_nom'],
                $_POST['new_ref'] ?? null,
                $duree,
                $_POST['new_prix'],
                $_POST['new_tva']
            ]);
        $data_pro = $db->prepare('SELECT * FROM entreprises WHERE numero_pro = ?');
        $data_pro->execute([$numero_pro]);
        $pro = $data_pro->fetch(PDO::FETCH_ASSOC);

        $data_prestation = $db->prepare('SELECT * FROM prestations WHERE numero_pro = ?');
        $data_prestation->execute([$numero_pro]);
        $prestations = $data_prestation->fetchAll(PDO::FETCH_ASSOC);

        // Construction du tableau HTML
        $prestationsHtml = '';
        $duree_totale = 0;

        foreach ($prestations as $p) {
            $prixHT = (float) $p['prix'];
            $tva = isset($p['tva']) ? (float) $p['tva'] : 20;
            $prixTTC = $prixHT * (1 + $tva / 100);
            $duree_totale += (float) $p['duree'];

            $prestationsHtml .= '<tr>
                <td>' . htmlspecialchars($p['nom']) . '</td>
                <td>' . number_format($p['duree'], 2, ',', ' ') . ' h</td>
                <td>' . number_format($prixHT, 2, ',', ' ') . ' €</td>
                <td>' . number_format($tva, 2, ',', ' ') . ' %</td>
                <td>' . number_format($prixTTC, 2, ',', ' ') . ' €</td>
            </tr>';
        }

        $taux_horaire = isset($pro['taux_horaire']) ? (float) $pro['taux_horaire'] : 0;
        $cout_main_oeuvre = $duree_totale * $taux_horaire;

        // Génération HTML
        $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Conditions Générales de Prestations - ' . htmlspecialchars($pro['denomination']) . '</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.4; }
    h1, h2 { color: #2F4F4F; }
    h1 { font-size: 18px; margin-bottom: 10px; }
    h2 { font-size: 14px; margin-top: 20px; }
    p, ul { margin: 8px 0; }
    ul { padding-left: 20px; }
    strong { color: #000; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>
</head>
<body>
<h1>Conditions Générales de Prestations de Services (CGPS)</h1>
<p>Les présentes Conditions Générales de prestations de services régissent les relations entre <strong>' . htmlspecialchars($pro['denomination']) . '</strong> et ses clients.</p>

<h2>ARTICLE 1 : DISPOSITIONS GÉNÉRALES</h2>
<p>Les présentes CGPS régissent les relations entre <strong>' . htmlspecialchars($pro['denomination']) . '</strong> et ses clients dans le cadre des prestations de services. Toute commande implique l’adhésion pleine et entière du client aux présentes CGPS.</p>

<h2>ARTICLE 2 : NATURE DES PRESTATIONS</h2>
<p><strong>' . htmlspecialchars($pro['denomination']) . '</strong> propose les prestations suivantes :</p>
<table>
<thead>
<tr><th>Prestation</th><th>Durée</th><th>Prix HT (€)</th><th>TVA (%)</th><th>Prix TTC (€)</th></tr>
</thead>
<tbody>' . $prestationsHtml . '</tbody>
</table>

<p><strong>Taux horaire de la main d’œuvre :</strong> ' . number_format($pro['taux_horaire'], 2, ',', ' ') . ' € HT</p>

<h2>ARTICLE 3 : DEVIS ET COMMANDE</h2>
<p>Un devis peut être établi à la demande du client, précisant les éléments de la prestation. La commande peut être validée directement via la plateforme sans signature préalable du devis.</p>

<h2>ARTICLE 4 : PRIX</h2>
<p>Les prix des prestations sont exprimés en euros hors taxes (HT) et toutes taxes comprises (TTC), selon le taux de TVA en vigueur.</p>

<h2>ARTICLE 5 : MODALITÉS DE PAIEMENT</h2>
<p>Le paiement des prestations s’effectue intégralement lors de la prise de rendez-vous sur la plateforme Automoclick, par carte bancaire ou tout autre moyen de paiement sécurisé proposé.</p>

<h2>ARTICLE 6 : MODIFICATION DES RENDEZ-VOUS</h2>
<p>Les prestations sont <strong>modifiables</strong> jusqu\'à 48 heures avant la date prévue. <strong>Aucun remboursement ni annulation</strong> ne sera accepté après la validation du paiement.</p>

<h2>ARTICLE 7 : FORCE MAJEURE</h2>
<p>En cas de force majeure, aucune des parties ne pourra être tenue responsable. La partie concernée devra informer l’autre dans un délai de 5 jours ouvrés.</p>

<h2>ARTICLE 8 : OBLIGATIONS ET CONFIDENTIALITÉ</h2>
<p><strong>' . htmlspecialchars($pro['denomination']) . '</strong> s’engage à garantir la confidentialité des données échangées avec le client.</p>

<h2>ARTICLE 9 : RESPONSABILITÉ</h2>
<p>Le prestataire s’engage à fournir les moyens nécessaires à la bonne exécution de la prestation. Sa responsabilité ne peut excéder le montant HT payé pour la prestation.</p>

<h2>ARTICLE 10 : RÉFÉRENCES</h2>
<p>Sauf demande contraire,<strong>' . htmlspecialchars($pro['denomination']) . '</strong> autorise le client à mentionner son nom ou logo à titre de référence.</p>

<h2>ARTICLE 11 : LITIGES</h2>
<p>Toute contestation sera soumise à un arbitrage selon les règles de la Chambre de Commerce Internationale.</p>

<h2>ARTICLE 12 : LOI APPLICABLE</h2>
<p>Le présent contrat est régi par la loi française.</p>
</body>
</html>';

        // PDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nom de fichier sécurisé
        $filename = "CGP-" . preg_replace('/[^a-zA-Z0-9_-]/', '', $pro['denomination']) . ".pdf";
        $pdf_directory = __DIR__ . 'docs/cgp/';
        $pdf_path = $pdf_directory . $filename;

        // Création du dossier si besoin
        if (!is_dir($pdf_directory)) {
            if (!mkdir($pdf_directory, 0775, true)) {
                die("❌ Erreur : Impossible de créer le dossier docs/cgp.");
            }
        }

        // Sauvegarde du PDF
        if (file_put_contents($pdf_path, $dompdf->output()) === false) {
            die("❌ Erreur : Impossible d'enregistrer le fichier PDF.");
        }

        // Encodage base64 si nécessaire
        $file_content = file_get_contents($pdf_path);
        if ($file_content === false) {
            die("❌ Erreur : Impossible de lire le fichier PDF.");
        }
        $file_content_base64 = chunk_split(base64_encode($file_content));
    } elseif (isset($_POST['new_prest_v'])) {
        // 1. Insertion de la prestation
        $stmt = $db->prepare("INSERT INTO prestations_vehicule (numero_pro, type_presta, immatriculation, model, boite, carburant, description, model_annee, date_circulation, marque, kilometrage, prix, prix_j, tva, token, date_c ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $numero_pro,
            $_POST['new_type'],
            $_POST['new_immatriculation'],
            $_POST['new_model'],
            $_POST['new_bv'],
            $_POST['new_carburant'],
            $_POST['new_description'] ?? null,
            $_POST['new_annee'],
            $_POST['new_date_circulation'],
            $_POST['new_marque_v'],
            $_POST['new_kilometrage'],
            $_POST['new_prix_v'] ?? null,
            $_POST['new_prix_j'] ?? null,
            $_POST['new_tva_v'],
            $token
        ]);

        // 2. Récupère l'ID de la prestation ajoutée
        $id_prestation = $db->lastInsertId();

        // 3. Traiter les images
        if (!empty($_FILES['photos']['name'][0])) {
            $total = count($_FILES['photos']['name']);
            for ($i = 0; $i < $total; $i++) {
                $tmp_name = $_FILES['photos']['tmp_name'][$i];
                $original_name = $_FILES['photos']['name'][$i];
                $extension = pathinfo($original_name, PATHINFO_EXTENSION); // extrait l'extension
                $unique_name = time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;

                $target_dir = "uploads/prestations/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                $target_file = $target_dir . $unique_name;

                $check = getimagesize($tmp_name);
                if ($check !== false) {
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        // Insère le chemin dans la table des images liées à la prestation
                        $stmt_img = $db->prepare("INSERT INTO photos_prestations_vehicule (id_prestation, chemin, numero_pro) VALUES (?, ?, ?)");
                        $stmt_img->execute([$id_prestation, $target_file, $numero_pro]);
                    }
                }
            }
        }




    } elseif (isset($_POST['edit_id_v'])) {
        $edit_id = (int) $_POST['edit_id_v'];
                $prix_jz = isset($_POST['prix_v']) && $_POST['prix_v'] !== ''
            ? (float) $_POST['prix_v']
            : null;
        $stmt = $db->prepare("UPDATE prestations_vehicule SET statut = ?, immatriculation = ?, model = ?, carburant = ?, description = ?, marque = ?, duree = ?, kilometrage = ?, prix = ?, prix_j = ?, tva = ? WHERE id = ? AND numero_pro = ?");
        $stmt->execute([
            $_POST['statut'],
            $_POST['immatriculation'],
            $_POST['model'],
            $_POST['carburant'],
            $_POST['bv'],
            $_POST['description'] ?? null,
            $_POST['marque_v'],
            $_POST['kilometrage'],
            $prix_jz,
            $_POST['prix_j'] ?? null,
            $_POST['tva_v'],
            $edit_id,
            $numero_pro
        ]);

    } elseif (isset($_POST['delete_id_v'])) {
        $delete_id = (int) $_POST['delete_id_v'];
        // Suppression véhicule
        $db->prepare("DELETE FROM prestations_vehicule WHERE id = ? AND numero_pro = ?")
            ->execute([$delete_id, $numero_pro]);
    }
    header('Location: prestation');
    exit();
}
// Récupération des prestations
// Exemple pour récupérer les photos du véhicule courant $p['id']
$tva_defaut = 8.5;
$prest = $db->prepare("SELECT * FROM prestations WHERE numero_pro = ?");
$prest->execute([$numero_pro]);
$prestations = $prest->fetchAll(PDO::FETCH_ASSOC);
$prest_v = $db->prepare("SELECT * FROM prestations_vehicule WHERE numero_pro = ?");
$prest_v->execute([$numero_pro]);
$prestations_v = $prest_v->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF‑8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
    <link rel="manifest" href="img/site.webmanifest">
    <title>Mes prestations</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js/minified/introjs.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener("DOMContentLoaded", function () {
            const menuBtn = document.getElementById("menuBtn");
            menuBtn.addEventListener("click", toggleMenu);
        });

        function toggleMenu() {
            // Exemple de logique
            const menu = document.getElementById("menu");
            if (menu) menu.classList.toggle("hidden");
        }
    </script>
    <script nonce="<?= $nonce ?>">
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('select.type').forEach(select => {
                const row = select.closest('tr');
                if (!row) return;

                const dureeInput = row.querySelector('.duree-input');
                const priceInput = row.querySelector('.price');
                const selectPrice = row.querySelector('.select_price');

                if (!dureeInput) return;

                const sync = () => {
                    if (select.value === 'forfait') {
                        dureeInput.style.display = 'none';
                        if (priceInput) priceInput.style.display = 'block';
                        if (selectPrice) selectPrice.style.display = 'none';
                        dureeInput.value = '';
						
                    } else {
                        dureeInput.style.display = 'block';
                        if (priceInput) priceInput.style.display = 'none';
                        if (selectPrice) selectPrice.style.display = 'block';
                    }
                };

                // Utiliser requestAnimationFrame pour s'assurer que le DOM est rendu
                requestAnimationFrame(sync);

                select.addEventListener('change', sync);
            });
        });



    </script>


</head>

<body class="bg-gray-100">
    <?php include('../includes/aside.php'); ?>

    <div class="flex-grow transition-all duration-300 ease-in-out p-4 md:ml-64">
        <h1 class="text-2xl font-bold mb-6 text-center">Mes prestations</h1>
        <?php if ($specialisation == "vendeur-auto" || $specialisation == "loueur"): ?>
            <button id="openModalBtn" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Ajouter un véhicule
            </button>
            <div id="vehiculeModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
                <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-4xl relative">
                    <button class="closeModalBtn absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl">
                        &times;
                    </button>

                    <h2 class="text-2xl font-semibold mb-4">Ajouter un véhicule</h2>

                    <form method="post" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="new_prest_v" value="1" />
                        <div>
                            <label for="new_type" class="block font-medium mb-1">Type</label>
                            <select id="new_type" name="new_type" class="border p-2 rounded w-full">
                                <option value="">Selectionner le type</option>
                                <option value="1">Location</option>
                                <option value="2">Vente</option>
                            </select>
                        </div>
                        <div>
                            <label for="photos" class="block font-medium mb-1">Photos du véhicule</label>
                            <input type="file" id="photos" name="photos[]" multiple accept="image/*"
                                class="border p-2 rounded w-full" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            <div>
                                <label for="new_model" class="block font-medium mb-1">Modèle</label>
                                <input type="text" id="new_model" name="new_model" required
                                    class="border p-2 rounded w-full" />
                            </div>

                            <div>
                                <label for="new_marque_v" class="block font-medium mb-1">Marque</label>
                                <input type="text" id="new_marque_v" name="new_marque_v"
                                    class="border p-2 rounded w-full" />
                            </div>
                            <div>
                                <label for="new_carburant" class="block font-medium mb-1">Type de carburant</label>
                                <select id="new_carburant" name="new_carburant" class="border p-2 rounded w-full" required>
                                    <option value="">Selectionner le type de carburant</option>
                                    <option value="Essence">Essence</option>
                                    <option value="Diesel">Diesel</option>
                                    <option value="Electrique">Électrique</option>
                                    <option value="Hybride">Hybride</option>

                                </select>
                            </div>
                            <div>
                                <label for="new_bv" class="block font-medium mb-1">Boite de vitesse</label>
                                <select id="new_bv" name="new_bv" class="border p-2 rounded w-full" required>
                                    <option value="">Selectionner la boite de vitesse</option>
                                    <option value="Automatique">Automatique</option>
                                    <option value="Manuelle">Manuelle</option>
                                </select>
                            </div>
                            <div>
                                <label for="new_immatriculation" class="block font-medium mb-1">Immatriculation (ex:
                                    AA-123-BC)</label>
                                <input type="text" id="new_immatriculation" name="new_immatriculation"
                                    class="border p-2 rounded w-full" required />
                            </div>

                            <div>
                                <label for="new_kilometrage" class="block font-medium mb-1">Kilométrage</label>
                                <input type="text" id="new_kilometrage" name="new_kilometrage"
                                    class="border p-2 rounded w-full" required />
                            </div>

                            <div>
                                <label for="new_date_circulation" class="block font-medium mb-1">Date de mise en
                                    circulation</label>
                                <input type="month" id="new_date_circulation" name="new_date_circulation"
                                    class="border p-2 rounded w-full" />
                            </div>

                            <div>
                                <label for="new_annee" class="block font-medium mb-1">Année (ex: 2025)</label>
                                <input type="number" id="new_annee" name="new_annee" min="1900" max="2100" step="1"
                                    class="border p-2 rounded w-full" />
                            </div>

                            <?php if ($specialisation == "vendeur-auto") { ?>
                                <div class="sm:col-span-3">
                                    <label for="new_description" class="block font-medium mb-1">Description du véhicule</label>
                                    <textarea id="new_description" name="new_description"
                                        class="border p-2 rounded w-full"></textarea>
                                </div>

                                <div>
                                    <label for="new_prix_v" class="block font-medium mb-1">Prix HT (€)</label>
                                    <input type="number" id="new_prix_v" step="0.01" name="new_prix_v" required
                                        class="border p-2 rounded w-full" />
                                </div>
                            <?php } else { ?>

                                <div>
                                    <label for="new_prix_v" class="block font-medium mb-1">Prix journalier HT (€)</label>
                                    <input type="number" id="new_prix_j" step="0.01" name="new_prix_j" required
                                        class="border p-2 rounded w-full" />
                                </div>
                            <?php } ?>

                            <div>
                                <label for="new_tva_v" class="block font-medium mb-1">TVA (%)</label>
                                <input type="number" id="new_tva_v" step="0.01" name="new_tva_v" required
                                    class="border p-2 rounded w-full" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                Ajouter
                            </button>
                        </div>
                    </form>

                </div>
            </div>
            <?php if (count($prestations_v)): ?>
                <div class="space-y-6">
                    <?php foreach ($prestations_v as $p): ?>
                        <?php
                        $stmt_photos = $db->prepare("SELECT id, chemin FROM photos_prestations_vehicule WHERE id_prestation = ? AND numero_pro = ?");
                        $stmt_photos->execute([$p['id'], $numero_pro]);
                        $photos = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);
                        $photos_json = htmlspecialchars(json_encode(array_column($photos, 'chemin')), ENT_QUOTES, 'UTF-8');
                        $first_photo = !empty($photos) ? $photos[0]['chemin'] : null;
                        ?>
                        <div class="vehicule-card flex items-center gap-6 p-4 bg-white border rounded-xl shadow hover:shadow-lg transition"
                            data-id="<?= htmlspecialchars($p['id']) ?>">

                            <?php if ($first_photo): ?>
                                <div class="md:w-1/4 w-full max-w-[200px] flex-shrink-0">
                                    <img src="<?= htmlspecialchars($first_photo) ?>" alt="Photo véhicule"
                                        class="w-full h-auto object-cover rounded-lg shadow-sm">
                                </div>
                            <?php endif; ?>

                            <div class="p-6 md:w-2/3 w-full flex flex-col justify-between">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800 mb-3">
                                        <?= htmlspecialchars($p['model']) ?> - <?= htmlspecialchars($p['marque']) ?>
                                    </h3>

                                    <div class="text-gray-700 space-y-1">
                                        <p><strong>Immatriculation :</strong> <?= htmlspecialchars($p['immatriculation']) ?></p>
                                        <p><strong>Kilométrage :</strong> <?= htmlspecialchars($p['kilometrage']) ?> km</p>
                                        <p><strong><?= $specialisation == "vendeur-auto" ? "Prix HT" : "Prix journalier HT" ?>
                                                :</strong>
                                            <?= htmlspecialchars($specialisation == "vendeur-auto" ? $p['prix'] : $p['prix_j']) ?> €
                                        </p>
                                        <p><strong>TVA :</strong> <?= htmlspecialchars($p['tva']) ?> %</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="vehiculeModal2_<?= htmlspecialchars($p['id']) ?>"
                            class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl p-8 relative max-h-[90vh] overflow-y-auto">
                                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Modifier ou supprimer le véhicule</h2>
                                <div id="modal_photos" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-6">
                                    <?php foreach ($photos as $index => $photo): ?>
                                        <div class="relative group rounded-lg overflow-hidden border shadow">
                                            <img src="<?= htmlspecialchars($photo['chemin']) ?>" alt="Photo véhicule" />
                                            <input type="hidden" name="photo_id[]" value="<?= htmlspecialchars($photo['id']) ?>">
                                            <div
                                                class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-4 rounded-lg">
                                                <form method="post" enctype="multipart/form-data"
                                                    class="flex items-center justify-center">
                                                    <input type="hidden" name="edit_id_photo" value="<?= htmlspecialchars($p['id']) ?>">
                                                    <input type="hidden" name="photo_id" value="<?= htmlspecialchars($photo['id']) ?>">

                                                    <input type="file" name="new_photo"
                                                        id="edit_photo_<?= htmlspecialchars($p['id']) ?>_<?= $index ?>" accept="image/*"
                                                        class="hidden submit-on-change">

                                                    <button type="button"
                                                        class="text-white text-3xl material-symbols-outlined hover:scale-110 transition edit-photo-btn"
                                                        data-target="edit_photo_<?= htmlspecialchars($p['id']) ?>_<?= $index ?>">
                                                        edit
                                                    </button>
                                                </form>

                                                <form method="post" class="flex items-center justify-center">
                                                    <input type="hidden" name="edit_id_p" value="<?= htmlspecialchars($p['id']) ?>">
                                                    <input type="hidden" name="photo_id" value="<?= htmlspecialchars($photo['id']) ?>">
                                                    <button type="submit" name="delete_photo"
                                                        class="text-white text-3xl material-symbols-outlined hover:scale-110 transition">
                                                        delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>


                                <form method="post" class="space-y-6" enctype="multipart/form-data">
                                    <input type="hidden" name="edit_id_v" value="<?= htmlspecialchars($p['id']) ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="modal_bv_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Disposibilité</label>
                                            <select id="modal_statut_<?= htmlspecialchars($p['id']) ?>" name="statut"
                                                class="border p-2 rounded w-full">
                                                <option value="">Sélectionner la disposibilité</option>
                                                <option value="Automatique" <?= ($p['statut'] ?? '') === 'actif' ? 'selected' : '' ?>>Disponible</option>
                                                <option value="Manuelle" <?= ($p['boite'] ?? '') === 'inactif' ? 'selected' : '' ?>>
                                                    Indisponible</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="modal_model_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Modèle</label>
                                            <input type="text" name="model" id="modal_model_<?= htmlspecialchars($p['id']) ?>"
                                                placeholder="Modèle" value="<?= htmlspecialchars($p['model']) ?>"
                                                class="border border-gray-300 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                        </div>

                                        <div>
                                            <label for="modal_marque_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Marque</label>
                                            <input type="text" name="marque_v" id="modal_marque_<?= htmlspecialchars($p['id']) ?>"
                                                placeholder="Marque" value="<?= htmlspecialchars($p['marque']) ?>"
                                                class="border border-gray-300 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                        </div>

                                        <div>
                                            <label for="modal_immatriculation_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Immatriculation</label>
                                            <input type="text" name="immatriculation"
                                                id="modal_immatriculation_<?= htmlspecialchars($p['id']) ?>"
                                                value="<?= htmlspecialchars($p['immatriculation']) ?>" placeholder="Immatriculation"
                                                class="border border-gray-300 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                        </div>
                                        <div>
                                            <label for="modal_bv_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Boîte de vitesse</label>
                                            <select id="modal_bv_<?= htmlspecialchars($p['id']) ?>" name="bv"
                                                class="border p-2 rounded w-full">
                                                <option value="">Sélectionner la boîte de vitesse</option>
                                                <option value="Automatique" <?= ($p['boite'] ?? '') === 'Automatique' ? 'selected' : '' ?>>Automatique</option>
                                                <option value="Manuelle" <?= ($p['boite'] ?? '') === 'Manuelle' ? 'selected' : '' ?>>
                                                    Manuelle</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="modal_carburant_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Type de carburant</label>
                                            <select id="modal_carburant_<?= htmlspecialchars($p['id']) ?>" name="carburant"
                                                class="border p-2 rounded w-full">
                                                <option value="">Sélectionner le type de carburant</option>
                                                <option value="Essence" <?= ($p['carburant'] ?? '') === 'Essence' ? 'selected' : '' ?>>
                                                    Essence</option>
                                                <option value="Diesel" <?= ($p['carburant'] ?? '') === 'Diesel' ? 'selected' : '' ?>>
                                                    Diesel</option>
                                                <option value="Electrique" <?= ($p['carburant'] ?? '') === 'Electrique' ? 'selected' : '' ?>>Électrique</option>
                                                <option value="Hybride" <?= ($p['carburant'] ?? '') === 'Hybride' ? 'selected' : '' ?>>
                                                    Hybride</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="modal_kilometrage_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Kilométrage</label>
                                            <input type="number" name="kilometrage"
                                                id="modal_kilometrage_<?= htmlspecialchars($p['id']) ?>" placeholder="Kilométrage"
                                                value="<?= htmlspecialchars($p['kilometrage']) ?>"
                                                class="border border-gray-300 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                        </div>

                                        <div class="md:col-span-2">
                                            <label for="modal_description_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                            <textarea name="description" id="modal_description_<?= htmlspecialchars($p['id']) ?>"
                                                placeholder="Description"
                                                class="border border-gray-300 p-3 rounded-lg w-full min-h-[100px] focus:ring-2 focus:ring-blue-500 focus:outline-none"><?= htmlspecialchars($p['description']) ?></textarea>
                                        </div>
                                            <?php if ($p['type_presta'] == "0") : ?>
                                        <div>
                                            <label for="modal_prix_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Prix HT (€)</label>
                                            <input type="number" step="0.01" name="prix_v"
                                                id="modal_prix_<?= htmlspecialchars($p['id']) ?>" placeholder="Prix HT (€)"
                                                value="<?= htmlspecialchars($p['prix']) ?>"
                                                class="border border-gray-300 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                        </div>
                                                <?php else: ?>
                                          <div>
                                            <label for="modal_prix_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">Prix HT (€)</label>
                                            <input type="number" step="0.01" name="prix_j"
                                                id="modal_prix_<?= htmlspecialchars($p['id']) ?>" placeholder="Prix HT (€)"
                                                value="<?= htmlspecialchars($p['prix_j']) ?>"
                                                class="border border-gray-300 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                        </div>
                                            <?php endif; ?>
                                        <div>
                                            <label for="modal_tva_<?= htmlspecialchars($p['id']) ?>"
                                                class="block text-sm font-medium text-gray-700 mb-1">TVA (%)</label>
                                            <input type="number" step="0.1" name="tva_v"
                                                id="modal_tva_<?= htmlspecialchars($p['id']) ?>" placeholder="TVA (%)"
                                                value="<?= htmlspecialchars($p['tva']) ?>"
                                                class="border border-gray-300 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                                        </div>
                                    </div>

                                    <div class="flex justify-between mt-8">
                                        <button type="submit"
                                            class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-lg transition">
                                            Enregistrer
                                        </button>
                                    </div>
                                </form>
                                <form method="post" class="mt-6">
                                    <input type="hidden" name="delete_id_v" value="<?= htmlspecialchars($p['id']) ?>">
                                    <button type="submit"
                                        class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-3 rounded-lg transition"
                                        id="modal_delete_btn">
                                        Supprimer le véhicule
                                    </button>
                                </form>
                                <button type="button"
                                    class="closeModalBtn absolute top-4 right-6 text-3xl text-gray-500 hover:text-black font-bold focus:outline-none">
                                    &times;
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <p class="text-gray-600 text-center">Aucun véhicule enregistré.</p>
            <?php endif; ?>
            <script nonce="<?= htmlspecialchars($nonce) ?>">
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.vehicule-card').forEach(card => {
                        card.addEventListener('click', () => {
                            const id = card.getAttribute('data-id');
                            const modal = document.getElementById('vehiculeModal2_' + id);

                            if (modal) {
                                modal.classList.remove('hidden');

                                const closeBtn = modal.querySelector('.closeModalBtn');
                                if (closeBtn) {
                                    closeBtn.addEventListener('click', () => {
                                        modal.classList.add('hidden');
                                    });
                                }

                                modal.addEventListener('click', (e) => {
                                    if (e.target === modal) {
                                        modal.classList.add('hidden');
                                    }
                                });
                            }
                        });
                    });
                    document.querySelectorAll('.edit-photo-btn').forEach(button => {
                        button.addEventListener('click', () => {
                            const targetId = button.getAttribute('data-target');
                            const fileInput = document.getElementById(targetId);
                            if (fileInput) {
                                fileInput.click();
                            }
                        });
                    });
                    document.querySelectorAll('.submit-on-change').forEach(input => {
                        input.addEventListener('change', () => {
                            input.closest('form').submit();
                        });
                    });
                });
            </script>






        <?php else: ?>
            <!-- Formulaire pour ajouter une prestation standard -->
            <!-- Bouton pour ouvrir le modal -->
            <button id="openModal" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                Ajouter une prestation
            </button>

            <!-- Modal -->
            <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl p-6 relative">

                    <button id="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">
                        ✕
                    </button>

                    <h2 class="text-2xl font-semibold mb-4">Ajouter une prestation</h2>
                    <form method="post" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">

                            <div class="flex flex-col">
                                <label for="new_forfait" class="text-gray-700 font-medium mb-1">
                                    Type <span class="text-sm text-gray-500">(facultatif)</span>
                                </label>
                                <select id="new_forfait" name="new_forfait" class="border p-2 rounded w-full">
                                    <option value="">Sélectionner</option>
                                    <option value="forfait">Forfait</option>
                                    <option value="standard">Prestation standard</option>
                                </select>
                            </div>

                            <div class="flex flex-col">
                                <label for="new_nom" class="text-gray-700 font-medium mb-1">Nom *</label>
                                <input type="text" id="new_nom" name="new_nom" placeholder="Nom" required
                                    class="border p-2 rounded w-full" />
                            </div>
                            <div class="flex flex-col">
                                <label for="new_ref" class="text-gray-700 font-medium mb-1">Référence</label>
                                <input type="text" id="new_ref" name="new_ref" placeholder="Référence"
                                    class="border p-2 rounded w-full" />
                            </div>
                            <div class="flex flex-col" id="dureeContainer">
                                <label for="new_duree" class="text-gray-700 font-medium mb-1">Durée *</label>
                                <input type="number" id="new_duree" name="new_duree" placeholder="Durée (ex. 1h)" required
                                    class="border p-2 rounded w-full" />
                            </div>
                            <div class="flex flex-col">
                                <label for="new_prix" class="text-gray-700 font-medium mb-1">Prix ht (€) *</label>
                                <input type="number" step="0.01" id="new_prix" name="new_prix" placeholder="Prix ht (€)"
                                    required class="border p-2 rounded w-full" />
                            </div>
                            <div class="flex flex-col">
                                <label for="new_tva" class="text-gray-700 font-medium mb-1">TVA (%) *</label>
                                <input type="number" step="0.01" id="new_tva" name="new_tva" placeholder="TVA (%)" required
                                    class="border p-2 rounded w-full" />
                            </div>
                        </div>
                        <button type="submit" name="new_prest"
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            Ajouter
                        </button>
                    </form>
                </div>
            </div>
            <script>
                const modal = document.getElementById('modal');
                const openModalBtn = document.getElementById('openModal');
                const closeModalBtn = document.getElementById('closeModal');

                openModalBtn.addEventListener('click', () => {
                    modal.classList.remove('hidden');
                });

                closeModalBtn.addEventListener('click', () => {
                    modal.classList.add('hidden');
                });

                // fermer le modal si clic en dehors
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                    }
                });
                // Récupère les éléments
                const typeSelect = document.getElementById('new_forfait');
                const dureeContainer = document.getElementById('dureeContainer');

                // Fonction pour afficher/cacher Durée
                function toggleDuree() {
                    if (typeSelect.value === 'forfait') {
                        dureeContainer.style.display = 'none';
                        document.getElementById('new_duree').required = false;
                    } else {
                        dureeContainer.style.display = 'flex';
                        document.getElementById('new_duree').required = true;
                    }
                }

                // Événement au changement
                typeSelect.addEventListener('change', toggleDuree);

                // Initialiser au chargement
                document.addEventListener('DOMContentLoaded', toggleDuree);
            </script>

            <!-- Liste des prestations standards -->
            <?php if (count($prestations)): ?>
                <div id="edit" class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-200">
                    <table class="min-w-full table-auto text-sm">
                        <thead
                            class="bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="p-4 text-left">Type</th>
                                <th class="p-4 text-left">Nom</th>
                                <th class="p-4 text-left">Référence</th>
                                <th class="p-4 text-left">Durée</th>
                                <th class="p-4 text-left">Prix HT (€)</th>
                                <th class="p-4 text-left">TVA (%)</th>
                                <th class="p-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($prestations as $p): ?>
                                <?php $rese = "SELECT * FROM entreprises WHERE numero_pro = " . $db->quote($numero_pro);
                                $data_pro = $db->query($rese)->fetch(PDO::FETCH_ASSOC); ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <form method="post" class="flex items-center gap-2">
                                        <td class="p-3 w-1/4">
                                            <select name="type"
                                                class="type w-full border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 rounded-lg p-2">
                                                <option value="forfait" <?= $p['duree'] === null ? 'selected' : '' ?>>Forfait</option>
                                                <option value="standard" <?= $p['duree'] !== null ? 'selected' : '' ?>>Prestation
                                                    standard</option>
                                            </select>
                                        </td>
                                        <td class="p-3 w-1/6">
                                            <input type="hidden" name="edit_id" value="<?= $p['id'] ?>">
                                            <input type="text" name="nom" value="<?= htmlspecialchars($p['nom']) ?>"
                                                class="w-full border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 rounded-lg p-2" />
                                        </td>
                                        <td class="p-3 w-1/6">
                                            <input type="text" name="ref" value="<?= htmlspecialchars($p['ref']) ?>"
                                                placeholder="Facultatif"
                                                class="w-full border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 rounded-lg p-2" />
                                        </td>
                                        <td class="p-3 w-1/6">

                                            <input type="text" name="duree" value="<?= htmlspecialchars($p['duree'] ?? '') ?>"
                                                class="duree-input w-full border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 rounded-lg p-2"
                                                style="display: <?= ($p['duree'] === null ? 'none' : 'block') ?>;">


                                        </td>
                                        <td class="p-3 w-1/6">
    <!-- Affichage du prix forfaitaire si la prestation est de type 'forfait' -->
    <input type="number" step="0.01" name="prix_f" value="<?= htmlspecialchars($p['prix']) ?>"
        class="price w-full border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 rounded-lg p-2"
        style="display: <?= ($p['duree'] === null ? 'block' : 'none') ?>;" />

    <!-- Affichage du select pour les taux horaires si la prestation est de type 'standard' -->
    <select name="prix_p"
        class="select_price w-full border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 rounded-lg p-2"
        style="display: <?= ($p['duree'] !== null ? 'block' : 'none') ?>;">
        <option value="<?= htmlspecialchars($data_pro['taux_horaire']) ?>"
            <?= $p['prix'] == $data_pro['taux_horaire'] ? 'selected' : '' ?>>
            Taux horaire M1
        </option>
        <option value="<?= htmlspecialchars($data_pro['taux_horaire2']) ?>"
            <?= $p['prix'] == $data_pro['taux_horaire2'] ? 'selected' : '' ?>>
            Taux horaire M2
        </option>
        <option value="<?= htmlspecialchars($data_pro['taux_horaire3']) ?>"
            <?= $p['prix'] == $data_pro['taux_horaire3'] ? 'selected' : '' ?>>
            Taux horaire M3
        </option>
    </select>
</td>

                                        <td class="p-3 w-1/6">
                                            <input type="number" step="0.1" name="tva"
                                                value="<?= isset($p['tva']) ? htmlspecialchars($p['tva']) : $tva_defaut ?>"
                                                class="w-full border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 rounded-lg p-2" />
                                        </td>
                                        <td class="p-3 w-1/8 text-center">
                                            <div class="flex justify-center gap-2">
                                                <button type="submit"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg shadow-sm transition flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-sm">check</span>
                                                    <span class="hidden sm:inline"></span>
                                                </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                                        <button type="submit"
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg shadow-sm transition flex items-center gap-1"
                                            onclick="return confirm('Supprimer cette prestation ?');">
                                            <span class="material-symbols-outlined text-sm">delete</span>
                                            <span class="hidden sm:inline"></span>
                                        </button>
                                    </form>
                    </div>
                    </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            </div>

        <?php else: ?>
            <p class="text-gray-600 text-center">Aucune prestation enregistrée.</p>
        <?php endif; ?>
    <?php endif; ?>

    </div>

    <script nonce="<?= $nonce ?>">
        document.addEventListener('DOMContentLoaded', function () {
            // Gestion openModalBtn
            const openModalBtn = document.getElementById('openModalBtn');
            if (openModalBtn) {
                openModalBtn.addEventListener('click', function () {
                    const vehiculeModal = document.getElementById('vehiculeModal');
                    if (vehiculeModal) vehiculeModal.classList.remove('hidden');
                });
            }

            // Gestion closeModalBtn dans vehiculeModal
            const vehiculeModal = document.getElementById('vehiculeModal');
            if (vehiculeModal) {
                const closeModalBtn = vehiculeModal.querySelector('.closeModalBtn');
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', () => vehiculeModal.classList.add('hidden'));
                }
            }

            // Votre code sync pour les selects .type
            document.querySelectorAll('.type').forEach(select => {
                const row = select.closest('tr');
                if (!row) return;

                const dureeInput = row.querySelector('.duree-input');
                const priceInput = row.querySelector('.price');
                const selectPrice = row.querySelector('.select_price');

                if (!dureeInput) return;

               const sync = () => {
    // On vérifie si le type de prestation est 'forfait' ou 'standard'
    if (select.value === 'forfait') {
        // Affiche le champ de prix forfaitaire et cache le select des taux horaires
        dureeInput.style.display = 'none';
        if (priceInput) priceInput.style.display = 'block';
        if (selectPrice) selectPrice.style.display = 'none';
        dureeInput.value = ''; // Si c'est forfait, on vide la durée
    } else {
        // Affiche le champ de durée et le select des taux horaires pour une prestation standard
        dureeInput.style.display = 'block';
        if (priceInput) priceInput.style.display = 'none';
        if (selectPrice) selectPrice.style.display = 'block';
        
        // Si un taux horaire est sélectionné, on met à jour le prix
        const selectedPrice = selectPrice.value;
        if (selectedPrice) {
            priceInput.value = selectedPrice; // Met à jour le champ prix avec le taux horaire
        }
    }
};



                sync();
                select.addEventListener('change', sync);
            });
        });

</script>
    <!-- JS pour sidebar responsive -->
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        function toggleMenu() {
            document.getElementById("mobileMenu").classList.toggle("hidden");
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/intro.js/minified/intro.min.js"></script>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        const profilPro =
            0;

        function startTutorial() {

            const intro = introJs();
            intro.setOptions({
                steps: [{
                    intro: "Bienvenue dans votre tableau de bord professionnel. Voici un rapide tour d'horizon."
                },
                {
                    element: '#prestation',
                    intro: "Déclarez vos prestations en indiquant la durée, le tarif HT et le taux de TVA correspondant."
                },
                {
                    element: '#edit',
                    intro: "Modifiez les prestations que vous proposez à vos clients."
                }
                ],
                showProgress: true,
                exitOnOverlayClick: false,
                showStepNumbers: true,
                disableInteraction: true,
                doneLabel: "Terminer",
                nextLabel: "Suivant",
                prevLabel: "Précédent",

            });
            intro.start();
        }

        if (profilPro === 1) {
            ;
            startTutorial();
        }
    </script>
</body>

</html>