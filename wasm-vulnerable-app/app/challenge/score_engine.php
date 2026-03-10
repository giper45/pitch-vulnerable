<?php
if (!defined('PV_APP')) {
    http_response_code(403);
    echo 'Direct access denied';
    exit;
}

function pv_default_flags() {
    return [
        'quest_1' => 'S9_BoxHunter#9',
        'quest_2' => '',
        'quest_3' => '',
        'quest_4' => 'SSRF{localhost_admin_service_unlocked}',
        'quest_5' => '',
    ];
}

function pv_load_flags() {
    $flagsPath = pv_storage_root() . '/secret_data/protected/scoreboard_flags.json';
    if (!is_file($flagsPath)) {
        return pv_default_flags();
    }

    $raw = @file_get_contents($flagsPath);
    if ($raw === false) {
        return pv_default_flags();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return pv_default_flags();
    }

    return array_merge(pv_default_flags(), $decoded);
}

function pv_init_scoreboard_state() {
    pv_session_boot();

    if (!isset($_SESSION['pv_scoreboard']) || !is_array($_SESSION['pv_scoreboard'])) {
        $_SESSION['pv_scoreboard'] = [
            'quest_1' => false,
            'quest_2' => false,
            'quest_3' => false,
            'quest_4' => false,
            'quest_5' => false,
            'activity' => [],
        ];
    }
}

function pv_log_activity($line) {
    pv_init_scoreboard_state();
    $_SESSION['pv_scoreboard']['activity'][] = pv_date('H:i:s') . ' | ' . $line;

    if (count($_SESSION['pv_scoreboard']['activity']) > 18) {
        $_SESSION['pv_scoreboard']['activity'] = array_slice($_SESSION['pv_scoreboard']['activity'], -18);
    }
}

function pv_mark_solved($questId) {
    pv_init_scoreboard_state();
    $_SESSION['pv_scoreboard'][$questId] = true;
}

function pv_is_solved($questId) {
    pv_init_scoreboard_state();
    return !empty($_SESSION['pv_scoreboard'][$questId]);
}

function pv_attack_feed() {
    pv_init_scoreboard_state();

    $ambient = [
        pv_date('H:i:s') . ' | Recon bot scans /login.php with payload list',
        pv_date('H:i:s') . ' | Suspicious traversal pattern detected: ../../../../etc/passwd',
        pv_date('H:i:s') . ' | DOM watcher active on Hall of Fame',
        pv_date('H:i:s') . ' | Internal service endpoint hidden behind localhost filter',
        pv_date('H:i:s') . ' | Rumor says a hidden ping console exists for authenticated users',
    ];

    return array_merge($ambient, $_SESSION['pv_scoreboard']['activity']);
}

function pv_normalize_multiline($value) {
    $text = str_replace("\r\n", "\n", (string)$value);
    return trim($text);
}

function pv_normalize_passwd_blob($value) {
    $text = str_replace(["\r\n", "\r"], "\n", (string)$value);
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $lines = preg_split('/\n+/', $text) ?: [];

    // When copied from rendered HTML, entries are often flattened on one line.
    if (count($lines) === 1) {
        $lines = preg_split('/\s+(?=[A-Za-z0-9_.-]+:[^:\s]*:\d+:\d+:)/', $text) ?: [$text];
    }

    $normalized = [];
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line !== '') {
            $normalized[] = $line;
        }
    }

    return implode("\n", $normalized);
}

function pv_expected_passwd_content() {
    $candidates = [
        '/sandbox_etc/passwd',
        dirname(__DIR__, 2) . '/fake_etc/passwd',
        '/etc/passwd',
    ];

    foreach ($candidates as $path) {
        if (is_file($path) && is_readable($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                return pv_normalize_passwd_blob($raw);
            }
        }
    }

    return '';
}

function pv_expected_rce_flag() {
    $candidates = [
        pv_storage_root() . '/.secret/reverse_shell_secret.flag',
        dirname(__DIR__, 2) . '/uploads/.secret/reverse_shell_secret.flag',
    ];

    foreach ($candidates as $path) {
        if (is_file($path) && is_readable($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                return trim((string)$raw);
            }
        }
    }

    return '';
}
