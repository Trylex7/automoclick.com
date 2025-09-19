<?php
$messages = [
    "Bonne journée à toi 💪",
    "Bon courage pour aujourd'hui 🚀",
    "Objectif 200€ aujourd'hui 🔥",
    "Rien ne t'arrête aujourd'hui ! 🏆",
    "Tu vas tout déchirer 💼",
    "Chaque client compte, donne tout ! 🛍️",
    "Tu es sur la bonne voie 👊",
    "Ne lâche rien, t’es un(e) guerrier(ère) ! 🐯",
    "Fonce vers tes objectifs ! 💰",
    "Un jour de plus vers le succès 🌟"
];

$messageAleatoire = $messages[array_rand($messages)];
echo $messageAleatoire;