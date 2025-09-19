<?php 
require '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;
$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Conditions Générales d’Utilisation - Professionnels</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.5; }
    h1, h2 { color: #2F4F4F; }
    h1 { font-size: 18px; margin-bottom: 10px; }
    h2 { font-size: 14px; margin-top: 20px; }
    p, ul { margin: 8px 0; }
    ul { padding-left: 20px; }
    strong { color: #000; }
</style>
</head>
<body>
<h1>Conditions Générales d’Utilisation (CGU) – Professionnels</h1>

<p>Les présentes Conditions Générales d’Utilisation ont pour objet de définir les modalités et conditions dans lesquelles les professionnels utilisent la plateforme <strong>Automoclick</strong>.</p>

<h2>ARTICLE 1 : OBJET</h2>
<p>La plateforme permet aux professionnels de proposer des prestations de services, de gérer leurs rendez-vous, leurs paiements et leur relation client via un espace dédié.</p>

<h2>ARTICLE 2 : ACCÈS À LA PLATEFORME</h2>
<p>Tout professionnel doit créer un compte pour accéder aux services de la plateforme. L’inscription implique l’acceptation des présentes CGU sans réserve.</p>

<h2>ARTICLE 3 : OBLIGATIONS DU PROFESSIONNEL</h2>
<ul>
    <li>Fournir des informations exactes, à jour et complètes lors de l’inscription.</li>
    <li>Mettre à jour régulièrement ses horaires, tarifs, prestations et disponibilités.</li>
    <li>Respecter les rendez-vous réservés et assurer les prestations proposées.</li>
    <li>Respecter les lois et règlements applicables à son activité.</li>
    <li>Traiter les clients avec professionnalisme, respect et confidentialité.</li>
</ul>

<h2>ARTICLE 4 : CONDITIONS FINANCIÈRES</h2>
<p>Le professionnel reçoit les paiements des clients après des frais de la plateforme. Les conditions financières peuvent être détaillées dans un contrat séparé ou dans l’espace pro.</p>

<h2>ARTICLE 5 : POLITIQUE D’ANNULATION ET DE MODIFICATION</h2>
<p>Les rendez-vous réservés par les clients sont <strong>non remboursables</strong>. Le professionnel peut proposer un report dans la limite de ses disponibilités.</p>

<h2>ARTICLE 6 : RESPONSABILITÉ</h2>
<p>Le professionnel est seul responsable de la qualité et de l’exécution des prestations. La plateforme ne saurait être tenue responsable en cas de litige entre professionnel et client.</p>

<h2>ARTICLE 7 : PROPRIÉTÉ INTELLECTUELLE</h2>
<p>Le professionnel autorise la plateforme à utiliser ses informations publiques (nom, logo, description, etc.) à des fins de référencement ou de promotion sur la plateforme.</p>

<h2>ARTICLE 8 : SUSPENSION ET RÉSILIATION</h2>
<p>En cas de manquement grave (fraude, prestations non honorées, propos inappropriés…), la plateforme se réserve le droit de suspendre ou supprimer le compte professionnel, sans préavis.</p>

<h2>ARTICLE 9 : DONNÉES PERSONNELLES</h2>
<p>Les données personnelles collectées sont utilisées exclusivement pour la gestion du compte professionnel et ne sont jamais revendues. Le professionnel dispose d’un droit d’accès, de rectification et de suppression de ses données.</p>

<h2>ARTICLE 10 : MODIFICATION DES CGU</h2>
<p>Les présentes CGU peuvent être modifiées à tout moment. En cas de modification, les professionnels seront notifiés par e-mail ou via leur espace personnel.</p>

<h2>ARTICLE 11 : LOI APPLICABLE ET LITIGES</h2>
<p>Les présentes CGU sont soumises à la loi française. En cas de litige, une solution amiable sera recherchée. À défaut, les tribunaux compétents seront saisis.</p>

<p><em>Fait le ' . date('d/m/Y') . '</em></p>
</body>
</html>';


$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Pour forcer le téléchargement
$dompdf->stream('CGU_Professionnels_' . date('Ymd') . '.pdf', ['Attachment' => true]);