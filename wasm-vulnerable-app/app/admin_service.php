<?php
define('PV_APP', true);
require_once __DIR__ . '/lib/pv_core.php';
require_once __DIR__ . '/challenge/admin_service_logic.php';

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$action = (string)($_GET['action'] ?? 'status');
$providedToken = (string)($_SERVER['HTTP_X_JV_INTERNAL_TOKEN'] ?? '');
$internalRequest = pv_is_internal_service_request($providedToken);
[$statusCode, $payload] = pv_admin_service_response($action, $remoteAddr, $internalRequest);

http_response_code($statusCode);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
