<?php
require_once  __DIR__ . '/../db/dbconnect2.php';
function trackPageView($pageName) {
    global $db;

    // Vérifie si la page existe déjà
    $stmt = $db->prepare("SELECT id, views FROM page_views WHERE page = :page");
    $stmt->execute([':page' => $pageName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Incrémente
        $stmt = $db->prepare("UPDATE page_views SET views = views + 1 WHERE id = :id");
        $stmt->execute([':id' => $row['id']]);
    } else {
        // Insère nouvelle page
        $stmt = $db->prepare("INSERT INTO page_views (page, views) VALUES (:page, 1)");
        $stmt->execute([':page' => $pageName]);
    }
}
