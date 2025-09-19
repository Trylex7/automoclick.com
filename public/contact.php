<?php
session_start();
require_once '../api/traker.php';
$message_envoye = false;
$erreur = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nom = htmlspecialchars(trim($_POST['nom']));
    $email = htmlspecialchars(trim($_POST['email']));
    $objet = htmlspecialchars(trim($_POST['objet']));
    $message = htmlspecialchars(trim($_POST['message']));

    if (!empty($nom) && !empty($email) && !empty($objet) && !empty($message)) {
        $to = "support@automoclick.com"; // Adresse email de réception
        $subject = "[Contact Automoclick] " . $objet;
        $body = "Nom : $nom\nEmail : $email\n\nMessage :\n$message";
        $headers = "From: $email\r\nReply-To: $email";

        if (mail($to, $subject, $body, $headers)) {
            $message_envoye = true;
        } else {
            $erreur = "Une erreur est survenue lors de l'envoi. Veuillez réessayer plus tard.";
        }
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact - Automoclick</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
</head>
 <?php include ('../includes/dropdown.php'); ?>
<body class="bg-gray-100">

<section class="max-w-4xl mx-auto p-8 bg-gradient-to-r from-white via-gray-50 to-white shadow-lg rounded-xl my-12">
  <h1 class="text-4xl font-extrabold text-gray-800 mb-6 text-center">Contactez Automoclick</h1>
  <p class="mb-8 text-center text-gray-600">Une question, un problème ou une suggestion ? Remplissez le formulaire ci-dessous et nous vous répondrons rapidement.</p>

  <?php if($message_envoye): ?>
      <div class="mb-6 p-4 bg-green-100 text-green-800 border border-green-200 rounded text-center">
          Votre message a été envoyé avec succès !
      </div>
  <?php elseif($erreur): ?>
      <div class="mb-6 p-4 bg-red-100 text-red-800 border border-red-200 rounded text-center">
          <?= $erreur ?>
      </div>
  <?php endif; ?>

  <div class="grid md:grid-cols-2 gap-10">
    <!-- Formulaire -->
    <form class="space-y-5" action="" method="POST">
      <input class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" type="text" name="nom" placeholder="Nom complet" required>
      <input class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" type="email" name="email" placeholder="Email" required>
      <input class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" type="text" name="objet" placeholder="Objet" required>
      <textarea class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" name="message" rows="6" placeholder="Votre message" required></textarea>
      <button type="submit" class="w-full bg-green-600 text-white font-bold py-3 rounded-lg hover:bg-green-700 transition">Envoyer le message</button>
    </form>

    <!-- Réseaux sociaux -->
    <div class="flex flex-col justify-center items-center md:items-start space-y-6">
      <h2 class="text-2xl font-semibold text-gray-800 mb-2">Suivez-nous</h2>
      <div class="flex gap-6 text-xl">
        <a href="#" class="text-blue-600 hover:text-blue-800 transition">Facebook</a>
        <a href="#" class="text-blue-400 hover:text-blue-600 transition">Twitter</a>
        <a href="#" class="text-pink-500 hover:text-pink-700 transition">Instagram</a>
      </div>
    </div>
  </div>
</section>
<?php include('../includes/footer.php'); ?>
</body>
</html>
