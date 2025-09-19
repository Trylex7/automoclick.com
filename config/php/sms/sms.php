<?php
// Update the path below to your autoload.php,
// see https://getcomposer.org/doc/01-basic-usage.md    
require_once '../../../vendor/autoload.php';
use Twilio\Rest\Client;

$sid    = "AC5263bdc6083f585213cc82a6d903c15c";
$token  = "ac89e20d7a1a474859ad6b6d2d948740";
$twilio = new Client($sid, $token);

$message = $twilio->messages->create(
    "+261388745700", // To
    [
        "from" => "+18577544654",
        "body" => "Bonjour, Bienvenue sur Automoclick",
    ]
);

print $message->body;