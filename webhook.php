<?php
if (!isset($_SESSION['id_admin'])) {
    header('Location: connexion');
    exit;
}
$logFile = __DIR__ . "/logs.json";

// Charger les logs existants
$logs = [];
if (file_exists($logFile)) {
    $logs = json_decode(file_get_contents($logFile), true) ?? [];
}

// Réception d’une erreur depuis logError()
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['error'])) {
    $err = json_decode($_POST['error'], true);
    if ($err) {
        $logs[] = $err;
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

// Vider les logs
if (isset($_GET['clear'])) {
    file_put_contents($logFile, json_encode([]));
    header("Location: webhook.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Logs Automoclick</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center">
            <span class="material-symbols-outlined mr-2 text-red-500">report</span>
            Logs Automoclick
        </h1>

        <div class="mb-4 flex justify-between items-center">
            <a href="?clear=1" 
               class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition">
               Vider les logs
            </a>
            <span class="text-gray-600 text-sm">
                Total erreurs : <?= count($logs); ?>
            </span>
        </div>

        <?php if (empty($logs)): ?>
            <div class="p-6 bg-green-100 border border-green-300 text-green-700 rounded-lg">
                ✅ Aucune erreur détectée
            </div>
        <?php else: ?>
            <div class="overflow-x-auto bg-white shadow rounded-lg">
                <table class="min-w-full text-sm text-left text-gray-600">
                    <thead class="bg-gray-50 text-gray-700 font-semibold">
                        <tr>
                            <th class="px-4 py-3">Date/Heure</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Message</th>
                            <th class="px-4 py-3">Contexte</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($logs) as $error): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3"><?= htmlspecialchars($error['time']); ?></td>
                                <td class="px-4 py-3 font-medium text-red-600"><?= htmlspecialchars($error['type']); ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($error['message']); ?></td>
                                <td class="px-4 py-3">
                                    <?php if (!empty($error['context'])): ?>
                                        <pre class="bg-gray-100 p-2 rounded text-xs overflow-x-auto max-w-lg"><?= htmlspecialchars(print_r($error['context'], true)); ?></pre>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
