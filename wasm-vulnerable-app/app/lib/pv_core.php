<?php
if (!defined('PV_APP')) {
    define('PV_APP', true);
}

function pv_session_boot() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function pv_storage_root() {
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    if (is_dir('/uploads') && is_writable('/uploads')) {
        $root = '/uploads';
        return $root;
    }

    $fallback = dirname(__DIR__) . '/../uploads';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0777, true);
    }

    $root = $fallback;
    return $root;
}

function pv_db() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . pv_storage_root() . '/pitch_vulnerable.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function pv_current_user() {
    pv_session_boot();
    return $_SESSION['pv_user'] ?? null;
}

function pv_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function pv_debug_enabled() {
    if (defined('PV_DEBUG_ENABLED')) {
        return PV_DEBUG_ENABLED === true;
    }

    $value = getenv('PV_DEBUG');
    if ($value === false && isset($_ENV['PV_DEBUG'])) {
        $value = $_ENV['PV_DEBUG'];
    }
    if ($value === false && isset($_SERVER['PV_DEBUG'])) {
        $value = $_SERVER['PV_DEBUG'];
    }

    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function pv_time_stub_timestamp() {
    $raw = getenv('PV_TIME_STUB_TS');
    if ($raw !== false && preg_match('/^-?\d+$/', trim((string)$raw))) {
        return (int)trim((string)$raw);
    }

    // Fixed timestamp used when avoiding runtime clock syscalls.
    return 1710000000;
}

function pv_date($format = 'c') {
    $format = (string)$format;
    return gmdate($format, pv_time_stub_timestamp());
}

function pv_render_header($title = 'Pitch-Vulnerable') {
    $user = pv_current_user();
    $username = $user['username'] ?? 'Guest';

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '    <meta charset="UTF-8">';
    echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '    <title>' . pv_escape($title) . '</title>';
    echo '    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">';
    echo '    <link rel="stylesheet" href="/assets/main.css">';
    echo '</head>';
    echo '<body class="jv-page">';
    echo '    <nav class="navbar navbar-expand-lg navbar-dark jv-topbar sticky-top">';
    echo '        <div class="container-xxl">';
    echo '            <a class="navbar-brand d-flex align-items-center gap-2" href="/index.php">';
    echo '                <img class="jv-brand-logo" src="/assets/images/club-mark.svg" alt="Club mark">';
    echo '                <span class="jv-brand-text">Pitch-Vulnerable</span>';
    echo '            </a>';
    echo '            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#jvNavbar" aria-controls="jvNavbar" aria-expanded="false" aria-label="Toggle navigation">';
    echo '                <span class="navbar-toggler-icon"></span>';
    echo '            </button>';
    echo '            <div class="collapse navbar-collapse" id="jvNavbar">';
    echo '                <ul class="navbar-nav me-auto mb-2 mb-lg-0">';
    echo '                    <li class="nav-item"><a class="nav-link" href="/index.php">Home</a></li>';
    echo '                    <li class="nav-item"><a class="nav-link" href="/login.php">Club Member Login</a></li>';
    echo '                    <li class="nav-item"><a class="nav-link" href="/winners.php">Hall of Fame</a></li>';
    echo '                    <li class="nav-item"><a class="nav-link" href="/scoreboard.php">Scoreboard</a></li>';
    echo '                    <li class="nav-item"><a class="nav-link" href="/reset.php">Reset</a></li>';
    echo '                    <li class="nav-item"><a class="nav-link" href="/logout.php">Logout</a></li>';
    echo '                    <!-- <li class="nav-item"><a class="nav-link" href="/hidden.php">Ping The World</a></li> -->';
    echo '                </ul>';
    echo '                <span class="badge text-bg-dark border border-secondary">Session: ' . pv_escape($username) . '</span>';
    echo '            </div>';
    echo '        </div>';
    echo '    </nav>';
    echo '    <main class="container-xxl py-4">';
}

function pv_render_footer() {
    echo '    </main>';
    echo '    <footer class="jv-footer py-3 mt-4">';
    echo '        <div class="container-xxl small text-secondary">Pitch-Vulnerable training app. Vulnerabilities are intentional and provided only for educational use.</div>';
    echo '    </footer>';
    echo '    <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>';
    echo '</body>';
    echo '</html>';
}
