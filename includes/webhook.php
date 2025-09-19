<?php
function logError($type, $message, $context = []) {
    $data = [
        'type' => $type,
        'message' => $message,
        'context' => $context,
        'time' => date('Y-m-d H:i:s')
    ];

    // Envoi vers l'API webhook
    $ch = curl_init("https://automoclick.com/webhook.php"); // <== API
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['error' => json_encode($data)]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // Pour debug si nécessaire
    // error_log("Webhook response: $response");
}

// ⚠️ Capture des erreurs PHP
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    logError("PHP Error", $errstr, [
        'errno' => $errno,
        'file' => $errfile,
        'line' => $errline
    ]);
    return false;
});

// 💥 Capture des exceptions non gérées
set_exception_handler(function ($e) {
    logError("Exception", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
});

// 🔴 Capture des erreurs fatales
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logError("Fatal Error", $error['message'], [
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});
