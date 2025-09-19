<?php 
require_once __DIR__ . '/maintenance.php';
$maintenance = new MaintenanceMode();
if ($maintenance->check()) {
    $maintenance->showMaintenancePage();
}
