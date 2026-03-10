<?php
if (!defined('WASM_SETUP_BOOTSTRAPPED')) {
    define('WASM_SETUP_BOOTSTRAPPED', true);

    function setup_log_target() {
        static $target = null;
        if ($target !== null) {
            return $target;
        }

        if (is_dir('/var/log') && is_writable('/var/log')) {
            $target = '/var/log/setup.log';
            return $target;
        }

        $fallbackDir = dirname(__DIR__) . '/fake_logs';
        if (!is_dir($fallbackDir)) {
            @mkdir($fallbackDir, 0777, true);
        }

        $target = $fallbackDir . '/setup.log';
        return $target;
    }

    function setup_log($message, array $context = []) {
        $entry = '[' . date('Y-m-d H:i:s') . '] [SETUP] ' . $message;
        if (!empty($context)) {
            $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $entry .= ' | ' . $json;
            }
        }
        $entry .= "\n";
        @file_put_contents(setup_log_target(), $entry, FILE_APPEND);
    }

    function pv_setup_storage_root() {
        if (is_dir('/uploads') && is_writable('/uploads')) {
            return '/uploads';
        }

        $fallback = dirname(__DIR__) . '/uploads';
        if (!is_dir($fallback)) {
            @mkdir($fallback, 0777, true);
        }

        return $fallback;
    }

    function resolve_target_path($targetPath) {
        if (is_dir($targetPath)) {
            return rtrim($targetPath, '/') . '/' . basename($targetPath);
        }
        return $targetPath;
    }

    function copy_to_etc($sourcePath, $targetPath) {
        $effectiveTarget = resolve_target_path($targetPath);

        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            setup_log('Source not readable', ['source' => $sourcePath]);
            return;
        }

        $targetDir = dirname($effectiveTarget);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $content = @file_get_contents($sourcePath);
        if ($content === false) {
            setup_log('Failed reading source', ['source' => $sourcePath]);
            return;
        }

        $ok = @file_put_contents($effectiveTarget, $content);
        if ($ok === false) {
            setup_log('Failed writing target', [
                'target' => $effectiveTarget,
                'target_exists' => file_exists($effectiveTarget),
                'target_is_dir' => is_dir($effectiveTarget),
                'target_dir' => $targetDir,
                'target_dir_writable' => is_writable($targetDir),
            ]);
            return;
        }

        setup_log('Hydrated target file', [
            'source' => $sourcePath,
            'target' => $effectiveTarget,
            'bytes' => $ok,
        ]);
    }

    function ensure_jv_directory($path) {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
    }

    function ensure_jv_file($path, $content) {
        if (!is_file($path)) {
            @file_put_contents($path, $content);
            setup_log('Created seed file', ['path' => $path]);
        }
    }

    function ensure_jv_token_file($path) {
        if (is_file($path)) {
            return;
        }

        $token = '';
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $token = sha1(uniqid('pv_internal_', true));
        }

        @file_put_contents($path, $token . "\n");
        setup_log('Created internal token file', ['path' => $path]);
    }

    function initialize_jv_database($dbPath) {
        try {
            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE,
                    password TEXT,
                    role TEXT,
                    secret_hint TEXT
                )'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS guestbook (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    author TEXT,
                    message TEXT,
                    created_at TEXT
                )'
            );

            $usersCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($usersCount === 0) {
                $pdo->exec("INSERT INTO users (username, password, role, secret_hint) VALUES
                    ('admin', 'club_admin_1997', 'admin', 'Only admin can access the tactical vault'),
                    ('striker', 'S9_BoxHunter#9', 'player', 'No. 9 finishing profile'),
                    ('winger', 'W11_LineBreaker#11', 'player', 'Wide runner specialist'),
                    ('playmaker', 'M6_ControlGrid#6', 'player', 'Midfield balance profile')
                ");
            }

            $guestbookCount = (int)$pdo->query('SELECT COUNT(*) FROM guestbook')->fetchColumn();
            if ($guestbookCount === 0) {
                $pdo->exec("INSERT INTO guestbook (author, message, created_at) VALUES
                    ('North End', 'Support the club. Leave your name in the Hall of Fame.', datetime('now')),
                    ('Old School', 'Blackfield Champion', datetime('now'))
                ");
            }

            setup_log('Database initialized', ['db' => $dbPath]);
        } catch (Throwable $e) {
            setup_log('Database init error', ['error' => $e->getMessage(), 'db' => $dbPath]);
        }
    }

    function pv_seed_application_state($storageRoot) {
        $secretRoot = $storageRoot . '/secret_data';
        $protectedRoot = $secretRoot . '/protected';
        $hiddenSecretRoot = $storageRoot . '/.secret';

        ensure_jv_directory($secretRoot);
        ensure_jv_directory($protectedRoot);
        ensure_jv_directory($hiddenSecretRoot);

        initialize_jv_database($storageRoot . '/pitch_vulnerable.sqlite');
        ensure_jv_token_file($protectedRoot . '/internal_service.token');

        ensure_jv_file(
            $secretRoot . '/scouting_report.txt',
            "Pitch-Vulnerable scouting report\n" .
            "Objective 2 tip: try traversal from /app/biographies to ../../uploads/secret_data/scouting_report.txt\n" .
            "Flag preview: LFI{blackfield_traversal_success}\n"
        );

        ensure_jv_file(
            $protectedRoot . '/internal_service.flag',
            'SSRF{localhost_admin_service_unlocked}' . "\n"
        );

        ensure_jv_file(
            $hiddenSecretRoot . '/reverse_shell_secret.flag',
            'RCE{ping_the_world_compromised}' . "\n"
        );

        @file_put_contents(
            $protectedRoot . '/scoreboard_flags.json',
            json_encode([
                'quest_1' => 'S9_BoxHunter#9',
                'quest_2' => '',
                'quest_3' => '',
                'quest_4' => 'SSRF{localhost_admin_service_unlocked}',
                'quest_5' => '',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        ensure_jv_file(
            $protectedRoot . '/.deny-web-access',
            "This folder is outside /app web root.\n"
        );
    }

    setup_log('Bootstrap start', ['cwd' => getcwd()]);

    copy_to_etc('/sandbox_etc/passwd', '/etc/passwd');
    copy_to_etc('/sandbox_etc/shadow', '/etc/shadow');

    $storageRoot = pv_setup_storage_root();
    pv_seed_application_state($storageRoot);

    setup_log('Bootstrap end', ['storage_root' => $storageRoot]);
}
