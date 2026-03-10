<?php
// app/logger.php
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$req_uri = $_SERVER['REQUEST_URI'] ?? 'Unknown'; // Utilissimo per vedere i payload GET!

$log_entry = "[" . date('Y-m-d H:i:s') . "] IP: $ip | URI: $req_uri | Agent: $user_agent\n";

// Usiamo @ per silenziare eventuali errori se la cartella non ha i permessi
@file_put_contents('/var/log/access.log', $log_entry, FILE_APPEND);
?>