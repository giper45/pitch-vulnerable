<?php
define('PV_APP', true);
require_once __DIR__ . '/lib/pv_core.php';

pv_session_boot();

function pv_path_is_inside($path, $baseDir) {
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedBase = rtrim(str_replace('\\', '/', $baseDir), '/');
    return strpos($normalizedPath, $normalizedBase . '/') === 0 || $normalizedPath === $normalizedBase;
}

function pv_delete_tree($path, $baseDir) {
    if ($path === '' || !pv_path_is_inside($path, $baseDir) || !file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $items = @scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        pv_delete_tree($path . DIRECTORY_SEPARATOR . $item, $baseDir);
    }

    @rmdir($path);
}

function pv_clear_logs() {
    $logDir = '/var/log';
    if (!is_dir($logDir) || !is_writable($logDir)) {
        $logDir = dirname(__DIR__) . '/fake_logs';
    }

    foreach (['access.log', 'debug.log', 'lfi_attacks.log', 'setup.log'] as $logFile) {
        $target = rtrim($logDir, '/') . '/' . $logFile;
        @file_put_contents($target, '');
    }
}

$status = '';
$statusClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmPhrase = trim((string)($_POST['confirm_phrase'] ?? ''));
    $ack = (string)($_POST['confirm_ack'] ?? '');

    if ($confirmPhrase !== 'RESET PITCH-VULNERABLE' || $ack !== 'yes') {
        $status = 'Invalid confirmation. Type exactly: RESET PITCH-VULNERABLE.';
        $statusClass = 'bad';
    } else {
        $storageRoot = function_exists('pv_setup_storage_root') ? pv_setup_storage_root() : pv_storage_root();
        $dbPath = $storageRoot . '/pitch_vulnerable.sqlite';
        $secretPath = $storageRoot . '/secret_data';
        $hiddenSecretPath = $storageRoot . '/.secret';

        @unlink($dbPath);
        pv_delete_tree($secretPath, $storageRoot);
        pv_delete_tree($hiddenSecretPath, $storageRoot);
        pv_clear_logs();

        if (function_exists('pv_seed_application_state')) {
            pv_seed_application_state($storageRoot);
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $status = 'Reset completed: database, secret_data, .secret, session, and logs were restored to the initial state.';
        $statusClass = 'ok';
    }
}

pv_render_header('Application State Reset');
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <section class="jv-card p-3 p-md-4">
            <h3 class="jv-panel-title">Reset Pitch-Vulnerable State</h3>
            <p class="jv-muted">This operation deletes runtime data and recreates the initial training state.</p>
            <ul class="mb-3">
                <li>Reset SQLite DB (<span class="inline-code">users</span>, <span class="inline-code">guestbook</span>)</li>
                <li>Reset <span class="inline-code">secret_data</span> folder</li>
                <li>Reset <span class="inline-code">.secret</span> folder</li>
                <li>Reset session (login and scoreboard progress)</li>
                <li>Clear application logs</li>
            </ul>

            <?php if ($status !== ''): ?>
                <div class="notice <?php echo pv_escape($statusClass); ?>"><?php echo pv_escape($status); ?></div>
            <?php endif; ?>

            <div class="notice warn">
                Required confirmation: type <span class="inline-code">RESET PITCH-VULNERABLE</span> and check the box.
            </div>

            <form method="post" class="d-grid gap-2">
                <label for="confirm_phrase" class="form-label mb-0">Confirmation phrase</label>
                <input id="confirm_phrase" class="form-control" type="text" name="confirm_phrase" placeholder="RESET PITCH-VULNERABLE">

                <div class="form-check mt-2">
                    <input id="confirm_ack" class="form-check-input" type="checkbox" name="confirm_ack" value="yes">
                    <label class="form-check-label" for="confirm_ack">I confirm I want to restore all state</label>
                </div>

                <button class="btn btn-outline-light mt-2" type="submit">Run Reset</button>
            </form>
        </section>
    </div>
</div>
<?php pv_render_footer(); ?>
