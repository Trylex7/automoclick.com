<?php
require_once '../header.php';
session_start();
require '../db/dbconnect2.php';
require '../vendor/autoload.php';

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
                $_POST['prix'],
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
                echo json_encode(['success' => true, 'prestations' => $prestations]);
        exit;

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
        $stmt = $db->prepare("INSERT INTO prestations_vehicule (numero_pro, type_presta, immatriculation, model, boite, carburant, description, model_annee, date_circulation, marque, duree, kilometrage, prix, prix_j, tva, token, date_c ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $numero_pro,
            $_POST['new_type'],
            $_POST['new_immatriculation'],
            $_POST['new_model'],
            $_POST['new_bv'],
            $_POST['new_carburant'],
            $_POST['new_description'],
            $_POST['new_annee'],
            $_POST['new_date_circulation'],
            $_POST['new_marque_v'],
            $_POST['new_duree_v'] ?? null,
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

        $stmt = $db->prepare("UPDATE prestations_vehicule SET immatriculation = ?, model = ?, carburant = ?, boite = ?, description = ?, marque = ?, duree = ?, kilometrage = ?, prix = ?, prix_j = ?, tva = ? WHERE id = ? AND numero_pro = ?");
        $stmt->execute([
            $_POST['immatriculation'],
            $_POST['model'],
            $_POST['carburant'],
            $_POST['bv'],
            $_POST['description'] ?? null,
            $_POST['marque_v'],
            $_POST['duree_v'] ?? null,
            $_POST['kilometrage'],
            $_POST['prix_v'] ?? null,
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