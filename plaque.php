<?php
// Clé API PlateRecognizer
$apiToken = '4a8b70f4ce26e22457b0330ff93f0e461d032be1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    // Vérifie qu'il y a bien un fichier uploadé sans erreur
    if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileType = $_FILES['image']['type'];

        $cfile = new CURLFile($tmpName, $fileType, $fileName);

        $data = [
            'upload' => $cfile,
            'vehicule' => 'false',
            'regions' => 'fr', 
        ];

        $ch = curl_init('https://api.platerecognizer.com/v1/plate-reader/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Token $apiToken"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Erreur cURL : ' . curl_error($ch);
        }
        curl_close($ch);

        $result = json_decode($response, true);

        echo "<h3>Résultat de la reconnaissance :</h3><pre>";
        print_r($result);
        echo "</pre>";

        if (!empty($result['results'][0]['plate'])) {
            echo "<p><strong>Plaque détectée :</strong> " . strtoupper($result['results'][0]['plate']) . "</p>";
        } else {
            echo "<p>Aucune plaque détectée.</p>";
        }
    } else {
        echo "Erreur lors de l'upload de l'image.";
    }
}
?>

<form method="post" enctype="multipart/form-data">
    <label>Choisissez une image de plaque :</label><br>
    <input type="file" name="image" accept="image/*" required><br><br>
    <button type="submit">Envoyer à PlateRecognizer</button>
</form>