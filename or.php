<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'Segoe UI');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Ordre de Réparation</title>
  <style>
    :root {
      --primary: #2c3e50;
      --accent: #3498db;
      --light: #ecf0f1;
      --gray: #bdc3c7;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 20px;
      background-color: #fff;
      color: var(--primary);
    }

    .container {
      width: 100%;
      max-width: 800px;
      margin: auto;
      padding: 20px;
    }

    h1 {
      text-align: center;
      color: var(--accent);
      margin-bottom: 20px;
      font-size: 22px;
    }

    .section {
      margin-bottom: 20px;
    }

    .section-title {
      font-weight: bold;
      color: var(--accent);
      font-size: 14px;
      margin-bottom: 8px;
      border-left: 4px solid var(--accent);
      padding-left: 8px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10px;
      font-size: 12px;
    }

    table td, table th {
      border: 1px solid var(--gray);
      padding: 10px;
    }

    table th {
      background-color: var(--light);
    }

    .box {
      border: 1px dashed var(--gray);
      padding: 10px;
      min-height: 80px;
      background-color: #f9f9f9;
    }

    .drawing-box {
      text-align: center;
      padding: 10px;
      background: #fdfdfd;
      border: 1px dashed var(--gray);
    }

    .drawing-box img {
      max-width: 250px;
      opacity: 0.8;
    }

    .signature-section {
      display: inline-block;
      margin-top: 30px;
    }

    .signature-box {
      display: inline-block;
      margin-left: 50px;
      text-align: center;
      border-top: 1px solid var(--gray);
      padding-top: 10px;
      font-size: 14px;
      color: #555;
    }
  </style>
</head>
<body>

<div class="container">
  <h1>Ordre de Réparation</h1>

  <div class="section">
    <div class="section-title">Informations Client</div>
    <table>
      <tr>
        <td><strong>Nom / Prénom</strong></td>
        <td><strong>Adresse</strong></td>
      </tr>
      <tr>
        <td></td>
        <td></td>
      </tr>
      <tr>
        <td><strong>Téléphone</strong></td>
        <td><strong>Email</strong></td>
      </tr>
      <tr>
        <td></td>
        <td></td>
      </tr>
    </table>
  </div>

  <div class="section">
    <div class="section-title">Informations Véhicule</div>
    <table>
      <tr>
        <th>Marque</th>
        <th>Modèle</th>
        <th>Immatriculation</th>
        <th>Kilométrage</th>
      </tr>
      <tr>
        <td></td><td></td><td></td><td></td>
      </tr>
    </table>
  </div>

  <div class="section">
    <div class="section-title">Travaux demandés</div>
    <div class="box"></div>
  </div>

  <div class="section">
    <div class="section-title">Constat état du véhicule (carrosserie / chocs)</div>
    <div class="drawing-box">
      <p style="font-style: italic; margin-bottom: 10px;">Veuillez annoter les zones endommagées :</p>
      <img src="https://automoclick.com/asset/style/img/ordre%20de%20reparation.png" alt="Schéma voiture" />
    </div>
  </div>

  <div class="section">
    <div class="section-title">Remarques diverses</div>
    <div class="box"></div>
  </div>

  <div class="signature-section">
    <div class="signature-box">Signature du client</div>
    <div class="signature-box">Signature du responsable atelier</div>
  </div>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('ordre_reparation.pdf', ['Attachment' => false]);
exit;
