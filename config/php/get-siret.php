<?php
header('Content-Type: application/json');

if (!isset($_GET['siret'])) {
    http_response_code(400);
    echo json_encode(['error' => 'SIRET manquant']);
    exit;
}

$siret = preg_replace('/\D/', '', $_GET['siret']);
$apiUrl = "https://data.siren-api.fr/v3/etablissements/$siret";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Client-Secret: 5tIbySnbBQqQFlvRFkp2sKjEBaSnblCC"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
?>