<?php
$nonce = base64_encode(random_bytes(16));

header("Content-Security-Policy: script-src 'self' 'nonce-$nonce' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://www.google.com/recaptcha https://code.jquery.com;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block"); 
header("Referrer-Policy: no-referrer-when-downgrade");
header("Permissions-Policy: geolocation=(self \"https://www.google.com\"), microphone=(self)");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");


?>
