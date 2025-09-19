<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}

require_once 'db/dbconnect2.php';

$body = json_decode(file_get_contents('php://input'), true);
$input = trim($body['message'] ?? '');

$specialites = [
    'mecanique' => ['Mécanique', ['mecanique','mécanique','mécanicien','mecanicien','reparer','réparer','révision','entretien']],
    'depanneur' => ['Dépanneur', ['depanneur','dépanneur','dépannage','remorquage','assistance','dépanner']],
    'carrosserie' => ['Carrossier(e)', ['carrosserie','carrossier','carrossière','peinture carrosserie','reparation carrosserie','retouche carrosserie']],
    'controle' => ['Contrôleur technique', ['controle','contrôle','controle technique','contrôle technique','ct','inspection','visite technique']],
    'electro' => ['Électromécanicien(ne)', ['electro','électro','electrique','électrique','électromécanicien','electromecanicien','diagnostic électrique']],
    'garage' => ['Garage', ['garage','garagiste','atelier','réparation auto','entretien véhicule']],
    'nettoyage' => ['Nettoyage', ['nettoyage','lavage voiture','laveur','nettoyage vehicule','nettoyage véhicule','laver voiture','detailing','detailing voiture']],
    'peintre' => ['Peintre', ['peintre','peinture','peinture carrosserie','repeindre','retouche peinture']],
    'soudeur' => ['Soudeur(se)', ['soudeur','soudure','souder','soudage']],
    'prepa' => ['Préparateur automobile', ['preparateur','préparateur','préparation voiture','preparation voiture','reprogrammation','reprogrammer','programme','optimisation moteur','optimisation','optimiser','optimiser moteur','optimiser voiture','reprogrammer moteur']],
    'vendeur-auto' => ['Vendeur de véhicule', ['vendeur auto','vente voiture','vendeur de voiture','commercial automobile','concessionnaire','vendeur','vente','vendre voiture','vendre','achat']],
    'loueur' => ['Location de véhicule', ['location voiture','louer voiture','loueur','location vehicule','voiture à louer']],
    'tunning' => ['Tunning', ['tuning','tunning','modification voiture','customisation','personnalisation voiture']]
];

$intentions = [
    'salutation' => [
        ["Salut ! 😊 Comment puis-je t'aider ?", "Bonjour ! Ravi de te voir ici, comment puis-je t'assister ?", "Coucou ! Que puis-je faire pour toi aujourd'hui ?"],
        ['bonjour','salut','coucou']
    ],
    'recherche' => [
        ["Tu cherches un professionnel ? Peux-tu préciser le domaine ?", "Quel type de service ou professionnel souhaites-tu trouver ?"],
        ['cherche','trouver','besoin','souhaite']
    ],
    'particulier' => [
        ["Tu es un particulier, que souhaites-tu faire ?", "Comment puis-je t'aider avec ton compte ou tes recherches ?"],
        ['particulier','inscription','mon compte']
    ],
    'professionnel' => [
        ["Bienvenue parmi les pros ! Que souhaites-tu faire ?", "Dis-moi comment je peux t'aider avec ton compte ou tes services."],
        ['professionnel','inscription','compte pro']
    ],
];

function normalizeText($text) {
    $text = strtolower($text);
    $text = iconv('UTF-8','ASCII//TRANSLIT',$text);
    return $text;
}

function reponseAleatoire(array $reponses) {
    return $reponses[array_rand($reponses)];
}

function chercherProfessionnels($code,$label) {
    global $db;
    $stmt = $db->prepare("SELECT denomination, commune FROM entreprises WHERE FIND_IN_SET(:spe,spe)>0 LIMIT 5");
    $stmt->execute(['spe'=>$code]);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$resultats) return "Désolé, je n’ai trouvé aucun professionnel en $label pour le moment.";
    $reponse = "Voici quelques professionnels en $label :\n";
    foreach ($resultats as $pro) $reponse .= "• {$pro['denomination']} à {$pro['commune']}\n";
    return $reponse;
}

function repondreAvecChatGPT($message) {
    $apiKey = getenv('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');
    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $messages = [
        ['role'=>'system','content'=>"Tu es Jérémy, l'assistant Automoclick. Réponds de façon amicale et professionnelle."],
        ['role'=>'user','content'=>$message],
    ];
    $data = ['model'=>'gpt-4','messages'=>$messages,'temperature'=>0.7];
    $ch = curl_init($endpoint);
    curl_setopt_array($ch,[
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey],
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POSTFIELDS=>json_encode($data)
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($result,true);
    return $response['choices'][0]['message']['content'] ?? "Je n’ai pas compris, peux-tu reformuler ?";
}

// ================= LOGIQUE PRINCIPALE =================
$input_normalized = normalizeText($input);
$response_parts = [];

// 1️⃣ Détecter salutation sans bloquer
foreach ($intentions['salutation'][1] as $mot) {
    if (strpos($input_normalized,$mot)!==false) {
        $response_parts[] = reponseAleatoire($intentions['salutation'][0]);
        break;
    }
}

// 2️⃣ Détecter recherche/pro/particulier
foreach (['recherche','professionnel','particulier'] as $type) {
    foreach ($intentions[$type][1] as $mot) {
        if (strpos($input_normalized,$mot)!==false) {
            $response_parts[] = reponseAleatoire($intentions[$type][0]);
            break 2;
        }
    }
}

// 3️⃣ Détecter spécialités même après salutation
foreach ($specialites as $code => [$label,$aliases]) {
    foreach ($aliases as $alias) {
        if (strpos($input_normalized,strtolower($alias))!==false) {
            $response_parts[] = chercherProfessionnels($code,$label);
            break 2;
        }
    }
}

// 4️⃣ GPT fallback si rien détecté
if (empty($response_parts)) {
    $prompt = "Tu es Jérémy, assistant Automoclick. L'utilisateur a dit : \"$input\". Détermine si c'est une salutation, une recherche ou un message pro. Si recherche, indique la spécialité parmi : ".implode(', ',array_keys($specialites)).". Réponds en JSON : {'type':'salutation'|'recherche'|'pro','specialite':'code_specialite'|'aucune'}";
    $json_response = repondreAvecChatGPT($prompt);
    $data = json_decode($json_response,true);
    if ($data && isset($data['type'])) {
        switch($data['type']) {
            case 'salutation': $response_parts[] = "Bonjour ! Ravi de te voir sur Automoclick 😊 Comment puis-je t'aider ?"; break;
            case 'recherche':
                if ($data['specialite']!=='aucune' && isset($specialites[$data['specialite']])) {
                    [$label,] = $specialites[$data['specialite']];
                    $response_parts[] = chercherProfessionnels($data['specialite'],$label);
                } else $response_parts[] = "Super ! Quel type de professionnel recherches-tu ?";
                break;
            case 'pro': $response_parts[] = "Bienvenue ! Tu es un professionnel. Que souhaites-tu faire sur Automoclick ?"; break;
        }
    }
}

// 5️⃣ Fallback final
if (empty($response_parts)) $response_parts[] = "Je n’ai pas bien compris, peux-tu reformuler ?";

// 6️⃣ Retour JSON
echo json_encode(['token'=>$_SESSION['token'],'response'=>implode(' ',$response_parts)]);
