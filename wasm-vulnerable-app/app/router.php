<?php
// router.php - Global Interceptor for WASM PHP Honeypot

require_once 'busybox.php';

error_reporting(E_ALL);
ini_set('display_errors', (defined('PV_DEBUG_ENABLED') && PV_DEBUG_ENABLED) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', '/var/log/php_errors.log');

function verbose_log($message, array $context = []) {
    if (!defined('PV_DEBUG_ENABLED') || PV_DEBUG_ENABLED !== true) {
        return;
    }

    $entry = "[" . date('Y-m-d H:i:s') . "] [ROUTER] " . $message;
    if (!empty($context)) {
        $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            $entry .= " | " . $json;
        }
    }
    $entry .= "\n";

    @file_put_contents('/var/log/debug.log', $entry, FILE_APPEND);
}

if (defined('PV_DEBUG_ENABLED') && PV_DEBUG_ENABLED) {
    set_error_handler(function ($severity, $message, $file, $line) {
        verbose_log('PHP runtime warning/error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]);
        return false;
    });

    set_exception_handler(function (Throwable $exception) {
        verbose_log('Uncaught exception', [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    });

    register_shutdown_function(function () {
        $last_error = error_get_last();
        if ($last_error !== null) {
            verbose_log('Shutdown with last error', $last_error);
        }
    });
}

/**
 * Logging functionality integrated from logger.php
 * Logs all incoming requests with IP, URI, and User Agent
 */
function log_request() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $req_uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';
    $referer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
    
    $log_entry = "[" . date('Y-m-d H:i:s') . "] ";
    $log_entry .= "IP: $ip | ";
    $log_entry .= "Method: $method | ";
    $log_entry .= "URI: $req_uri | ";
    $log_entry .= "Referer: $referer | ";
    $log_entry .= "Agent: $user_agent\n";

    // Log to mounted /var/log directory (mapped in wasmer.toml)
    @file_put_contents('/var/log/access.log', $log_entry, FILE_APPEND);
}

// Log every incoming request
log_request();

$document_root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__), '/');
$request_path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$request_path = rawurldecode($request_path);
$requested_file = $document_root . $request_path;

if ($request_path === '/') {
    $requested_file = $document_root . '/index.php';
}

verbose_log('Incoming request', [
    'cwd' => getcwd(),
    'document_root' => $document_root,
    'request_path' => $request_path,
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
    'resolved_requested_file' => $requested_file,
]);

if (is_dir($requested_file)) {
    $requested_file = rtrim($requested_file, '/') . '/index.php';
}

if (is_file($requested_file) && preg_match('/\.(?:png|jpg|jpeg|gif|webp|svg|ico|css|js|map|txt|woff|woff2|ttf|eot)$/i', $requested_file)) {
    verbose_log('Serving static file via builtin server', ['file' => $requested_file]);
    return false; 
}

if (file_exists($requested_file) && is_file($requested_file)) {
    verbose_log('Executing target file', [
        'file' => $requested_file,
        'exists' => file_exists($requested_file),
        'is_readable' => is_readable($requested_file),
    ]);
    
    // Read the raw source code (HTML + PHP)
    $raw_code = file_get_contents($requested_file);
    if ($raw_code === false) {
        verbose_log('Failed to read requested file', ['file' => $requested_file]);
        header('HTTP/1.1 500 Internal Server Error');
        echo '<h1>500 Internal Server Error</h1>';
        echo 'Failed to read script source.';
        return;
    }
    
    // THE GLOBAL MAGIC: Apply our rewrites to the entire file
    $safe_code = preg_replace('/\bsystem\s*\(/i', 'fake_system(', $raw_code);
    $safe_code = preg_replace('/\bshell_exec\s*\(/i', 'fake_shell_exec(', $safe_code);
    $safe_code = preg_replace('/\bexec\s*\(/i', 'fake_exec(', $safe_code);
    $safe_code = preg_replace('/\bpassthru\s*\(/i', 'fake_passthru(', $safe_code);

    try {
        // This allows eval to perfectly handle mixed HTML/PHP files!
        eval('?>' . $safe_code);
        verbose_log('Execution completed', ['file' => $requested_file]);
    } catch (Throwable $e) {
        verbose_log('Honeypot execution error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        error_log("Honeypot execution error: " . $e->getMessage());
    }
    
} else {
    verbose_log('Requested file not found', [
        'file' => $requested_file,
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
    ]);
    header("HTTP/1.1 404 Not Found");
    echo "<h1>404 Not Found</h1>";
    echo "The requested URL was not found on this server.";
}
?>
