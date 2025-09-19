<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Merci pour votre paiement</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-r from-green-400 to-green-800 min-h-screen flex items-center justify-center">

    <div class="bg-white shadow-2xl rounded-3xl p-10 max-w-lg w-full text-center">
        <div class="mb-6">
            <!-- Icône de succès -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-green-500" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12l2 2l4-4M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10s-4.477 10-10 10z" />
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-2">Merci !</h1>
        <p class="text-gray-600 mb-6">Votre paiement a été effectué avec succès. 🟢</p>

        <a href="/"
            class="inline-block bg-green-500 hover:bg-green-600 text-white font-semibold px-6 py-3 rounded-full shadow-md transition duration-300">
            Retour à l'accueil
        </a>

        <div class="mt-8 text-gray-500 text-sm">
            <p>Vous allez recevoir un e-mail de confirmation sous peu.</p>
            <p>Si vous avez des questions, contactez-nous à <a href="mailto:support@automoclick.com"
                    class="text-blue-500 underline">support@automoclick.com</a>.</p>
        </div>
    </div>

</body>

</html>
