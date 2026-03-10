<?php
if (!function_exists('pv_env_truthy')) {
    function pv_env_truthy($key) {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        if ($value === false && isset($_SERVER[$key])) {
            $value = $_SERVER[$key];
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!defined('PV_DEBUG_ENABLED')) {
    define('PV_DEBUG_ENABLED', pv_env_truthy('PV_DEBUG'));
}

require_once __DIR__ . '/setup.php';
$routerResult = require __DIR__ . '/router.php';

// Important: when router returns false, propagate to PHP built-in server
// so static assets are served directly with the correct content-type/body.
if ($routerResult === false) {
    return false;
}
