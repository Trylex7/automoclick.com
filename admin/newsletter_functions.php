<?php
// newsletter_functions.php


/**
* Récupère la liste unique des placeholders {{cle}} présents dans un HTML.
*/
function nf_get_placeholders(string $html): array {
$placeholders = [];
if (preg_match_all('/\{\{\s*([a-zA-Z0-9_\-\.]+)\s*\}\}/', $html, $m)) {
$placeholders = array_values(array_unique($m[1]));
}
return $placeholders;
}


/**
* Rend le HTML final en remplaçant les placeholders par les valeurs fournies.
* Les clés manquantes sont remplacées par une chaîne vide.
*/
function nf_render_html(string $html, ?string $styles, array $vars): string {
$replacements = [];
foreach (nf_get_placeholders($html) as $key) {
$val = $vars[$key] ?? '';
// Sécuriser minimalement, mais on suppose HTML voulu dans certains champs
// Si tu veux forcer l'échappement HTML, dé-commente la ligne suivante :
// $val = htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$replacements['{{' . $key . '}}'] = $val;
$replacements['{{ ' . $key . ' }}'] = $val; // variante avec espaces
}
$final = strtr($html, $replacements);


if (!empty($styles)) {
$final = "<style>" . $styles . "</style>\n" . $final;
}
return $final;
}


/**
* Charge un template + variables d'une campagne et produit le HTML final.
*/
function nf_render_by_content_id(PDO $db, int $contentId): ?array {
$stmt = $db->prepare("SELECT c.id, c.nom_campagne, c.variables_json, t.nom AS template_nom, t.contenu, t.styles
FROM newsletter_contenus c
JOIN newsletter_templates t ON t.id = c.template_id
WHERE c.id = ?");
$stmt->execute([$contentId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) return null;


$vars = json_decode($row['variables_json'] ?? '{}', true) ?: [];
$html = nf_render_html($row['contenu'], $row['styles'], $vars);


return [
'meta' => $row,
'html' => $html,
'vars' => $vars,
];
}


/**
* Envoi d'un email HTML simple. Utilise `mail()` par défaut.
* Tu peux intégrer PHPMailer ici si tu l'utilises déjà.
*/
